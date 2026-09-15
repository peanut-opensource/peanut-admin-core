<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use DateTimeImmutable;
use DateTimeZone;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\FileMedia\Application\FileMediaException;
use PeanutAdmin\FileMedia\Application\FileObject;
use PeanutAdmin\FileMedia\Application\FileService;
use PeanutAdmin\FileMedia\Delivery\DeliveryPolicy;
use PeanutAdmin\FileMedia\Delivery\DeliveryRequest;
use PeanutAdmin\FileMedia\Delivery\DeliveryService;
use PeanutAdmin\FileMedia\Delivery\DeliveryVisibility;
use PeanutAdmin\FileMedia\Delivery\ReplayMode;
use PeanutAdmin\FileMedia\Delivery\SignedDeliveryTokenService;
use PeanutAdmin\FileMedia\Storage\ObjectStorageProvider;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Module\ModuleAvailabilityService;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use think\facade\Db;
use think\Request;
use think\Response;

final readonly class FileHttpService
{
    public function __construct(
        private FileService $files,
        private ObjectStorageProvider $storage,
        private AuditService $audit,
        private ModuleAvailabilityService $modules,
        private string $deliveryBaseUrl,
        private string $deliverySigningKey,
    ) {}

    public function list(Request $request): Response
    {
        self::emptyPayload($request);
        $query = $request->get();
        if (!is_array($query) || array_diff(array_keys($query), ['status', 'page', 'page_size']) !== []) {
            throw self::problem(FileMediaException::uploadInvalid('The file list query is invalid.'));
        }
        $status = $query['status'] ?? 'ready';
        if (!is_string($status) || !in_array($status, ['ready', 'archived'], true)) {
            throw self::problem(FileMediaException::uploadInvalid('The file status filter is invalid.'));
        }
        $result = $this->files->list(
            MemberAdminRuntime::context($request),
            $status,
            self::positiveInt($query['page'] ?? '1', 10000),
            self::positiveInt($query['page_size'] ?? '20', 100),
        );

        return self::json($request, [
            'data' => ['items' => array_map(static fn(FileObject $file): array => $file->toArray(), $result['items'])],
            'page' => $result['page'],
            'page_size' => $result['page_size'],
            'total' => $result['total'],
        ]);
    }

    public function detail(Request $request, string $fileKey): Response
    {
        self::noInput($request);
        $file = $this->files->detail(MemberAdminRuntime::context($request), $fileKey);

        return self::json($request, ['data' => $file->toArray()]);
    }

    public function upload(Request $request): Response
    {
        if (MemberAdminRuntime::body($request) !== [] || $request->get() !== []) {
            throw self::problem(FileMediaException::uploadInvalid('The file operation contains undeclared fields.'));
        }
        [$sourcePath, $originalName] = self::uploadedFile($request);
        $file = $this->files->upload(
            MemberAdminRuntime::context($request),
            $sourcePath,
            $originalName,
        );

        return self::json($request, ['data' => $file->toArray()], 201, '/api/v1/files/' . rawurlencode($file->fileKey));
    }

    public function archive(Request $request, string $fileKey): Response
    {
        self::noInput($request);
        $file = $this->files->archive(
            MemberAdminRuntime::context($request),
            $fileKey,
            MemberAdminRuntime::header($request, 'if-match'),
        );

        return self::json($request, ['data' => $file->toArray()]);
    }

    public function download(Request $request, string $fileKey): Response
    {
        self::noInput($request);
        $context = MemberAdminRuntime::context($request);
        $file = $this->files->detail($context, $fileKey);
        $stream = $this->files->content($context, $fileKey);
        $content = stream_get_contents($stream);
        fclose($stream);
        if (!is_string($content) || strlen($content) !== $file->sizeBytes) {
            throw self::problem(FileMediaException::storageUnavailable());
        }

        return Response::create($content, 'html', 200)->header([
            'Content-Type' => $file->mediaType,
            'Content-Length' => (string) $file->sizeBytes,
            'Content-Disposition' => self::contentDisposition($file->originalName),
            'X-Content-Type-Options' => 'nosniff',
            'X-Request-Id' => MemberAdminRuntime::requestId($request),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function assets(Request $request): Response
    {
        self::emptyPayload($request);
        $query = $request->get();
        if (!is_array($query) || array_diff(array_keys($query), ['page', 'page_size']) !== []) {
            throw self::problem(FileMediaException::deliveryInvalid());
        }
        $context = MemberAdminRuntime::context($request);
        $now = self::now();
        $result = $this->files->assets(
            $context,
            self::positiveInt($query['page'] ?? '1', 10000),
            self::positiveInt($query['page_size'] ?? '20', 100),
        );
        $items = [];
        foreach ($result['items'] as $item) {
            $file = $item['file'];
            $grant = $this->delivery(self::scope($context->tenantId, $context->requestId))->issue(
                new DeliveryRequest($context, $file, DeliveryVisibility::Private, ReplayMode::SingleUse, $now, 120, true),
            );
            $items[] = [
                'file_key' => $file->fileKey,
                'original_name' => $file->originalName,
                'media_type' => $item['media_type'],
                'width' => $item['width'],
                'height' => $item['height'],
                'preview_uri' => $grant->uri,
                'variants' => [],
            ];
        }

        return self::json($request, [
            'data' => ['items' => $items],
            'page' => $result['page'],
            'page_size' => $result['page_size'],
            'total' => $result['total'],
        ]);
    }

    public function grant(Request $request, string $fileKey): Response
    {
        self::noInput($request);
        $context = MemberAdminRuntime::context($request);
        $grant = Db::transaction(function () use ($context, $fileKey) {
            $this->modules->assertAvailable(
                self::scope($context->tenantId, $context->requestId),
                'peanut.file-media',
                self::now(),
                true,
            );
            $file = $this->files->detail($context, $fileKey);
            $grant = $this->delivery(self::scope($context->tenantId, $context->requestId))->issue(
                new DeliveryRequest($context, $file, DeliveryVisibility::Private, ReplayMode::SingleUse, self::now(), 120, true),
            );
            $this->audit->tenantMember(
                $context,
                'tenant.file.delivery.granted',
                'peanut.file-media.read',
                'file',
                $fileKey,
                $grant->auditMetadata(),
            );

            return $grant;
        });

        return self::json($request, ['data' => [
            'file_key' => $fileKey,
            'delivery_uri' => $grant->uri,
            'visibility' => $grant->visibility->value,
            'replay_mode' => $grant->replayMode->value,
            'expires_at' => $grant->expiresAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ]], 201);
    }

    public function deliver(Request $request, string $fileKey): Response
    {
        self::emptyPayload($request);
        $query = $request->get();
        if (!is_array($query) || array_keys($query) !== ['token'] || !is_string($query['token'])) {
            throw self::problem(FileMediaException::deliveryDenied());
        }
        $requestId = MemberAdminRuntime::requestId($request);
        $now = self::now();
        $tenantId = SignedDeliveryTokenService::peekTenantId($query['token'], $this->deliverySigningKey);
        [$file, $content] = Db::transaction(function () use ($query, $tenantId, $fileKey, $now, $requestId): array {
            $scope = self::scope($tenantId, $requestId);
            $this->modules->assertAvailable($scope, 'peanut.file-media', $now, true);
            $this->tokens($scope)->verifyAndConsume($query['token'], $tenantId, $fileKey, $now);
            $file = $this->files->downloadable($scope, $fileKey);
            $metadata = $this->storage->head($file->storageKey);
            if ($metadata->sizeBytes !== $file->sizeBytes || !hash_equals($metadata->sha256, $file->sha256)) {
                throw FileMediaException::storageUnavailable();
            }
            $stream = $this->storage->open($file->storageKey);
            $content = stream_get_contents($stream);
            fclose($stream);
            if (!is_string($content) || strlen($content) !== $file->sizeBytes) {
                throw FileMediaException::storageUnavailable();
            }
            $this->audit->tenantSystem(
                $tenantId,
                'tenant.file.delivered',
                'peanut.file-media.read',
                $requestId,
                ['adapter_key' => 'local-signed', 'visibility' => 'private', 'replay_mode' => 'single_use'],
            );

            return [$file, $content];
        });

        return Response::create($content, 'html', 200)->header([
            'Content-Type' => $file->mediaType,
            'Content-Length' => (string) $file->sizeBytes,
            'Content-Disposition' => 'inline; filename*=UTF-8\'\'' . rawurlencode($file->originalName),
            'X-Content-Type-Options' => 'nosniff',
            'X-Request-Id' => $requestId,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function delivery(TenantScope $scope): DeliveryService
    {
        if (!hash_equals('local-private', $this->storage->key())
            || preg_match('#^https://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$#D', $this->deliveryBaseUrl) !== 1) {
            throw FileMediaException::deliveryUnavailable();
        }

        return new DeliveryService(
            new LocalSignedDeliveryAdapter($this->deliveryBaseUrl, $this->tokens($scope)),
            new DeliveryPolicy(),
        );
    }

    private function tokens(TenantScope $scope): SignedDeliveryTokenService
    {
        if (strlen($this->deliverySigningKey) < 32) {
            throw FileMediaException::deliveryUnavailable();
        }

        return new SignedDeliveryTokenService(
            $this->deliverySigningKey,
            new ThinkPhpDeliveryReplayGuard($scope),
        );
    }

    private static function noInput(Request $request): void
    {
        self::emptyPayload($request);
        if ($request->get() !== []) {
            throw self::problem(FileMediaException::uploadInvalid('The file operation does not accept query parameters.'));
        }
    }

    private static function emptyPayload(Request $request): void
    {
        if (MemberAdminRuntime::body($request) !== []) {
            throw self::problem(FileMediaException::uploadInvalid('The file operation contains undeclared form fields.'));
        }
    }

    private static function positiveInt(mixed $value, int $maximum): int
    {
        $integer = is_int($value)
            ? $value
            : (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1
                ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false);
        if (!is_int($integer) || $integer > $maximum) {
            throw self::problem(FileMediaException::uploadInvalid('The file pagination input is invalid.'));
        }

        return $integer;
    }

    /** @return array{string, string} */
    private static function uploadedFile(Request $request): array
    {
        $files = $request->file();
        if (!is_array($files) || array_keys($files) !== ['file'] || !is_object($files['file'])) {
            throw self::problem(FileMediaException::uploadInvalid('Exactly one multipart file field named file is required.'));
        }
        $file = $files['file'];
        if (method_exists($file, 'getError') && (int) $file->getError() !== UPLOAD_ERR_OK) {
            throw self::problem(FileMediaException::uploadInvalid());
        }
        $path = method_exists($file, 'getPathname') ? $file->getPathname() : null;
        $name = match (true) {
            method_exists($file, 'getOriginalName') => $file->getOriginalName(),
            method_exists($file, 'getOriginalFilename') => $file->getOriginalFilename(),
            default => null,
        };
        if (!is_string($path) || !is_string($name)) {
            throw self::problem(FileMediaException::uploadInvalid());
        }

        return [$path, $name];
    }

    /** @param array<string,mixed> $body */
    private static function json(Request $request, array $body, int $status = 200, ?string $location = null): Response
    {
        $requestId = MemberAdminRuntime::requestId($request);
        $meta = ['request_id' => $requestId];
        foreach (['page', 'page_size', 'total'] as $key) {
            if (array_key_exists($key, $body)) {
                $meta[$key] = $body[$key];
                unset($body[$key]);
            }
        }
        $body['meta'] = $meta;
        $headers = ['Content-Type' => 'application/json', 'X-Request-Id' => $requestId, 'Cache-Control' => 'no-store'];
        $data = $body['data'] ?? null;
        if (is_array($data) && is_int($data['revision'] ?? null)) {
            $headers['ETag'] = '"rev-' . $data['revision'] . '"';
        }
        if ($location !== null) {
            $headers['Location'] = $location;
        }

        return Response::create($body, 'json', $status)->header($headers);
    }

    private static function contentDisposition(string $name): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'download';
        $fallback = trim(substr($fallback, 0, 120), '._-') ?: 'download';

        return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }

    private static function problem(FileMediaException $exception): ApiException
    {
        return new ApiException($exception->errorCode, $exception->httpStatus, $exception->getMessage());
    }

    private static function scope(int $tenantId, string $identity): TenantScope
    {
        return TenantScope::fromTrustedContext($tenantId, $identity);
    }

    private static function now(): DateTimeImmutable
    {
        $date = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $date->setTime(
            (int) $date->format('H'),
            (int) $date->format('i'),
            (int) $date->format('s'),
            (int) $date->format('v') * 1000,
        );
    }
}
