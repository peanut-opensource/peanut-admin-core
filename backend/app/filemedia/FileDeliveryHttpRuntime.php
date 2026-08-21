<?php

declare(strict_types=1);

namespace PeanutAdmin\App\filemedia;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\http\TenantModuleRuntime;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\FileMedia\Application\FileMediaException;
use PeanutAdmin\FileMedia\Application\FileObject;
use PeanutAdmin\FileMedia\Delivery\DeliveryPolicy;
use PeanutAdmin\FileMedia\Delivery\DeliveryRequest;
use PeanutAdmin\FileMedia\Delivery\DeliveryService;
use PeanutAdmin\FileMedia\Delivery\DeliveryVisibility;
use PeanutAdmin\FileMedia\Delivery\ReplayMode;
use PeanutAdmin\FileMedia\Delivery\SignedDeliveryTokenService;
use PeanutAdmin\FileMedia\Persistence\PdoFileRepository;
use PeanutAdmin\FileMedia\Storage\PrivateStorageAdapter;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Api\RequestId;
use PeanutAdmin\Kernel\Host\ExternalOperationResponse;
use PeanutAdmin\Kernel\Host\ProblemDetailsAdapter;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use think\Request;
use think\Response;
use Throwable;

final class FileDeliveryHttpRuntime
{
    public static function assets(Request $request): Response
    {
        $pdo = MemberAdminRuntime::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $op = TenantModuleRuntime::operation('listFileAssets', 'GET', '/api/v1/file-assets', 'peanut.file-media', 'peanut.file-media.read');
        $external = TenantModuleRuntime::request($request, $op, '/api/v1/file-assets');
        $response = TenantModuleRuntime::host($pdo, $modules)->read($op, $external, static function ($authorized, $query) use ($pdo) {
            try {
                self::emptyBody($query->body['payload'] ?? null);
                $raw = $query->body['query'] ?? null;
                if (!is_array($raw) || array_diff(array_keys($raw), ['page','page_size']) !== []) {
                    throw FileMediaException::deliveryInvalid();
                }$context = TenantModuleRuntime::context($authorized);
                $result = (new FileDeliveryRepository($pdo))->assets($context->tenantId, TenantModuleRuntime::positiveInt($raw['page'] ?? '1', 10000), TenantModuleRuntime::positiveInt($raw['page_size'] ?? '20', 100));
                $items = [];
                foreach ($result['items'] as $item) {
                    $file = (new PdoFileRepository($pdo))->get($context->tenantId, (string) $item['file_key']);
                    $grant = self::service($pdo, $context->tenantId)->issue(new DeliveryRequest($context, $file, DeliveryVisibility::Private, ReplayMode::SingleUse, $query->comparisonTime, 120, true));
                    $items[] = ['file_key' => $file->fileKey,'original_name' => $file->originalName,'media_type' => $item['media_type'],'width' => (int) $item['width'],'height' => (int) $item['height'],'preview_uri' => $grant->uri,'variants' => []];
                }return new ExternalOperationResponse(200, ['data' => ['items' => $items],'page' => $result['page'],'page_size' => $result['page_size'],'total' => $result['total']]);
            } catch (FileMediaException $e) {
                throw self::problem($e);
            }
        });
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    public static function grant(Request $request, string $fileKey): Response
    {
        $pdo = MemberAdminRuntime::pdo();
        $modules = RuntimeModuleRegistry::compile();
        $path = '/api/v1/files/' . rawurlencode($fileKey) . '/delivery-grants';
        $op = TenantModuleRuntime::operation('createFileDeliveryGrant', 'POST', '/api/v1/files/{file_key}/delivery-grants', 'peanut.file-media', 'peanut.file-media.read', true);
        $external = TenantModuleRuntime::request($request, $op, $path);
        $response = TenantModuleRuntime::host($pdo, $modules)->command($op, $external, static function ($authorized, $command, PDO $transaction) use ($fileKey) {
            try {
                self::noInput($command->body);
                $context = TenantModuleRuntime::context($authorized);
                $file = (new PdoFileRepository($transaction))->get($context->tenantId, $fileKey);
                $grant = self::service($transaction, $context->tenantId)->issue(new DeliveryRequest($context, $file, DeliveryVisibility::Private, ReplayMode::SingleUse, $command->comparisonTime, 120, true));
                return new \PeanutAdmin\Kernel\Host\ExternalOperationResult(201, ['data' => ['file_key' => $fileKey,'delivery_uri' => $grant->uri,'visibility' => $grant->visibility->value,'replay_mode' => $grant->replayMode->value,'expires_at' => $grant->expiresAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')]], 'tenant.file.delivery.granted', 'peanut.file-media.read', $grant->auditMetadata(), 'file', $fileKey);
            } catch (FileMediaException $e) {
                throw self::problem($e);
            }
        }, guard: TenantModuleRuntime::commandGuard('peanut.file-media'));
        return TenantModuleRuntime::response($response, $external->requestId->value);
    }

    public static function deliver(Request $request, string $fileKey): Response
    {
        $pdo = MemberAdminRuntime::pdo();
        $requestId = MemberAdminRuntime::requestId($request);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        try {
            self::emptyBody(MemberAdminRuntime::body($request));
            $query = $request->get();
            if (!is_array($query) || array_keys($query) !== ['token'] || !is_string($query['token'])) {
                throw FileMediaException::deliveryDenied();
            }
            $config = self::config();
            $tenantId = SignedDeliveryTokenService::peekTenantId($query['token'], $config['delivery_signing_key']);
            $pdo->beginTransaction();
            $registry = RuntimeModuleRegistry::compile();
            if (!in_array('peanut.file-media', $registry->moduleKeys(), true)) {
                throw new ModuleException('MODULE_NOT_INSTALLED', 'The Module is not registered by this host.');
            }
            $guard = new ModuleGuard(new PdoModuleRuntimeRepository($pdo, true));
            $guard->assertDeployment('peanut.file-media');
            $guard->assertTenant($tenantId, 'peanut.file-media', $now);
            self::tokens($pdo, $tenantId)->verifyAndConsume($query['token'], $tenantId, $fileKey, $now);
            $file = (new PdoFileRepository($pdo))->getForDownload($tenantId, $fileKey);
            $storage = self::storage();
            $metadata = $storage->head($file->storageKey);
            if ($metadata->sizeBytes !== $file->sizeBytes || !hash_equals($metadata->sha256, $file->sha256)) {
                throw FileMediaException::storageUnavailable();
            }
            $stream = $storage->open($file->storageKey);
            $content = stream_get_contents($stream);
            fclose($stream);
            if (!is_string($content) || strlen($content) !== $file->sizeBytes) {
                throw FileMediaException::storageUnavailable();
            }
            (new PdoAuditRepository($pdo))->appendTenantSystem(
                $tenantId,
                'tenant.file.delivered',
                'peanut.file-media.read',
                $requestId,
                ['adapter_key' => 'local-signed', 'visibility' => 'private', 'replay_mode' => 'single_use'],
            );
            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($throwable instanceof FileMediaException) {
                $throwable = self::problem($throwable);
            }
            $response = (new ProblemDetailsAdapter())->respond($throwable, RequestId::fromHeader($requestId));

            return TenantModuleRuntime::response($response, $requestId);
        }

        return Response::create($content, 'html', 200)->header(['Content-Type' => $file->mediaType,'Content-Length' => (string) $file->sizeBytes,'Content-Disposition' => 'inline; filename*=UTF-8\'\'' . rawurlencode($file->originalName),'X-Content-Type-Options' => 'nosniff','X-Request-Id' => $requestId,'Cache-Control' => 'private, no-store']);
    }

    private static function service(PDO $pdo, int $tenantId): DeliveryService
    {
        return new DeliveryService(self::adapter($pdo, $tenantId), new DeliveryPolicy());
    }
    private static function adapter(PDO $pdo, int $tenantId): LocalSignedDeliveryAdapter
    {
        $c = self::config();
        if ($c['provider'] !== 'local-private' || $c['delivery_adapter'] !== 'local-signed') {
            throw FileMediaException::deliveryUnavailable();
        }return new LocalSignedDeliveryAdapter($c['delivery_base_url'], self::tokens($pdo, $tenantId));
    }
    private static function tokens(PDO $pdo, int $tenantId): SignedDeliveryTokenService
    {
        $key = self::config()['delivery_signing_key'];
        if (strlen($key) < 32) {
            throw FileMediaException::deliveryUnavailable();
        }return new SignedDeliveryTokenService($key, new PdoDeliveryReplayGuard($pdo, $tenantId));
    }
    private static function storage(): PrivateStorageAdapter
    {
        $c = self::config();
        if ($c['provider'] !== 'local-private') {
            throw FileMediaException::storageUnavailable();
        }return new PrivateStorageAdapter(new LocalPrivateStorageProvider($c['local_root'], $c['public_roots']));
    }
    /** @return array{provider:string,delivery_adapter:string,delivery_base_url:string,delivery_signing_key:string,local_root:string,public_roots:list<string>} */
    private static function config(): array
    {
        $c = require dirname(__DIR__, 3) . '/backend/config/file-media.php';
        foreach (['provider','delivery_adapter','delivery_base_url','delivery_signing_key','local_root'] as $k) {
            if (!is_string($c[$k] ?? null)) {
                throw FileMediaException::deliveryUnavailable();
            }
        }if (!is_array($c['public_roots'] ?? null)) {
            throw FileMediaException::deliveryUnavailable();
        }return $c;
    }
    private static function emptyBody(mixed $v): void
    {
        if (!is_array($v) || $v !== []) {
            throw FileMediaException::deliveryInvalid();
        }
    }
    /** @param array<string,mixed> $body */
    private static function noInput(array $body): void
    {
        self::emptyBody($body['payload'] ?? null);
        if (($body['query'] ?? null) !== []) {
            throw FileMediaException::deliveryInvalid();
        }
    }
    private static function problem(FileMediaException $e): ApiException
    {
        return new ApiException($e->errorCode, $e->httpStatus, 'The file delivery operation could not be completed.');
    }
}
