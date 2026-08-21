<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDO;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\FileMedia\Application\FileMediaException;
use PeanutAdmin\FileMedia\Application\FileObject;
use PeanutAdmin\FileMedia\Application\FileService;
use PeanutAdmin\FileMedia\Application\UploadPolicy;
use PeanutAdmin\FileMedia\Persistence\PdoFileRepository;
use PeanutAdmin\FileMedia\Storage\StorageProvider;
use PeanutAdmin\FileMedia\Storage\StoredObject;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Api\RequestId;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\DataPermissionAdapter;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Authorization\PermissionRequirement;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationEvaluator;
use PeanutAdmin\Kernel\Host\AtomicOperationAdapter;
use PeanutAdmin\Kernel\Host\AuthorizedExternalOperation;
use PeanutAdmin\Kernel\Host\ExternalHostConfiguration;
use PeanutAdmin\Kernel\Host\ExternalOperationDefinition;
use PeanutAdmin\Kernel\Host\ExternalOperationHost;
use PeanutAdmin\Kernel\Host\ExternalOperationRequest;
use PeanutAdmin\Kernel\Host\ExternalOperationResponse;
use PeanutAdmin\Kernel\Host\ExternalOperationResult;
use PeanutAdmin\Kernel\Host\ModuleAvailabilityAdapter;
use PeanutAdmin\Kernel\Host\PermissionAdapter;
use PeanutAdmin\Kernel\Host\ProblemDetailsAdapter;
use PeanutAdmin\Kernel\Host\TrustedContextAdapter;
use PeanutAdmin\Kernel\Host\TypedTargetAdapter;
use PeanutAdmin\Kernel\Http\PermissionMiddleware;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use think\Request;
use think\Response;
use Throwable;

final class FileRuntimeFactory
{
    /** @return array<string, ExternalOperationDefinition> */
    public static function operations(): array
    {
        return [
            'listFiles' => self::operation('listFiles', 'GET', '/api/v1/files', 'peanut.file-media.read'),
            'createFile' => self::operation(
                'createFile',
                'POST',
                '/api/v1/files',
                'peanut.file-media.create',
                true,
            ),
            'getFile' => self::operation(
                'getFile',
                'GET',
                '/api/v1/files/{file_key}',
                'peanut.file-media.read',
            ),
            'downloadFile' => self::operation(
                'downloadFile',
                'GET',
                '/api/v1/files/{file_key}/content',
                'peanut.file-media.read',
            ),
            'archiveFile' => self::operation(
                'archiveFile',
                'DELETE',
                '/api/v1/files/{file_key}',
                'peanut.file-media.delete',
                true,
            ),
        ];
    }

    public static function list(Request $request, ?PDO $pdo = null, ?CompiledModuleRegistry $modules = null): Response
    {
        $pdo ??= MemberAdminRuntime::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['listFiles'];
        $externalRequest = self::externalRequest($request, $operation, '/api/v1/files');
        $response = self::host($pdo, $modules)->read(
            $operation,
            $externalRequest,
            static function (AuthorizedExternalOperation $authorized, ExternalOperationRequest $query) use ($pdo): ExternalOperationResponse {
                try {
                    self::assertEmptyPayload($query);
                    $input = self::listQuery($query);
                    $result = (new PdoFileRepository($pdo))->list(
                        self::context($authorized)->tenantId,
                        $input['status'],
                        $input['page'],
                        $input['page_size'],
                    );

                    return new ExternalOperationResponse(200, [
                        'data' => [
                            'items' => array_map(static fn(FileObject $file): array => $file->toArray(), $result['items']),
                        ],
                        'page' => $result['page'],
                        'page_size' => $result['page_size'],
                        'total' => $result['total'],
                    ]);
                } catch (FileMediaException $exception) {
                    throw self::apiException($exception);
                }
            },
        );

        return self::jsonResponse($response, $externalRequest->requestId->value);
    }

