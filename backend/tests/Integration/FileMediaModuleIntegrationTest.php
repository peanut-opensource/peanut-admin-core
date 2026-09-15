<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Integration;

use PDO;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use think\App;
use think\Request;
use think\Response;

final class FileMediaModuleIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_c02_file_media_host_test';
    private const EMAIL = 'file-owner@example.test';
    private const PASSWORD = 'File-Media-C02-2026!';

    private PDO $admin;
    private PDO $pdo;
    private App $app;
    private int $tenantId;
    private int $memberId;
    private string $storageRoot;
    private string $uploadPath;
    private string $accessToken;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through the focused File/Media Host integration gate.');
        }
        if (getenv('DB_HOST') !== '127.0.0.1') {
            throw new RuntimeException('DB_HOST must be 127.0.0.1.');
        }
        $port = $this->requiredPort('DB_PORT');
        if ($port !== $this->requiredPort('MYSQL_PORT')) {
            throw new RuntimeException('File/Media MYSQL_PORT and DB_PORT must match.');
        }
        $password = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
        $this->admin = new PDO(
            "mysql:host=127.0.0.1;port={$port};charset=utf8mb4",
            'root',
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec(
            'CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
        );
        $this->pdo = new PDO(
            "mysql:host=127.0.0.1;port={$port};dbname=" . self::DATABASE . ';charset=utf8mb4',
            'root',
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        foreach ([
            'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'AUTH_IDENTIFIER_HMAC_KEY',
            'FILE_MEDIA_STORAGE_ROOT', 'FILE_MEDIA_DELIVERY_BASE_URL', 'FILE_MEDIA_DELIVERY_SIGNING_KEY',
        ] as $name) {
            $this->originalEnvironment[$name] = getenv($name);
        }
        putenv('DB_HOST=127.0.0.1');
        putenv("DB_PORT={$port}");
        putenv('DB_DATABASE=' . self::DATABASE);
        putenv('DB_USERNAME=root');
        putenv("DB_PASSWORD={$password}");
        putenv('AUTH_IDENTIFIER_HMAC_KEY=file-media-host-integration-hmac-key');
        $this->storageRoot = sys_get_temp_dir() . '/peanut-file-media-host-' . bin2hex(random_bytes(8));
        putenv('FILE_MEDIA_STORAGE_ROOT=' . $this->storageRoot);
        putenv('FILE_MEDIA_DELIVERY_BASE_URL=https://peanut-admin.test');
        putenv('FILE_MEDIA_DELIVERY_SIGNING_KEY=' . str_repeat('s', 32));

        $root = dirname(__DIR__, 3);
        $app = new App($root . '/backend');
        $app->initialize();
        $app->cache->clear();
        $this->app = $app;
        $installation = (new InstallWorkflow(
            $root,
        ))->run(
            InstallProductProfile::load(
                $root . '/profiles/reference-admin.json',
                $root . '/schemas/product-profile.schema.json',
            ),
            self::EMAIL,
            self::PASSWORD,
            'File Owner',
            [
                'code' => 'file-media-host',
                'name' => 'File Media Host',
                'owner_email' => self::EMAIL,
                'owner_name' => 'File Owner',
            ],
        );
        $this->tenantId = (int) $installation['tenant']['tenant_id'];
        $this->memberId = (int) $installation['tenant']['owner_member_id'];
        $this->grantPermissions();
        $this->uploadPath = tempnam(sys_get_temp_dir(), 'peanut-file-http-')
            ?: throw new RuntimeException('Could not create the File/Media upload fixture.');
        file_put_contents($this->uploadPath, "host multipart bytes\n");
        $authentication = $app->make(TenantAuthService::class)->login(
            self::EMAIL,
            self::PASSWORD,
            'file-media-host',
            '127.0.0.1',
            'File Media integration',
            'req_file_media_login_0001',
        );
        self::assertInstanceOf(TenantAuthentication::class, $authentication);
        $this->accessToken = $authentication->tokens->access->expose();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->app)) {
                $this->app->cache->clear();
            }
            @unlink($this->uploadPath ?? '');
            $this->removeTree($this->storageRoot ?? '');
            if (isset($this->admin)) {
                $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
            }
            foreach ($this->originalEnvironment as $name => $value) {
                $value === false ? putenv($name) : putenv("{$name}={$value}");
            }
        } finally {
            if (isset($this->app)) {
                restore_error_handler();
                restore_exception_handler();
            }
        }
    }

    public function testMultipartLifecycleHeadersTenantScopeAndAuditRedaction(): void
    {
        $created = $this->http($this->request(
                'POST',
                '/api/v1/files',
                'req_file_create_0001',
                files: $this->uploadFiles('../private report.txt'),
            ));
        self::assertSame(201, $created->getCode(), json_encode($created->getData(), JSON_THROW_ON_ERROR));
        $file = $created->getData()['data'] ?? null;
        self::assertIsArray($file);
        $fileKey = $file['file_key'] ?? null;
        self::assertIsString($fileKey);
        self::assertSame('private report.txt', $file['original_name'] ?? null);
        self::assertSame('text/plain', $file['media_type'] ?? null);
        self::assertSame(hash_file('sha256', $this->uploadPath), $file['sha256'] ?? null);
        self::assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}\\.\\d{3}Z$/D', $file['created_at'] ?? '');
        self::assertSame($file['created_at'], $file['updated_at'] ?? null);
        self::assertNull($file['archived_at'] ?? null);
        self::assertSame('"rev-1"', $created->getHeader('ETag'));
        self::assertSame('/api/v1/files/' . $fileKey, $created->getHeader('Location'));
        foreach (['tenant_id', 'storage_key', 'storage_provider_key', 'created_by_member_id'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $file);
        }

        $list = $this->http($this->request(
            'GET', '/api/v1/files', 'req_file_list_0001', query: ['page' => '1', 'page_size' => '20'],
        ));
        self::assertSame(200, $list->getCode());
        self::assertSame($fileKey, $list->getData()['data']['items'][0]['file_key'] ?? null);

        $download = $this->http($this->request(
            'GET', "/api/v1/files/{$fileKey}/content", 'req_file_download_0001',
        ));
        self::assertSame(200, $download->getCode());
        self::assertSame("host multipart bytes\n", $download->getContent());
        self::assertSame('text/plain', $download->getHeader('Content-Type'));
        self::assertSame('nosniff', $download->getHeader('X-Content-Type-Options'));
        self::assertSame('private, no-store', $download->getHeader('Cache-Control'));
        self::assertStringStartsWith('attachment; filename="private_report.txt";', $download->getHeader('Content-Disposition'));

        $archived = $this->http($this->request(
            'DELETE', "/api/v1/files/{$fileKey}", 'req_file_archive_0001', headers: ['if-match' => '"rev-1"'],
        ));
        self::assertSame(200, $archived->getCode());
        self::assertSame('archived', $archived->getData()['data']['status'] ?? null);
        self::assertSame('"rev-2"', $archived->getHeader('ETag'));
        self::assertMatchesRegularExpression(
            '/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}\\.\\d{3}Z$/D',
            $archived->getData()['data']['archived_at'] ?? '',
        );

        $denied = $this->http($this->request(
            'GET', "/api/v1/files/{$fileKey}/content", 'req_file_archived_download_0001',
        ));
        self::assertSame(404, $denied->getCode());
        self::assertSame('FILE_NOT_FOUND', $denied->getData()['code'] ?? null);
        self::assertSame(3, (int) $this->scalar(<<<'SQL'
SELECT COUNT(*) FROM pa_tenant_audit_event
WHERE action IN ('peanut.file-media.create', 'peanut.file-media.read', 'peanut.file-media.delete')
SQL));
        $auditJson = (string) $this->scalar(<<<'SQL'
SELECT JSON_ARRAYAGG(metadata_json) FROM pa_tenant_audit_event
WHERE action LIKE 'peanut.file-media.%'
SQL);
        foreach (['storage_key', 'storage_provider', 'original_name', 'content', 'token', 'path'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $auditJson);
        }
    }

    public function testPermissionAudienceInputAndNotFoundBoundariesFailClosed(): void
    {
        $this->pdo->exec(<<<SQL
DELETE role_permission FROM pa_role_permission role_permission
JOIN pa_permission permission ON permission.id = role_permission.permission_id
WHERE role_permission.tenant_id = {$this->tenantId}
  AND permission.`key` = 'peanut.file-media.read'
SQL);
        self::assertSame(1, $this->pdo->exec(<<<SQL
UPDATE pa_role SET authorization_revision = authorization_revision + 1, updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = {$this->tenantId} AND `key` = 'core.tenant-owner'
SQL));
        $denied = $this->http($this->request('GET', '/api/v1/files', 'req_file_permission_denied_0001'));
        self::assertSame(403, $denied->getCode());
        self::assertSame('AUTHZ_PERMISSION_DENIED', $denied->getData()['code'] ?? null);

        $wrongAudience = $this->http($this->request(
            'GET', '/api/v1/files', 'req_file_wrong_audience_0001', trustedContext: false,
        ));
        self::assertSame(401, $wrongAudience->getCode());

        $undeclared = $this->http($this->request(
                'POST',
                '/api/v1/files',
                'req_file_undeclared_0001',
                body: ['tenant_id' => 99],
                files: $this->uploadFiles('report.txt'),
            ));
        self::assertSame(422, $undeclared->getCode());
        self::assertSame('FILE_UPLOAD_INVALID', $undeclared->getData()['code'] ?? null);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pa_file_object'));

        $this->grantPermissions();

        $unknown = $this->http($this->request(
            'GET', '/api/v1/files/file_' . str_repeat('f', 32), 'req_file_unknown_0001',
        ));
        $malformed = $this->http($this->request(
            'GET', '/api/v1/files/not-a-key', 'req_file_malformed_0001',
        ));
        self::assertSame(404, $unknown->getCode());
        self::assertSame($unknown->getData()['code'] ?? null, $malformed->getData()['code'] ?? null);
    }

    public function testSignedDeliveryNeedsNoBearerAndIsAuditedAsSingleUseTenantSystemWork(): void
    {
        $created = $this->http($this->request(
                'POST',
                '/api/v1/files',
                'req_file_delivery_create_0001',
                files: $this->uploadFiles('preview.txt'),
            ));
        self::assertSame(201, $created->getCode());
        $fileKey = $created->getData()['data']['file_key'] ?? null;
        self::assertIsString($fileKey);

        $grant = $this->http($this->request(
            'POST',
            "/api/v1/files/{$fileKey}/delivery-grants",
            'req_file_delivery_grant_0001',
        ));
        self::assertSame(201, $grant->getCode(), json_encode($grant->getData(), JSON_THROW_ON_ERROR));
        $uri = $grant->getData()['data']['delivery_uri'] ?? null;
        self::assertIsString($uri);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        self::assertIsString($query['token'] ?? null);

        $deliveryRequest = $this->request(
            'GET',
            "/api/v1/file-deliveries/{$fileKey}",
            'req_file_delivery_public_0001',
            query: ['token' => $query['token']],
            trustedContext: false,
        );
        self::assertNull($deliveryRequest->header('authorization'));
        $delivery = $this->http($deliveryRequest);
        self::assertSame(200, $delivery->getCode(), json_encode($delivery->getData(), JSON_THROW_ON_ERROR));
        self::assertSame("host multipart bytes\n", $delivery->getContent());
        self::assertSame('tenant_system', $this->scalar(<<<'SQL'
SELECT actor_type FROM pa_tenant_audit_event
WHERE tenant_id = ? AND event_type = 'tenant.file.delivered' AND request_id = ?
SQL, [$this->tenantId, 'req_file_delivery_public_0001']));

        $replay = $this->http($deliveryRequest);
        self::assertSame(403, $replay->getCode());
        self::assertSame('FILE_DELIVERY_DENIED', $replay->getData()['code'] ?? null);
    }

    public function testAuditFailureRollsBackMetadataAndCompensatesStorage(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TRIGGER fail_file_media_created_audit
BEFORE INSERT ON pa_tenant_audit_event
FOR EACH ROW
BEGIN
  IF NEW.event_type = 'tenant.file.created' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic audit failure';
  END IF;
END
SQL);
        try {
            $response = $this->http($this->request(
                    'POST',
                    '/api/v1/files',
                    'req_file_audit_failure_0001',
                    files: $this->uploadFiles('report.txt'),
                ));
        } finally {
            $this->pdo->exec('DROP TRIGGER IF EXISTS fail_file_media_created_audit');
        }
        self::assertSame(500, $response->getCode());
        self::assertSame('INTERNAL_ERROR', $response->getData()['code'] ?? null);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pa_file_object'));
        self::assertSame([], $this->storedFiles());
        self::assertStringNotContainsString('synthetic audit failure', json_encode($response->getData(), JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     */
    private function request(
        string $method,
        string $url,
        string $requestId,
        array $body = [],
        array $headers = [],
        array $query = [],
        array $files = [],
        bool $trustedContext = true,
    ): Request {
        $request = (new Request())
            ->setMethod($method)
            ->setUrl($url)
            ->withServer([
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $url,
                'HTTP_HOST' => 'localhost',
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->withGet($query)
            ->withPost($body)
            ->withFiles($files)
            ->withHeader([
                'accept' => 'application/json',
                'content-type' => $files === [] ? 'application/json' : 'multipart/form-data',
                'x-request-id' => $requestId,
                ...($trustedContext ? ['authorization' => 'Bearer ' . $this->accessToken] : []),
                ...$headers,
            ]);
        if ($body !== []) {
            $request->withInput(json_encode($body, JSON_THROW_ON_ERROR));
        }
        return $request;
    }

    private function http(Request $request): Response
    {
        $outputLevel = ob_get_level();
        $app = new App(dirname(__DIR__, 3) . '/backend');
        $http = $app->http;
        try {
            $response = $http->run($request);
            $http->end($response);
            return $response;
        } finally {
            while (ob_get_level() > $outputLevel) {
                ob_end_clean();
            }
            restore_error_handler();
            restore_exception_handler();
        }
    }

    private function grantPermissions(): void
    {
        $this->pdo->exec(<<<SQL
INSERT INTO pa_role_permission (tenant_id, role_id, permission_id, granted_by_member_id, granted_at)
SELECT {$this->tenantId}, role.id, permission.id, {$this->memberId}, UTC_TIMESTAMP(3)
FROM pa_role role
JOIN pa_permission permission ON permission.`key` IN (
  'peanut.file-media.read', 'peanut.file-media.create', 'peanut.file-media.delete'
)
WHERE role.tenant_id = {$this->tenantId} AND role.`key` = 'core.tenant-owner'
ON DUPLICATE KEY UPDATE granted_at = VALUES(granted_at)
SQL);
        self::assertSame(1, $this->pdo->exec(<<<SQL
UPDATE pa_role SET authorization_revision = authorization_revision + 1, updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = {$this->tenantId} AND `key` = 'core.tenant-owner'
SQL));
    }

    /** @return array{file: array{name: string, type: string, tmp_name: string, error: int, size: int}} */
    private function uploadFiles(string $name): array
    {
        return ['file' => [
            'name' => $name,
            'type' => 'application/octet-stream',
            'tmp_name' => $this->uploadPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($this->uploadPath) ?: 0,
        ]];
    }

    /** @param list<mixed> $parameters */
    private function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        if (!is_dir($this->storageRoot)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->storageRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $files[] = $entry->getPathname();
            }
        }

        return $files;
    }

    private function removeTree(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($path);
    }

    private function requiredPort(string $name): int
    {
        $value = getenv($name);
        if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
            throw new RuntimeException("Missing required environment variable: {$name}.");
        }
        $port = (int) $value;
        if ($port < 1024 || $port > 65535) {
            throw new RuntimeException("Invalid local port in environment variable: {$name}.");
        }

        return $port;
    }
}
