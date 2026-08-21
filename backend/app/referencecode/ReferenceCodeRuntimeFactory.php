<?php

declare(strict_types=1);

namespace PeanutAdmin\App\referencecode;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDO;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
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
use PeanutAdmin\Kernel\Module\ManifestDocument;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\ReferenceCodes\Application\EffectiveReferenceCode;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeAdminService;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeException;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeQuery;
use PeanutAdmin\ReferenceCodes\Definition\ReferenceCodeSetDefinition;
use PeanutAdmin\ReferenceCodes\Definition\ReferenceCodeSetLoader;
use PeanutAdmin\ReferenceCodes\Definition\ReferenceCodeSetRegistry;
use PeanutAdmin\ReferenceCodes\Persistence\PdoReferenceCodeRepository;
use think\Request;
use think\Response;

final class ReferenceCodeRuntimeFactory
{
    /** @return array<string, ExternalOperationDefinition> */
    public static function operations(): array
    {
        return [
            'listReferenceCodeSets' => self::operation(
                'listReferenceCodeSets',
                'GET',
                '/api/v1/reference-code-sets',
                'peanut.reference-codes.read',
            ),
            'listReferenceCodes' => self::operation(
                'listReferenceCodes',
                'GET',
                '/api/v1/reference-code-sets/{module_key}/{set_key}/codes',
                'peanut.reference-codes.read',
            ),
            'getReferenceCode' => self::operation(
                'getReferenceCode',
                'GET',
                '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
                'peanut.reference-codes.read',
            ),
            'createReferenceCode' => self::operation(
                'createReferenceCode',
                'POST',
                '/api/v1/reference-code-sets/{module_key}/{set_key}/codes',
                'peanut.reference-codes.manage',
                true,
            ),
            'replaceReferenceCode' => self::operation(
                'replaceReferenceCode',
                'PUT',
                '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
                'peanut.reference-codes.manage',
                true,
            ),
            'retireReferenceCode' => self::operation(
                'retireReferenceCode',
                'DELETE',
                '/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}',
                'peanut.reference-codes.manage',
                true,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return ($create is true ? array{
     *   code: string, label: string, metadata: array<array-key, mixed>, status: string,
     *   sortOrder: int, effectiveAt: DateTimeImmutable, expiresAt: DateTimeImmutable|null
     * } : array{
     *   label: string, metadata: array<array-key, mixed>, status: string,
     *   sortOrder: int, effectiveAt: DateTimeImmutable, expiresAt: DateTimeImmutable|null
     * })
     */
    public static function versionInput(array $body, bool $create): array
    {
        $fields = ['label', 'metadata', 'status', 'sort_order', 'effective_at', 'expires_at'];
        if ($create) {
            array_unshift($fields, 'code');
        }
        if (array_diff(array_keys($body), $fields) !== [] || array_diff($fields, array_keys($body)) !== []) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code request has missing or unknown fields.',
            );
        }
        $code = $body['code'] ?? null;
        $label = $body['label'] ?? null;
        $metadata = $body['metadata'] ?? null;
        $status = $body['status'] ?? null;
        $sortOrder = $body['sort_order'] ?? null;
        if (($create && !is_string($code))
            || !is_string($label)
            || !is_array($metadata)
            || !is_string($status)
            || !is_int($sortOrder)) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code request field types are invalid.',
            );
        }
        $effectiveAt = self::date($body['effective_at'] ?? null, 'effective_at', false);
        $expiresAt = self::date($body['expires_at'] ?? null, 'expires_at', true);
        if (!$effectiveAt instanceof DateTimeImmutable) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_INTERVAL_INVALID',
                'The effective instant is required.',
            );
        }

        $version = [
            'label' => $label,
            'metadata' => $metadata,
            'status' => $status,
            'sortOrder' => $sortOrder,
            'effectiveAt' => $effectiveAt,
            'expiresAt' => $expiresAt,
        ];
        if (!$create) {
            return $version;
        }

        return ['code' => $code, ...$version];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{
     *   asOf: DateTimeImmutable|null,
     *   effectiveStatus: string,
     *   includeRetired: bool,
     *   page: int,
     *   pageSize: int
     * }
     */
    public static function listQuery(array $query): array
    {
        $fields = ['as_of', 'effective_status', 'include_retired', 'page', 'page_size'];
        if (array_diff(array_keys($query), $fields) !== []) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code query contains unknown parameters.',
            );
        }
        $asOf = array_key_exists('as_of', $query)
            ? self::date($query['as_of'], 'as_of', false)
            : null;
        $effectiveStatus = $query['effective_status'] ?? 'all';
        $includeRetired = $query['include_retired'] ?? 'false';
        $page = $query['page'] ?? '1';
        $pageSize = $query['page_size'] ?? '50';
        if (!is_string($effectiveStatus)
            || !in_array($effectiveStatus, ['active', 'inactive', 'all'], true)
            || !is_string($includeRetired)
            || !in_array($includeRetired, ['true', 'false'], true)) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code query is invalid.',
            );
        }

        return [
            'asOf' => $asOf,
            'effectiveStatus' => $effectiveStatus,
            'includeRetired' => $includeRetired === 'true',
            'page' => self::positiveQueryInteger($page, 10000),
            'pageSize' => self::positiveQueryInteger($pageSize, 100),
        ];
    }

    /** @param array<string, mixed> $query */
    public static function detailQuery(array $query): ?DateTimeImmutable
    {
        if (array_diff(array_keys($query), ['as_of']) !== []) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code detail query contains unknown parameters.',
            );
        }

        return array_key_exists('as_of', $query)
            ? self::date($query['as_of'], 'as_of', false)
            : null;
    }

    /** @param array<string, mixed> $body */
    public static function assertEmptyBody(array $body): void
    {
        if ($body !== []) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code request body must be empty.',
            );
        }
    }

    /** @return array<string, mixed> */
    public static function item(EffectiveReferenceCode $entry): array
    {
        return $entry->toArray();
    }

    public static function location(EffectiveReferenceCode $entry): string
    {
        return '/api/v1/reference-code-sets/'
            . rawurlencode($entry->moduleKey)
            . '/' . rawurlencode($entry->setKey)
            . '/codes/' . rawurlencode($entry->code);
    }

    public static function listSets(
        Request $request,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
    ): Response {
        $pdo ??= self::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['listReferenceCodeSets'];
        $externalRequest = self::externalRequest($request, $operation, '/api/v1/reference-code-sets');
        $response = self::host($pdo, $modules)->read(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $query,
            ) use ($pdo, $modules): ExternalOperationResponse {
                try {
                    $context = self::tenantContext($authorized);
                    self::assertEmptyBody(self::requestPayload($query));
                    self::assertNoQuery(self::requestQuery($query));
                    $visible = self::visibleDefinitionRegistry(
                        $pdo,
                        $modules,
                        $context,
                        $query->comparisonTime,
                    );

                    return new ExternalOperationResponse(200, [
                        'data' => ['items' => self::query($pdo)->sets($visible)],
                    ]);
                } catch (ReferenceCodeException $exception) {
                    throw self::apiException($exception);
                }
            },
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function listCodes(
        Request $request,
        string $moduleKey,
        string $setKey,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
    ): Response {
        $pdo ??= self::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['listReferenceCodes'];
        $path = self::collectionPath($moduleKey, $setKey);
        $externalRequest = self::externalRequest($request, $operation, $path);
        $response = self::host($pdo, $modules)->read(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $query,
            ) use ($pdo, $modules, $moduleKey, $setKey): ExternalOperationResponse {
                try {
                    $context = self::tenantContext($authorized);
                    self::assertEmptyBody(self::requestPayload($query));
                    $input = self::listQuery(self::requestQuery($query));
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $setKey);
                    self::assertOwnerAvailable($pdo, $definition, $context, $query->comparisonTime);
                    $result = self::query($pdo)->list(
                        $definition,
                        $context,
                        $input['asOf'],
                        $input['effectiveStatus'],
                        $input['includeRetired'],
                        $input['page'],
                        $input['pageSize'],
                    );

                    return new ExternalOperationResponse(200, [
                        'data' => [
                            'items' => array_map(self::item(...), $result['items']),
                            'as_of' => $result['as_of'],
                            'page' => $result['page'],
                            'page_size' => $result['page_size'],
                            'total' => $result['total'],
                        ],
                    ]);
                } catch (ReferenceCodeException $exception) {
                    throw self::apiException($exception);
                }
            },
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function getCode(
        Request $request,
        string $moduleKey,
        string $setKey,
        string $code,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
    ): Response {
        $pdo ??= self::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['getReferenceCode'];
        $path = self::detailPath($moduleKey, $setKey, $code);
        $externalRequest = self::externalRequest($request, $operation, $path);
        $response = self::host($pdo, $modules)->read(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $query,
            ) use ($pdo, $modules, $moduleKey, $setKey, $code): ExternalOperationResponse {
                try {
                    $context = self::tenantContext($authorized);
                    self::assertEmptyBody(self::requestPayload($query));
                    $asOf = self::detailQuery(self::requestQuery($query));
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $setKey);
                    self::assertOwnerAvailable($pdo, $definition, $context, $query->comparisonTime);
                    $entry = self::query($pdo)->get($definition, $context, $code, $asOf);

                    return new ExternalOperationResponse(200, ['data' => self::item($entry)]);
                } catch (ReferenceCodeException $exception) {
                    throw self::apiException($exception);
                }
            },
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function createCode(
        Request $request,
        string $moduleKey,
        string $setKey,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
    ): Response {
        $pdo ??= self::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['createReferenceCode'];
        $path = self::collectionPath($moduleKey, $setKey);
        $externalRequest = self::externalRequest($request, $operation, $path);
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($modules, $moduleKey, $setKey): ExternalOperationResult {
                try {
                    $context = self::tenantContext($authorized);
                    self::assertNoQuery(self::requestQuery($command));
                    $input = self::versionInput(self::requestPayload($command), true);
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $setKey);
                    self::assertOwnerAvailable($transaction, $definition, $context, $command->comparisonTime);
                    $entry = self::admin($transaction)->create(
                        $definition,
                        $context,
                        $input['code'],
                        $input['label'],
                        $input['metadata'],
                        $input['status'],
                        $input['sortOrder'],
                        $input['effectiveAt'],
                        $input['expiresAt'],
                        self::createPrecondition($command),
                    );

                    return self::result(
                        201,
                        $entry,
                        'reference-code.created',
                        'peanut.reference-codes.create',
                        ['effective_at', 'expires_at', 'label', 'metadata', 'sort_order', 'status'],
                    );
                } catch (ReferenceCodeException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard($modules, $moduleKey, $setKey),
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function replaceCode(
        Request $request,
        string $moduleKey,
        string $setKey,
        string $code,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
    ): Response {
        $pdo ??= self::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['replaceReferenceCode'];
        $path = self::detailPath($moduleKey, $setKey, $code);
        $externalRequest = self::externalRequest($request, $operation, $path);
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($modules, $moduleKey, $setKey, $code): ExternalOperationResult {
                try {
                    $context = self::tenantContext($authorized);
                    self::assertNoQuery(self::requestQuery($command));
                    $input = self::versionInput(self::requestPayload($command), false);
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $setKey);
                    self::assertOwnerAvailable($transaction, $definition, $context, $command->comparisonTime);
                    $entry = self::admin($transaction)->replace(
                        $definition,
                        $context,
                        $code,
                        $input['label'],
                        $input['metadata'],
                        $input['status'],
                        $input['sortOrder'],
                        $input['effectiveAt'],
                        $input['expiresAt'],
                        self::requiredIfMatch($command),
                    );

                    return self::result(
                        200,
                        $entry,
                        'reference-code.changed',
                        'peanut.reference-codes.replace',
                        ['effective_at', 'expires_at', 'label', 'metadata', 'sort_order', 'status'],
                    );
                } catch (ReferenceCodeException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard($modules, $moduleKey, $setKey),
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    public static function retireCode(
        Request $request,
        string $moduleKey,
        string $setKey,
        string $code,
        ?PDO $pdo = null,
        ?CompiledModuleRegistry $modules = null,
    ): Response {
        $pdo ??= self::pdo();
        $modules ??= RuntimeModuleRegistry::compile();
        $operation = self::operations()['retireReferenceCode'];
        $path = self::detailPath($moduleKey, $setKey, $code);
        $externalRequest = self::externalRequest($request, $operation, $path);
        $response = self::host($pdo, $modules)->command(
            $operation,
            $externalRequest,
            static function (
                AuthorizedExternalOperation $authorized,
                ExternalOperationRequest $command,
                PDO $transaction,
            ) use ($modules, $moduleKey, $setKey, $code): ExternalOperationResult {
                try {
                    $context = self::tenantContext($authorized);
                    self::assertNoQuery(self::requestQuery($command));
                    self::assertEmptyBody(self::requestPayload($command));
                    $definition = self::definitionRegistry($modules)->require($moduleKey, $setKey);
                    self::assertOwnerAvailable($transaction, $definition, $context, $command->comparisonTime);
                    $entry = self::admin($transaction)->retire(
                        $definition,
                        $context,
                        $code,
                        self::requiredIfMatch($command),
                    );

                    return self::result(
                        200,
                        $entry,
                        'reference-code.retired',
                        'peanut.reference-codes.retire',
                        ['effective_at', 'status'],
                    );
                } catch (ReferenceCodeException $exception) {
                    throw self::apiException($exception);
                }
            },
            guard: self::commandGuard($modules, $moduleKey, $setKey),
        );

        return self::httpResponse($response, $externalRequest->requestId->value);
    }

    /** @return array{inserted: int, updated: int, retired: int, reactivated: int} */
    public static function synchronizeDefinitions(
        PDO $pdo,
        CompiledModuleRegistry $modules,
        DateTimeImmutable $now,
    ): array {
        return (new PdoReferenceCodeRepository($pdo))->synchronize(
            self::definitionRegistry($modules),
            self::millisecond($now),
        );
    }

    public static function definitionRegistry(CompiledModuleRegistry $modules): ReferenceCodeSetRegistry
    {
        $registry = new ReferenceCodeSetRegistry();
        $loader = new ReferenceCodeSetLoader();
        foreach ($modules->modules as $module) {
            $moduleKey = self::moduleKey($module);
            $relativePath = self::definitionPath($module);
            $definitions = $relativePath === null
                ? []
                : $loader->load($moduleKey, self::ownedResourcePath($module, $relativePath));
            $registry->registerModule($moduleKey, $definitions);
        }

        return $registry;
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
                throw new LogicException('Reference-code operations do not accept data-query authorization.');
            },
            static function (): never {
                throw new LogicException('Reference-code operations do not accept typed targets.');
            },
        );

        return new ExternalOperationHost(
            $configuration,
            new TrustedContextAdapter($configuration),
            new ModuleAvailabilityAdapter(
                $modules,
                new ModuleGuard(new PdoModuleRuntimeRepository($pdo)),
            ),
            new PermissionAdapter($permissions),
            new TypedTargetAdapter($unusedDataAuthorization),
            new AtomicOperationAdapter($pdo),
            new ProblemDetailsAdapter(),
        );
    }

    public static function hostConfiguration(): ExternalHostConfiguration
    {
        $root = dirname(__DIR__, 3);
        /** @var array{roots: list<string>} $moduleConfig */
        $moduleConfig = require $root . '/backend/config/modules.php';
        /** @var array{tenant: array{clients: non-empty-list<string>}} $authConfig */
        $authConfig = require $root . '/backend/config/auth.php';

        return new ExternalHostConfiguration(
            new ModuleHostLayout(
                'backend/app/Modules',
                'PeanutAdmin\\App\\Modules',
                'frontend/src/modules',
            ),
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

    public static function httpResponse(ExternalOperationResponse $response, string $requestId): Response
    {
        $body = $response->body;
        if ($response->status >= 200 && $response->status <= 299) {
            if (is_array($body['data'] ?? null) && self::entryIdentity($body['data'])) {
                $body['data'] = self::orderedEntryData($body['data']);
            }
            $body['meta'] = ['request_id' => $requestId];
        }
        $headers = [
            'Content-Type' => $response->contentType,
            'X-Request-Id' => $requestId,
            'Cache-Control' => 'no-store',
            ...$response->headers,
        ];
        $etag = $body['data']['etag'] ?? null;
        if (is_string($etag)) {
            $headers['ETag'] = $etag;
        }
        if ($response->status === 201 && is_array($body['data'] ?? null)) {
            $data = $body['data'];
            if (self::entryIdentity($data)) {
                $headers['Location'] = '/api/v1/reference-code-sets/'
                    . rawurlencode($data['module_key'])
                    . '/' . rawurlencode($data['set_key'])
                    . '/codes/' . rawurlencode($data['code']);
            }
        }

        return Response::create($body, 'json', $response->status)->header($headers);
    }

    /** @return callable(AuthorizedExternalOperation, ExternalOperationRequest, PDO): void */
    private static function commandGuard(
        CompiledModuleRegistry $modules,
        string $moduleKey,
        string $setKey,
    ): callable {
        return static function (
            AuthorizedExternalOperation $authorized,
            ExternalOperationRequest $request,
            PDO $transaction,
        ) use ($modules, $moduleKey, $setKey): void {
            try {
                $context = self::tenantContext($authorized);
                $definition = self::definitionRegistry($modules)->require($moduleKey, $setKey);
                (new PdoReferenceCodeRepository($transaction))->assertCurrentDefinition($definition, true);
                self::assertModuleAvailable(
                    $transaction,
                    'peanut.reference-codes',
                    $context,
                    $request->comparisonTime,
                    true,
                );
                self::assertOwnerAvailable(
                    $transaction,
                    $definition,
                    $context,
                    $request->comparisonTime,
                    true,
                );
            } catch (ReferenceCodeException $exception) {
                throw self::apiException($exception);
            }
        };
    }

    private static function assertOwnerAvailable(
        PDO $pdo,
        ReferenceCodeSetDefinition $definition,
        TenantContext $context,
        DateTimeImmutable $comparisonTime,
        bool $lockAvailabilityReads = false,
    ): void {
        try {
            self::assertModuleAvailable(
                $pdo,
                $definition->moduleKey,
                $context,
                $comparisonTime,
                $lockAvailabilityReads,
            );
        } catch (ModuleException) {
            throw ReferenceCodeException::setNotFound();
        }
    }

    private static function assertModuleAvailable(
        PDO $pdo,
        string $moduleKey,
        TenantContext $context,
        DateTimeImmutable $comparisonTime,
        bool $lockAvailabilityReads = false,
    ): void {
        if ($lockAvailabilityReads) {
            self::lockModuleAvailability($pdo, $context->tenantId, $moduleKey);
        }
        $guard = new ModuleGuard(new PdoModuleRuntimeRepository($pdo));
        $guard->assertDeployment($moduleKey);
        $guard->assertTenant($context->tenantId, $moduleKey, $comparisonTime);
    }

    private static function lockModuleAvailability(PDO $pdo, int $tenantId, string $moduleKey): void
    {
        if (!$pdo->inTransaction()) {
            throw new LogicException('Module availability locks require the active command transaction.');
        }
        $installation = $pdo->prepare(<<<'SQL'
SELECT module_key
FROM pa_module_installation WHERE module_key = :module_key
FOR SHARE
SQL);
        $installation->execute(['module_key' => $moduleKey]);
        $installation->fetch(PDO::FETCH_ASSOC);

        $tenantModule = $pdo->prepare(<<<'SQL'
SELECT tenant_id, module_key
FROM pa_tenant_module WHERE tenant_id = :tenant_id AND module_key = :module_key
FOR SHARE
SQL);
        $tenantModule->execute(['tenant_id' => $tenantId, 'module_key' => $moduleKey]);
        $tenantModule->fetch(PDO::FETCH_ASSOC);
    }

    private static function visibleDefinitionRegistry(
        PDO $pdo,
        CompiledModuleRegistry $modules,
        TenantContext $context,
        DateTimeImmutable $comparisonTime,
    ): ReferenceCodeSetRegistry {
        $visible = new ReferenceCodeSetRegistry();
        $declared = self::definitionRegistry($modules);
        $grouped = [];
        foreach ($declared->all() as $definition) {
            $grouped[$definition->moduleKey][] = $definition;
        }
        foreach ($declared->moduleKeys() as $moduleKey) {
            $definitions = $grouped[$moduleKey] ?? [];
            if ($definitions !== [] && !self::ownerAvailable($pdo, $definitions[0], $context, $comparisonTime)) {
                continue;
            }
            $visible->registerModule($moduleKey, $definitions);
        }

        return $visible;
    }

    private static function ownerAvailable(
        PDO $pdo,
        ReferenceCodeSetDefinition $definition,
        TenantContext $context,
        DateTimeImmutable $comparisonTime,
    ): bool {
        try {
            self::assertOwnerAvailable($pdo, $definition, $context, $comparisonTime);

            return true;
        } catch (ReferenceCodeException $exception) {
            if ($exception->httpStatus === 404) {
                return false;
            }
            throw $exception;
        }
    }

    /** @param list<string> $changedFields */
    private static function result(
        int $status,
        EffectiveReferenceCode $entry,
        string $eventType,
        string $action,
        array $changedFields,
    ): ExternalOperationResult {
        return new ExternalOperationResult(
            $status,
            ['data' => self::item($entry)],
            $eventType,
            $action,
            self::auditMetadata($entry, $changedFields),
            'peanut.reference-code',
            $entry->moduleKey . '/' . $entry->setKey . '/' . $entry->code,
        );
    }

    /** @param list<string> $changedFields
     * @return array<string, bool|int|string|null>
     */
    private static function auditMetadata(EffectiveReferenceCode $entry, array $changedFields): array
    {
        return $entry->auditMetadata($changedFields);
    }

    private static function tenantContext(AuthorizedExternalOperation $authorized): TenantContext
    {
        if (!$authorized->context instanceof TenantContext) {
            throw ReferenceCodeException::codeNotFound();
        }

        return $authorized->context;
    }

    private static function admin(PDO $pdo): ReferenceCodeAdminService
    {
        return new ReferenceCodeAdminService(new PdoReferenceCodeRepository($pdo));
    }

    private static function query(PDO $pdo): ReferenceCodeQuery
    {
        return new ReferenceCodeQuery(new PdoReferenceCodeRepository($pdo));
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
            'peanut.reference-codes',
            new PermissionRequirement('tenant', [$permission]),
            atomicCommand: $command,
            idempotencyRequired: $command,
        );
    }

    private static function pdo(): PDO
    {
        return MemberAdminRuntime::pdo();
    }

    private static function externalRequest(
        Request $request,
        ExternalOperationDefinition $operation,
        string $path,
    ): ExternalOperationRequest {
        $route = $request->route();
        $context = is_array($route) ? ($route['tenant_context'] ?? null) : null;
        $requestId = $context instanceof TenantContext
            ? $context->requestId
            : MemberAdminRuntime::requestId($request);
        $comparisonTime = self::millisecond(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        return new ExternalOperationRequest(
            RequestId::fromHeader($requestId),
            $context,
            $operation->method,
            $path,
            [
                'payload' => MemberAdminRuntime::body($request),
                'query' => self::queryParameters($request),
                'if_match' => MemberAdminRuntime::header($request, 'if-match'),
                'if_none_match' => MemberAdminRuntime::header($request, 'if-none-match'),
            ],
            [],
            MemberAdminRuntime::header($request, 'idempotency-key'),
            $comparisonTime,
            $comparisonTime->modify('+24 hours'),
        );
    }

    /** @return array<string, mixed> */
    private static function requestPayload(ExternalOperationRequest $request): array
    {
        $payload = $request->body['payload'] ?? null;
        if (!is_array($payload)) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code request body is invalid.',
            );
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private static function requestQuery(ExternalOperationRequest $request): array
    {
        $query = $request->body['query'] ?? null;
        if (!is_array($query)) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code query is invalid.',
            );
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private static function queryParameters(Request $request): array
    {
        $query = $request->get();

        return is_array($query) ? $query : [];
    }

    /** @param array<string, mixed> $query */
    private static function assertNoQuery(array $query): void
    {
        if ($query !== []) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code operation does not accept query parameters.',
            );
        }
    }

    private static function createPrecondition(ExternalOperationRequest $request): ?string
    {
        if (self::precondition($request, 'if_match') !== null) {
            throw ReferenceCodeException::preconditionRequired();
        }

        return self::precondition($request, 'if_none_match');
    }

    private static function requiredIfMatch(ExternalOperationRequest $request): ?string
    {
        if (self::precondition($request, 'if_none_match') !== null) {
            throw ReferenceCodeException::preconditionRequired();
        }

        return self::precondition($request, 'if_match');
    }

    private static function precondition(ExternalOperationRequest $request, string $key): ?string
    {
        $value = $request->body[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private static function moduleKey(ManifestDocument $module): string
    {
        $moduleKey = $module->data['key'] ?? null;
        if (!is_string($moduleKey) || $moduleKey === '') {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Reference-code set owner is invalid.');
        }

        return $moduleKey;
    }

    private static function definitionPath(ManifestDocument $module): ?string
    {
        $backend = $module->data['backend'] ?? null;
        if (!is_array($backend)) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Reference-code backend metadata is invalid.');
        }
        $relativePath = $backend['reference_code_sets'] ?? null;
        if ($relativePath === null) {
            return null;
        }
        if (!is_string($relativePath) || $relativePath === '') {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Reference-code set path is invalid.');
        }

        return $relativePath;
    }

    private static function ownedResourcePath(ManifestDocument $module, string $relativePath): string
    {
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new ModuleException('MODULE_MANIFEST_INVALID', 'Reference-code set path is unsafe.');
        }
        $path = realpath($module->root . '/' . $relativePath);
        if ($path === false || !is_file($path) || !str_starts_with($path, $module->root . DIRECTORY_SEPARATOR)) {
            throw new ModuleException(
                'MODULE_MANIFEST_INVALID',
                'Reference-code set resource is outside its declaring Module.',
            );
        }

        return $path;
    }

    private static function apiException(ReferenceCodeException $exception): ApiException
    {
        $detail = match ($exception->errorCode) {
            'REFERENCE_CODE_SET_NOT_FOUND' => 'The requested reference-code set is unavailable.',
            'REFERENCE_CODE_NOT_FOUND' => 'The requested reference code is unavailable.',
            'REFERENCE_CODE_RETIRED' => 'The reference-code identity is permanently retired.',
            'REFERENCE_CODE_ALREADY_EXISTS' => 'The reference-code identity already exists.',
            'REFERENCE_CODE_REVISION_MISMATCH' => 'The reference-code revision has changed.',
            'REFERENCE_CODE_METADATA_INVALID' => 'The reference-code metadata is invalid.',
            'REFERENCE_CODE_INTERVAL_INVALID' => 'The reference-code effective interval is invalid.',
            'REFERENCE_CODE_REQUEST_INVALID' => 'The reference-code request is invalid.',
            'PRECONDITION_REQUIRED' => 'A strong reference-code precondition is required.',
            'INTERNAL_ERROR' => 'The reference-code request could not be completed.',
            default => 'The reference-code request could not be completed.',
        };

        return new ApiException($exception->errorCode, $exception->httpStatus, $detail);
    }

    private static function positiveQueryInteger(mixed $value, int $maximum): int
    {
        if (!is_string($value)
            || preg_match('/^[1-9][0-9]*$/D', $value) !== 1
            || strlen($value) > strlen((string) $maximum)
            || (strlen($value) === strlen((string) $maximum) && strcmp($value, (string) $maximum) > 0)) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_REQUEST_INVALID',
                'The reference-code pagination query is invalid.',
            );
        }

        return (int) $value;
    }

    private static function date(mixed $value, string $field, bool $nullable): ?DateTimeImmutable
    {
        if ($nullable && $value === null) {
            return null;
        }
        if (!is_string($value)
            || preg_match(
                '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})$/D',
                $value,
                $matches,
            ) !== 1) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_INTERVAL_INVALID',
                "{$field} must be an RFC 3339 instant.",
            );
        }
        $fraction = str_pad((string) $matches[2], 6, '0');
        if (((int) $fraction) % 1000 !== 0) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_INTERVAL_INVALID',
                "{$field} must use exact millisecond precision.",
            );
        }
        $canonical = $matches[1]
            . '.' . $fraction
            . ($matches[3] === 'Z' ? '+00:00' : $matches[3]);
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s.uP',
            $canonical,
            new DateTimeZone('UTC'),
        );
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d\TH:i:s.uP') !== $canonical
            || (int) $date->format('Y') < 1000) {
            throw ReferenceCodeException::invalid(
                'REFERENCE_CODE_INTERVAL_INVALID',
                "{$field} must be representable as DATETIME(3).",
            );
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private static function millisecond(DateTimeImmutable $date): DateTimeImmutable
    {
        $utc = $date->setTimezone(new DateTimeZone('UTC'));

        return $utc->setTime(
            (int) $utc->format('H'),
            (int) $utc->format('i'),
            (int) $utc->format('s'),
            (int) $utc->format('v') * 1000,
        );
    }

    /** @param array<string, mixed> $data */
    private static function entryIdentity(array $data): bool
    {
        return is_string($data['module_key'] ?? null)
            && is_string($data['set_key'] ?? null)
            && is_string($data['code'] ?? null);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function orderedEntryData(array $data): array
    {
        $effective = $data['effective'] ?? null;

        return [
            'module_key' => $data['module_key'],
            'set_key' => $data['set_key'],
            'code' => $data['code'],
            'lifecycle' => $data['lifecycle'] ?? null,
            'revision' => $data['revision'] ?? null,
            'etag' => $data['etag'] ?? null,
            'effective' => is_array($effective) ? self::orderedEffectiveData($effective) : null,
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
            'retired_at' => $data['retired_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function orderedEffectiveData(array $data): array
    {
        return [
            'revision' => $data['revision'] ?? null,
            'label' => $data['label'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'status' => $data['status'] ?? null,
            'sort_order' => $data['sort_order'] ?? null,
            'effective_at' => $data['effective_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    private static function collectionPath(string $moduleKey, string $setKey): string
    {
        return '/api/v1/reference-code-sets/'
            . rawurlencode($moduleKey)
            . '/' . rawurlencode($setKey)
            . '/codes';
    }

    private static function detailPath(string $moduleKey, string $setKey, string $code): string
    {
        return self::collectionPath($moduleKey, $setKey) . '/' . rawurlencode($code);
    }
}