    public static function detail(
        Request $request,
        string $fileKey,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
    ): Response {
        $pdo ??= MemberAdminRuntime::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['getFile'];
        $externalRequest = self::externalRequest($request, $operation, self::detailPath($fileKey));
        $response = self::host($pdo, $modules)->read(
            $operation,
            $externalRequest,
            static function (AuthorizedExternalOperation $authorized, ExternalOperationRequest $query) use ($pdo, $fileKey): ExternalOperationResponse {
                try {
                    self::assertNoInput($query);
                    self::assertFileKey($fileKey);
                    $file = (new PdoFileRepository($pdo))->get(self::context($authorized)->tenantId, $fileKey);

                    return new ExternalOperationResponse(200, ['data' => $file->toArray()]);
                } catch (FileMediaException $exception) {
                    throw self::apiException($exception);
                }
            },
        );

        return self::jsonResponse($response, $externalRequest->requestId->value);
    }

    public static function upload(
        Request $request,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
        ?StorageProvider $storage = null,
    ): Response {
        $pdo ??= MemberAdminRuntime::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $storage ??= self::storage();
        $operation = self::operations()['createFile'];
        $externalRequest = self::externalRequest($request, $operation, '/api/v1/files');
        $stored = null;
        $service = new FileService(new PdoFileRepository($pdo), $storage, self::uploadPolicy());
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($request, $storage, &$stored): ExternalOperationResult {
                try {
                    self::assertNoInput($command);
                    [$sourcePath, $originalName] = self::uploadedFile($request);
                    $service = new FileService(new PdoFileRepository($transaction), $storage, self::uploadPolicy());
                    $file = $service->upload(
                        self::context($authorized),
                        $sourcePath,
                        $originalName,
                        static function (StoredObject $object) use (&$stored): void {
                            $stored = $object;
                        },
                    );
                    (new FileDeliveryRepository($transaction))->recordImage($file, $sourcePath);

                    return new ExternalOperationResult(
                        201,
                        ['data' => $file->toArray()],
                        'tenant.file.created',
                        'peanut.file-media.create',
                        $file->auditMetadata(),
                        'file',
                        $file->fileKey,
                    );
                } catch (FileMediaException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard(),
        );
        if ($response->status >= 400 && $stored instanceof StoredObject) {
            try {
                $service->compensate($stored);
            } catch (FileMediaException $exception) {
                $response = (new ProblemDetailsAdapter())->respond(
                    self::apiException($exception),
                    $externalRequest->requestId,
                );
            }
        }

        return self::jsonResponse($response, $externalRequest->requestId->value, true);
    }

    public static function archive(
        Request $request,
        string $fileKey,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
    ): Response {
        $pdo ??= MemberAdminRuntime::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['archiveFile'];
        $externalRequest = self::externalRequest($request, $operation, self::detailPath($fileKey));
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($fileKey): ExternalOperationResult {
                try {
                    self::assertNoInput($command);
                    self::assertFileKey($fileKey);
                    $revision = self::expectedRevision($command);
                    $file = (new PdoFileRepository($transaction))->archive(
                        self::context($authorized),
                        $fileKey,
                        $revision,
                    );

                    return new ExternalOperationResult(
                        200,
                        ['data' => $file->toArray()],
                        'tenant.file.archived',
                        'peanut.file-media.delete',
                        $file->auditMetadata(),
                        'file',
                        $file->fileKey,
                    );
                } catch (FileMediaException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard(),
        );

        return self::jsonResponse($response, $externalRequest->requestId->value);
    }

    public static function download(
        Request $request,
        string $fileKey,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
        ?StorageProvider $storage = null,
    ): Response {
        $pdo ??= MemberAdminRuntime::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $storage ??= self::storage();
        $operation = self::operations()['downloadFile'];
        $path = self::detailPath($fileKey) . '/content';
        $externalRequest = self::externalRequest($request, $operation, $path);
        $response = self::host($pdo, $modules)->read(
            $operation,
            $externalRequest,
            static function (AuthorizedExternalOperation $authorized, ExternalOperationRequest $query) use (
                $pdo,
                $fileKey,
                $storage,
            ): ExternalOperationResponse {
                try {
                    self::assertNoInput($query);
                    self::assertFileKey($fileKey);
                    $context = self::context($authorized);

                    return (new PdoTransactionManager($pdo))->run(static function () use (
                        $pdo,
                        $fileKey,
                        $storage,
                        $context,
                    ): ExternalOperationResponse {
                        $file = (new PdoFileRepository($pdo))->getForDownload($context->tenantId, $fileKey);
                        if (!hash_equals($storage->key(), $file->storageProviderKey)) {
                            throw FileMediaException::storageUnavailable();
                        }
                        (new PdoAuditRepository($pdo))->appendTenantMember(
                            $context,
                            'tenant.file.downloaded',
                            'peanut.file-media.read',
                            'file',
                            $file->fileKey,
                            targetCount: 1,
                            metadata: $file->auditMetadata(),
                        );
                        $stream = $storage->open($file->storageKey);
                        $content = stream_get_contents($stream);
                        fclose($stream);
                        if (!is_string($content) || strlen($content) !== $file->sizeBytes) {
                            throw FileMediaException::storageUnavailable();
                        }

                        return new ExternalOperationResponse(200, [
                            'content' => $content,
                            'file' => $file,
                        ]);
                    });
                } catch (FileMediaException $exception) {
                    throw self::apiException($exception);
                }
            },
        );
        if ($response->status >= 400) {
            return self::jsonResponse($response, $externalRequest->requestId->value);
        }
        $file = $response->body['file'] ?? null;
        $content = $response->body['content'] ?? null;
        if (!$file instanceof FileObject || !is_string($content)) {
            return self::jsonResponse(
                (new ProblemDetailsAdapter())->respond(
                    self::apiException(FileMediaException::storageUnavailable()),
                    $externalRequest->requestId,
                ),
                $externalRequest->requestId->value,
            );
        }

        return Response::create($content, 'html', 200)->header([
            'Content-Type' => $file->mediaType,
            'Content-Length' => (string) $file->sizeBytes,
            'Content-Disposition' => self::contentDisposition($file->originalName),
            'X-Content-Type-Options' => 'nosniff',
            'X-Request-Id' => $externalRequest->requestId->value,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function host(PDO $pdo, CompiledModuleRegistry $modules): ExternalOperationHost
    {
        $configuration = self::hostConfiguration();
        $permissions = new PermissionMiddleware(
            new TenantAuthorizationEvaluator(
                new PdoTenantAuthorizationRepository($pdo),
                new RevisionPermissionCache(),
            ),
            new PlatformAuthorizationEvaluator(
                new PdoPlatformAuthorizationRepository($pdo),
                new RevisionPermissionCache(),
            ),
        );
        $unusedDataAuthorization = new DataPermissionAdapter(
            static function (): never {
                throw new LogicException('File operations do not accept data-query authorization.');
            },
            static function (): never {
                throw new LogicException('File operations do not accept typed targets.');
            },
        );

        return new ExternalOperationHost(
            $configuration,
            new TrustedContextAdapter($configuration),
            new ModuleAvailabilityAdapter($modules, new ModuleGuard(new PdoModuleRuntimeRepository($pdo))),
            new PermissionAdapter($permissions),
            new TypedTargetAdapter($unusedDataAuthorization),
            new AtomicOperationAdapter($pdo),
            new ProblemDetailsAdapter(),
        );
    }

    private static function hostConfiguration(): ExternalHostConfiguration
    {
        $root = dirname(__DIR__, 3);
        /** @var array{roots: list<string>} $moduleConfig */
        $moduleConfig = require $root . '/backend/config/modules.php';
        /** @var array{tenant: array{clients: non-empty-list<string>}} $authConfig */
        $authConfig = require $root . '/backend/config/auth.php';

        return new ExternalHostConfiguration(
            new ModuleHostLayout('backend/app/Modules', 'PeanutAdmin\\App\\Modules', 'frontend/src/modules'),
            $moduleConfig['roots'],
            '/api/v1',
            '/api/platform/v1',
            'docs/api/openapi.yaml',
            'backend/route/openapi-generated.php',
            'packages/web/admin-core/src/generated/api.d.ts',
            $authConfig['tenant']['clients'],
            'X-Request-Id',
        );
    }

    private static function storage(): StorageProvider
    {
        /** @var array{provider: string, local_root: string, public_roots: list<string>} $config */
        $config = require dirname(__DIR__, 3) . '/backend/config/file-media.php';
        if ($config['provider'] !== 'local-private') {
            throw FileMediaException::storageUnavailable();
        }

        return new LocalPrivateStorageProvider($config['local_root'], $config['public_roots']);
    }

    private static function uploadPolicy(): UploadPolicy
    {
        /** @var array{max_bytes: int, allowed_media_types: list<string>} $config */
        $config = require dirname(__DIR__, 3) . '/backend/config/file-media.php';

        return new UploadPolicy($config['allowed_media_types'], $config['max_bytes']);
    }

    private static function operation(
        string $operationId,
        string $method,
        string $path,
        string $permission,
        bool $command = false,
    ): ExternalOperationDefinition {
        return new ExternalOperationDefinition(
            $operationId,
            $method,
            $path,
            'tenant',
            'peanut.file-media',
            new PermissionRequirement('tenant', [$permission]),
            atomicCommand: $command,
        );
    }

    private static function externalRequest(
        Request $request,
        ExternalOperationDefinition $operation,
        string $path,
    ): ExternalOperationRequest {
        $route = $request->route();
        $context = is_array($route) ? ($route['tenant_context'] ?? null) : null;
        $requestId = $context instanceof TenantContext ? $context->requestId : MemberAdminRuntime::requestId($request);
        $comparisonTime = self::millisecond(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        return new ExternalOperationRequest(
            RequestId::fromHeader($requestId),
            $context,
            $operation->method,
            $path,
            [
                'payload' => MemberAdminRuntime::body($request),
                'query' => is_array($request->get()) ? $request->get() : [],
                'if_match' => MemberAdminRuntime::header($request, 'if-match'),
            ],
            [],
            null,
            $comparisonTime,
            $comparisonTime->modify('+24 hours'),
        );
    }

    /** @return callable(AuthorizedExternalOperation, ExternalOperationRequest, PDO): void */
    private static function commandGuard(): callable
    {
        return static function (
            AuthorizedExternalOperation $authorized,
            ExternalOperationRequest $request,
            PDO $transaction,
        ): void {
            $context = self::context($authorized);
            if (!$transaction->inTransaction()) {
                throw new LogicException('File Module availability lock requires an active transaction.');
            }
            foreach (['pa_module_installation' => 'module_key', 'pa_tenant_module' => 'tenant_id'] as $table => $column) {
                $sql = $table === 'pa_module_installation'
                    ? 'SELECT module_key FROM pa_module_installation WHERE module_key = :module_key FOR SHARE'
                    : 'SELECT tenant_id FROM pa_tenant_module WHERE tenant_id = :tenant_id AND module_key = :module_key FOR SHARE';
                $statement = $transaction->prepare($sql);
                $parameters = ['module_key' => 'peanut.file-media'];
                if ($column === 'tenant_id') {
                    $parameters['tenant_id'] = $context->tenantId;
                }
                $statement->execute($parameters);
                $statement->fetch(PDO::FETCH_ASSOC);
            }
            $guard = new ModuleGuard(new PdoModuleRuntimeRepository($transaction));
            $guard->assertDeployment('peanut.file-media');
            $guard->assertTenant($context->tenantId, 'peanut.file-media', $request->comparisonTime);
        };
    }

    private static function context(AuthorizedExternalOperation $authorized): TenantContext
    {
        if (!$authorized->context instanceof TenantContext) {
            throw FileMediaException::notFound();
        }

        return $authorized->context;
    }

    private static function assertNoInput(ExternalOperationRequest $request): void
    {
        self::assertEmptyPayload($request);
        $query = $request->body['query'] ?? null;
        if (!is_array($query) || $query !== []) {
            throw FileMediaException::uploadInvalid('The file operation does not accept query parameters.');
        }
    }

    private static function assertEmptyPayload(ExternalOperationRequest $request): void
    {
        $payload = $request->body['payload'] ?? null;
        if (!is_array($payload) || $payload !== []) {
            throw FileMediaException::uploadInvalid('The file operation contains undeclared form fields.');
        }
    }

    /** @return array{status: string, page: int, page_size: int} */
    private static function listQuery(ExternalOperationRequest $request): array
    {
        $query = $request->body['query'] ?? null;
        if (!is_array($query) || array_diff(array_keys($query), ['status', 'page', 'page_size']) !== []) {
            throw FileMediaException::uploadInvalid('The file list query is invalid.');
        }
        $status = $query['status'] ?? 'ready';
        if (!is_string($status) || !in_array($status, ['ready', 'archived'], true)) {
            throw FileMediaException::uploadInvalid('The file status filter is invalid.');
        }

        return [
            'status' => $status,
            'page' => self::positiveInteger($query['page'] ?? '1', 10000),
            'page_size' => self::positiveInteger($query['page_size'] ?? '20', 100),
        ];
    }

    private static function positiveInteger(mixed $value, int $maximum): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        } else {
            $integer = false;
        }
        if (!is_int($integer) || $integer > $maximum) {
            throw FileMediaException::uploadInvalid('The file pagination input is invalid.');
        }

        return $integer;
    }

    /** @return array{string, string} */
    private static function uploadedFile(Request $request): array
    {
        $files = $request->file();
        if (!is_array($files) || array_keys($files) !== ['file'] || !is_object($files['file'])) {
            throw FileMediaException::uploadInvalid('Exactly one multipart file field named file is required.');
        }
        $file = $files['file'];
        if (method_exists($file, 'getError') && (int) $file->getError() !== UPLOAD_ERR_OK) {
            throw FileMediaException::uploadInvalid();
        }
        $path = method_exists($file, 'getPathname') ? $file->getPathname() : null;
        $name = match (true) {
            method_exists($file, 'getOriginalName') => $file->getOriginalName(),
            method_exists($file, 'getOriginalFilename') => $file->getOriginalFilename(),
            default => null,
        };
        if (!is_string($path) || !is_string($name)) {
            throw FileMediaException::uploadInvalid();
        }

        return [$path, $name];
    }

    private static function expectedRevision(ExternalOperationRequest $request): int
    {
        $ifMatch = $request->body['if_match'] ?? null;
        if (!is_string($ifMatch) || preg_match('/^"rev-([1-9][0-9]*)"$/D', $ifMatch, $matches) !== 1) {
            throw FileMediaException::preconditionRequired();
        }
        $revision = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($revision)) {
            throw FileMediaException::preconditionRequired();
        }

        return $revision;
    }

    private static function assertFileKey(string $fileKey): void
    {
        if (preg_match('/^file_[0-9a-f]{32}$/D', $fileKey) !== 1) {
            throw FileMediaException::notFound();
        }
    }

    private static function detailPath(string $fileKey): string
    {
        return '/api/v1/files/' . rawurlencode($fileKey);
    }

    private static function jsonResponse(
        ExternalOperationResponse $response,
        string $requestId,
        bool $created = false,
    ): Response {
        $body = $response->body;
        if ($response->status >= 200 && $response->status <= 299) {
            $meta = [
                'request_id' => $requestId,
                ...array_filter([
                    'page' => $body['page'] ?? null,
                    'page_size' => $body['page_size'] ?? null,
                    'total' => $body['total'] ?? null,
                ], static fn(mixed $value): bool => $value !== null),
            ];
            unset($body['page'], $body['page_size'], $body['total']);
            $body['meta'] = $meta;
        }
        $headers = [
            'Content-Type' => $response->contentType,
            'X-Request-Id' => $requestId,
            'Cache-Control' => 'no-store',
            ...$response->headers,
        ];
        $data = $body['data'] ?? null;
        if (is_array($data) && is_int($data['revision'] ?? null)) {
            $headers['ETag'] = '"rev-' . $data['revision'] . '"';
        }
        if ($created && is_array($data) && is_string($data['file_key'] ?? null)) {
            $headers['Location'] = self::detailPath($data['file_key']);
        }

        return Response::create($body, 'json', $response->status)->header($headers);
    }

    private static function contentDisposition(string $name): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'download';
        $fallback = trim(substr($fallback, 0, 120), '._-');
        if ($fallback === '') {
            $fallback = 'download';
        }

        return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }

    private static function apiException(FileMediaException $exception): ApiException
    {
        return new ApiException($exception->errorCode, $exception->httpStatus, $exception->getMessage());
    }

    private static function millisecond(DateTimeImmutable $date): DateTimeImmutable
    {
        $date = $date->setTimezone(new DateTimeZone('UTC'));

        return $date->setTime(
            (int) $date->format('H'),
            (int) $date->format('i'),
            (int) $date->format('s'),
            (int) $date->format('v') * 1000,
        );
    }
}
