<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Integration;

use PDO;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallProductProfileApplier;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\command\KernelBootstrapFactory;
use PeanutAdmin\App\middleware\PlatformAuthRuntimeFactory;
use PeanutAdmin\App\middleware\TenantAuthRuntimeFactory;
use PeanutAdmin\Kernel\Auth\PlatformAuthentication;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Testing\Authorization\PdoAuthorizationFixtureSeeder;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Request;
use think\Response;

final class EffectiveAccessPreviewHttpIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_effective_access_http_test';
    private const EMAIL = 'effective-access-owner@example.test';
    private const PASSWORD = 'Effective-Access-P1-2026!';

    private PDO $admin;
    private PDO $pdo;
    private string $tenantAccessToken;
    private string $platformAccessToken;
    private int $tenantId;
    private int $memberId;
    private int $otherTenantMemberId;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run through scripts/test-integration.');
        }
        $port = (int) (getenv('MYSQL_PORT') ?: 3306);
        $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
        $this->admin = new PDO(
            "mysql:host=127.0.0.1;port={$port};charset=utf8mb4",
            'root',
            $rootPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec('CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $this->pdo = new PDO(
            "mysql:host=127.0.0.1;port={$port};dbname=" . self::DATABASE . ';charset=utf8mb4',
            'root',
            $rootPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'AUTH_IDENTIFIER_HMAC_KEY'] as $name) {
            $this->originalEnvironment[$name] = getenv($name);
        }
        putenv('DB_HOST=127.0.0.1');
        putenv("DB_PORT={$port}");
        putenv('DB_DATABASE=' . self::DATABASE);
        putenv('DB_USERNAME=root');
        putenv("DB_PASSWORD={$rootPassword}");
        putenv('AUTH_IDENTIFIER_HMAC_KEY=effective-access-http-hmac-key-2026');

        $root = dirname(__DIR__, 3);
        $profile = InstallProductProfile::load(
            $root . '/profiles/reference-admin.json',
            $root . '/schemas/product-profile.schema.json',
        );
        $installation = (new InstallWorkflow($root, $this->pdo))->run(
            $profile,
            self::EMAIL,
            self::PASSWORD,
            'Effective Access Platform Owner',
            [
                'code' => 'effective-access',
                'name' => 'Effective Access Tenant',
                'owner_email' => self::EMAIL,
                'owner_name' => 'Effective Access Owner',
            ],
        );
        $this->tenantId = (int) $installation['tenant']['tenant_id'];
        $this->memberId = (int) $installation['tenant']['owner_member_id'];

        $bootstrap = KernelBootstrapFactory::create();
        $other = $bootstrap->provisionTenantOwnerCandidate(
            (int) $installation['platform']['operator_id'],
            'effective-access-other',
            'Other Tenant',
            self::EMAIL,
            null,
            'Other Tenant Owner',
            'req_effective_access_other',
        );
        $bootstrap->activateTenantOwner(
            (int) $installation['platform']['operator_id'],
            $other->tenantId,
            $other->memberId,
            'req_effective_access_other_owner',
        );
        $bootstrap->activateTenant(
            (int) $installation['platform']['operator_id'],
            $other->tenantId,
            'req_effective_access_other_tenant',
        );
        (new InstallProductProfileApplier($root, $this->pdo))->apply($other->tenantId, $profile);
        $this->otherTenantMemberId = $other->memberId;

        $seeder = new PdoAuthorizationFixtureSeeder($this->pdo);
        $roleId = $seeder->roleForMember($this->tenantId, $this->memberId);
        $seeder->grantPermissions($this->tenantId, $roleId, ['example.work-item.read']);
        $targets = $seeder->targetSet($this->tenantId, $this->memberId, 'example.project', ['1001']);
        $seeder->allowTargetGroups(
            $this->tenantId,
            $roleId,
            $this->memberId,
            'example.work-item',
            'list',
            [['example.project' => $targets]],
        );

        $tenantAuthentication = TenantAuthRuntimeFactory::create(pdo: $this->pdo)->login(
            self::EMAIL,
            self::PASSWORD,
            'effective-access',
            '127.0.0.1',
            'Effective access HTTP integration',
            'req_effective_access_tenant_login',
        );
        self::assertInstanceOf(TenantAuthentication::class, $tenantAuthentication);
        $this->tenantAccessToken = $tenantAuthentication->tokens->access->expose();

        $platformAuthentication = PlatformAuthRuntimeFactory::create($this->pdo)->login(
            self::EMAIL,
            self::PASSWORD,
            '127.0.0.1',
            'Effective access HTTP integration',
            'req_effective_access_platform_login',
        );
        self::assertInstanceOf(PlatformAuthentication::class, $platformAuthentication);
        $this->platformAccessToken = $platformAuthentication->tokens->access->expose();
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
        foreach ($this->originalEnvironment as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }
    }

    public function testPreviewUsesTheRealTenantGuardsAndReturnsARedactedAuditedSnapshot(): void
    {
        $securityEventCount = $this->countAuthSecurityEvents();
        $preview = $this->request(
            'GET',
            "/api/v1/members/{$this->memberId}/effective-access",
            $this->tenantAccessToken,
            ['page' => 1, 'page_size' => 100],
        );

        self::assertSame(200, $preview->getCode());
        self::assertSame('no-store', $preview->getHeader('Cache-Control'));
        self::assertMatchesRegularExpression('/^req_effective_access_[a-f0-9]{16}$/', $preview->getHeader('X-Request-Id'));
        $data = $preview->getData()['data'];
        self::assertSame('authorization_inputs', $data['preview_kind']);
        self::assertSame((string) $this->memberId, $data['member']['id']);
        self::assertContains('core.member.effective-access.read', $data['permission_keys']);
        self::assertContains('example.work-item.read', $data['permission_keys']);
        $operation = $this->operation($data['resource_operations'], 'example.work-item', 'list');
        self::assertTrue($operation['functional_allowed']);
        self::assertSame('conditional', $operation['data_access']['mode']);
        self::assertSame(1, $operation['data_access']['groups'][0]['conditions'][0]['target_count']);

        $encoded = json_encode($preview->getData(), JSON_THROW_ON_ERROR);
        foreach (['account_id', 'provider_key', 'provider_class', 'target_ids', 'secret', 'session'] as $forbidden) {
            self::assertStringNotContainsString(sprintf('"%s":', $forbidden), $encoded);
        }
        self::assertSame(1, $this->countPreviewAudits());
        $metadata = $this->previewAuditMetadata();
        self::assertSame(count($data['permission_keys']), $metadata['permission_count']);
        self::assertArrayNotHasKey('permission_keys', $metadata);
        self::assertArrayNotHasKey('target_ids', $metadata);

        $outside = $this->request(
            'GET',
            "/api/v1/members/{$this->otherTenantMemberId}/effective-access",
            $this->tenantAccessToken,
        );
        $unknown = $this->request(
            'GET',
            '/api/v1/members/999999999/effective-access',
            $this->tenantAccessToken,
        );
        self::assertSame(404, $outside->getCode());
        self::assertSame(404, $unknown->getCode());
        foreach (['code', 'status', 'title', 'detail'] as $field) {
            self::assertSame($outside->getData()[$field], $unknown->getData()[$field], $field);
        }
        self::assertSame(1, $this->countPreviewAudits());

        foreach (['abc', '0', '-1', '01', (string) PHP_INT_MAX . '0'] as $invalidId) {
            $invalid = $this->request(
                'GET',
                "/api/v1/members/{$invalidId}/effective-access",
                $this->tenantAccessToken,
            );
            self::assertSame(422, $invalid->getCode(), $invalidId);
            self::assertSame('MEMBER_ID_INVALID', $invalid->getData()['code'], $invalidId);
        }
        self::assertSame(1, $this->countPreviewAudits());

        $wrongAudience = $this->request(
            'GET',
            "/api/v1/members/{$this->memberId}/effective-access",
            $this->platformAccessToken,
        );
        self::assertSame(401, $wrongAudience->getCode());
        self::assertSame('AUTH_AUDIENCE_MISMATCH', $wrongAudience->getData()['code']);
        self::assertSame(1, $this->countPreviewAudits());

        $this->pdo->exec(<<<SQL
UPDATE pa_role
SET `key` = 'tenant.preview-denied', is_builtin = 0,
    authorization_revision = authorization_revision + 1
WHERE tenant_id = {$this->tenantId} AND `key` = 'core.tenant-owner'
SQL);
        $denied = $this->request(
            'GET',
            "/api/v1/members/{$this->memberId}/effective-access",
            $this->tenantAccessToken,
        );
        self::assertSame(403, $denied->getCode());
        self::assertSame('AUTHZ_PERMISSION_DENIED', $denied->getData()['code']);
        self::assertSame(1, $this->countPreviewAudits());
        self::assertSame($securityEventCount, $this->countAuthSecurityEvents());
    }

    public function testPaginationValidationAndAnExtremePageStayWithinTheHttpContract(): void
    {
        foreach ([
            ['query' => ['page' => 0], 'code' => 'PAGE_INVALID'],
            ['query' => ['page_size' => 0], 'code' => 'PAGE_SIZE_INVALID'],
            ['query' => ['page_size' => 101], 'code' => 'PAGE_SIZE_INVALID'],
        ] as $case) {
            $invalid = $this->request(
                'GET',
                "/api/v1/members/{$this->memberId}/effective-access",
                $this->tenantAccessToken,
                $case['query'],
            );
            self::assertSame(422, $invalid->getCode(), $case['code']);
            self::assertSame($case['code'], $invalid->getData()['code']);
            self::assertSame('application/problem+json', $invalid->getHeader('Content-Type'));
            self::assertSame('no-store', $invalid->getHeader('Cache-Control'));
            self::assertSame($invalid->getData()['request_id'], $invalid->getHeader('X-Request-Id'));
        }
        self::assertSame(0, $this->countPreviewAudits());

        $extreme = $this->request(
            'GET',
            "/api/v1/members/{$this->memberId}/effective-access",
            $this->tenantAccessToken,
            ['page' => PHP_INT_MAX, 'page_size' => 100],
        );
        self::assertSame(200, $extreme->getCode());
        self::assertSame([], $extreme->getData()['data']['resource_operations']);
        self::assertSame(PHP_INT_MAX, $extreme->getData()['meta']['page']);
        self::assertSame(100, $extreme->getData()['meta']['page_size']);
        self::assertGreaterThan(0, $extreme->getData()['meta']['total']);
        self::assertSame(1, $this->countPreviewAudits());
    }

    /** @param list<array<string, mixed>> $operations
     * @return array<string, mixed>
     */
    private function operation(array $operations, string $resourceKey, string $operation): array
    {
        foreach ($operations as $candidate) {
            if ($candidate['resource_key'] === $resourceKey && $candidate['operation'] === $operation) {
                return $candidate;
            }
        }

        self::fail("Missing operation {$resourceKey}:{$operation}");
    }

    /** @param array<string, mixed> $query */
    private function request(string $method, string $url, string $accessToken, array $query = []): Response
    {
        $outputLevel = ob_get_level();
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
            ->withHeader([
                'accept' => 'application/json',
                'authorization' => 'Bearer ' . $accessToken,
                'content-type' => 'application/json',
                'user-agent' => 'Peanut effective access HTTP test',
                'x-request-id' => 'req_effective_access_' . bin2hex(random_bytes(8)),
            ]);
        $app = new App(dirname(__DIR__, 2));
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

    private function countPreviewAudits(): int
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT COUNT(*) FROM pa_tenant_audit_event
WHERE event_type = 'tenant.member.effective-access.viewed'
SQL);
        if ($statement === false) {
            throw new \RuntimeException('Could not count effective access audit events.');
        }

        return (int) $statement->fetchColumn();
    }

    private function countAuthSecurityEvents(): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) FROM pa_auth_security_event');
        if ($statement === false) {
            throw new \RuntimeException('Could not count authentication security events.');
        }

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function previewAuditMetadata(): array
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT metadata_json FROM pa_tenant_audit_event
WHERE event_type = 'tenant.member.effective-access.viewed'
ORDER BY id DESC LIMIT 1
SQL);
        if ($statement === false) {
            throw new \RuntimeException('Could not read effective access audit metadata.');
        }
        $metadata = $statement->fetchColumn();

        return json_decode((string) $metadata, true, 512, JSON_THROW_ON_ERROR);
    }
}
