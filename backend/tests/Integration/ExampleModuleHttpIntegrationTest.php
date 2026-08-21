<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Integration;

use PDO;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\middleware\TenantAuthRuntimeFactory;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Testing\Authorization\PdoAuthorizationFixtureSeeder;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Request;
use think\Response;

final class ExampleModuleHttpIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_example_http_test';

    private PDO $admin;
    private PDO $pdo;
    private int $tenantId;
    private int $memberId;
    private string $accessToken;

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
        putenv('AUTH_IDENTIFIER_HMAC_KEY=example-http-integration-hmac-key-2026');

        $root = dirname(__DIR__, 3);
        $password = 'Example-Http-P0-Only-2026!';
        $installation = (new InstallWorkflow($root, $this->pdo))->run(
            InstallProductProfile::load(
                $root . '/profiles/reference-admin.json',
                $root . '/schemas/product-profile.schema.json',
            ),
            'http-owner@example.test',
            $password,
            'HTTP Owner',
            [
                'code' => 'http-test',
                'name' => 'HTTP Test',
                'owner_email' => 'http-owner@example.test',
                'owner_name' => 'HTTP Owner',
            ],
        );
        $this->tenantId = (int) $installation['tenant']['tenant_id'];
        $this->memberId = (int) $installation['tenant']['owner_member_id'];
        $this->seedExampleData();
        $this->seedAuthorization();
        $authentication = TenantAuthRuntimeFactory::create(pdo: $this->pdo)->login(
            'http-owner@example.test',
            $password,
            'http-test',
            '127.0.0.1',
            'Example HTTP integration',
            'req_example_http_login',
        );
        self::assertInstanceOf(TenantAuthentication::class, $authentication);
        $this->accessToken = $authentication->tokens->access->expose();
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

    public function testSevenExampleEndpointsRunThroughAuthorizedHttpChain(): void
    {
        $list = $this->request('GET', '/api/v1/example/work-items', null, [
            'page' => 1,
            'page_size' => 20,
            'target_resource_key' => 'example.project',
            'target_role' => 'primary',
            'target_id' => '1,2',
            'sort' => '-created_at',
        ]);
        self::assertSame(200, $list->getCode());
        self::assertCount(2, $list->getData()['data']);
        self::assertSame(['Project A', 'Project B'], $this->sortedBoundaryLabels($list));
        self::assertSame('multiple', $list->getData()['meta']['target_scope']['mode']);

        $detail = $this->request('GET', '/api/v1/example/work-items/1');
        self::assertSame(200, $detail->getCode());
        self::assertSame('"rev-1"', $detail->getHeader('ETag'));
        self::assertSame('Project A', $detail->getData()['data']['boundary_target']['label']);

        $aggregate = $this->request('GET', '/api/v1/example/work-items/aggregate', null, [
            'target_resource_key' => 'example.project',
            'target_role' => 'primary',
            'target_id' => '1,2',
        ]);
        self::assertSame(200, $aggregate->getCode());
        self::assertSame(2, $aggregate->getData()['data']['total']);
        self::assertSame(2, $aggregate->getData()['data']['by_status']['open']);

        $referenceA = $this->request('GET', '/api/v1/example/reference-items/candidates', null, [
            'target_resource_key' => 'example.project',
            'target_role' => 'primary',
            'target_id' => '1',
        ]);
        self::assertSame(200, $referenceA->getCode());
        self::assertSame(['private-a', 'public-ref'], array_column($referenceA->getData()['data'], 'code'));
        $referenceB = $this->request('GET', '/api/v1/example/reference-items/candidates', null, [
            'target_resource_key' => 'example.project',
            'target_role' => 'primary',
            'target_id' => '2',
        ]);
        self::assertSame(['public-ref'], array_column($referenceB->getData()['data'], 'code'));

        $created = $this->request('POST', '/api/v1/example/work-items', [
            'target' => $this->target('1'),
            'reference_item_id' => '2',
            'title' => 'Created through HTTP',
        ], [], ['idempotency-key' => 'example-create-0001']);
        self::assertSame(201, $created->getCode());
        self::assertSame('3', $created->getData()['data']['id']);
        self::assertSame('"rev-1"', $created->getHeader('ETag'));

        $scopeDenied = $this->request('POST', '/api/v1/example/work-items', [
            'target' => $this->target('1'),
            'reference_item_id' => '3',
            'title' => 'Denied reference',
        ], [], ['idempotency-key' => 'example-create-0002']);
        self::assertSame(404, $scopeDenied->getCode());
        self::assertSame('AUTHZ_DATA_DENIED', $scopeDenied->getData()['code']);

        $updated = $this->request('PATCH', '/api/v1/example/work-items/1', [
            'target' => $this->target('1'),
            'title' => 'Updated through HTTP',
            'status' => 'active',
        ], [], [
            'if-match' => '"rev-1"',
            'idempotency-key' => 'example-update-0001',
        ]);
        self::assertSame(200, $updated->getCode());
        self::assertSame('2', $updated->getData()['data']['revision']);
        self::assertSame('"rev-2"', $updated->getHeader('ETag'));

        $crossTarget = $this->request('PATCH', '/api/v1/example/work-items/2', [
            'target' => $this->target('1'),
            'title' => 'Must not cross target',
        ], [], [
            'if-match' => '"rev-1"',
            'idempotency-key' => 'example-update-0002',
        ]);
        self::assertSame(404, $crossTarget->getCode());
        self::assertSame('AUTHZ_DATA_DENIED', $crossTarget->getData()['code']);

        $published = $this->request('POST', '/api/v1/example/work-item-view-policies', [
            'name' => 'HTTP view policy',
            'config' => ['status' => ['open', 'active']],
            'targets' => [[
                'target_resource_key' => 'example.project',
                'target_role' => 'primary',
                'target_ids' => ['1', '2'],
            ]],
        ], [], ['idempotency-key' => 'example-policy-0001']);
        self::assertSame(201, $published->getCode());
        self::assertCount(2, $published->getData()['data']);
        self::assertSame(['published', 'published'], array_column($published->getData()['data'], 'status'));
        self::assertSame(2, $this->scalarCount('SELECT COUNT(*) FROM pa_example_work_item_policy_publication'));
        self::assertSame(3, $this->scalarCount(<<<'SQL'
SELECT COUNT(*) FROM pa_tenant_audit_event
WHERE event_type IN (
    'example.work-item.created',
    'example.work-item.updated',
    'example.work-item.policy-published'
)
SQL));

        $disable = $this->pdo->prepare(<<<'SQL'
UPDATE pa_tenant_module SET status = 'disabled', updated_at = UTC_TIMESTAMP(3)
WHERE tenant_id = :tenant_id AND module_key = 'example.work-item'
SQL);
        $disable->execute(['tenant_id' => $this->tenantId]);
        $moduleDenied = $this->request('GET', '/api/v1/example/work-items', null, [
            'target_resource_key' => 'example.project',
            'target_role' => 'primary',
            'target_id' => ['1'],
        ]);
        self::assertSame(403, $moduleDenied->getCode());
        self::assertSame('MODULE_TENANT_DISABLED', $moduleDenied->getData()['code']);
    }

    private function seedExampleData(): void
    {
        $tenantId = $this->tenantId;
        $memberId = $this->memberId;
        $this->pdo->exec(<<<SQL
INSERT INTO pa_example_project (id, tenant_id, code, name, status, created_at, updated_at) VALUES
    (1, {$tenantId}, 'A', 'Project A', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (2, {$tenantId}, 'B', 'Project B', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (3, {$tenantId}, 'C', 'Project C', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
INSERT INTO pa_example_reference_item
    (id, owner_type, owner_tenant_id, code, name, status, created_at, updated_at) VALUES
    (1, 'deployment', NULL, 'public-ref', 'Public Reference', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (2, 'tenant', {$tenantId}, 'private-a', 'Private A', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (3, 'tenant', {$tenantId}, 'private-c', 'Private C', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
INSERT INTO pa_example_reference_scope
    (reference_item_id, scope_kind, target_tenant_id, target_resource_key, target_id, capability, status) VALUES
    (1, 'all_tenants', NULL, NULL, NULL, 'use', 'active'),
    (2, 'typed_target', {$tenantId}, 'example.project', '1', 'use', 'active'),
    (3, 'typed_target', {$tenantId}, 'example.project', '3', 'use', 'active');
INSERT INTO pa_example_work_item
    (id, tenant_id, project_id, queue_id, reference_item_id, owner_member_id, department_id,
     title, status, created_by_member_id, created_at, updated_at) VALUES
    (1, {$tenantId}, 1, NULL, 1, {$memberId}, NULL, 'Project A work', 'open', {$memberId}, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
    (2, {$tenantId}, 2, NULL, 1, {$memberId}, NULL, 'Project B work', 'open', {$memberId}, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
SQL);
    }

    private function seedAuthorization(): void
    {
        $seeder = new PdoAuthorizationFixtureSeeder($this->pdo);
        $roleId = $seeder->roleForMember($this->tenantId, $this->memberId);
        $seeder->grantPermissions($this->tenantId, $roleId, [
            'example.reference.use',
            'example.work-item.create',
            'example.work-item.policy-publish',
            'example.work-item.read',
            'example.work-item.update',
        ]);
        $readProjects = $seeder->targetSet($this->tenantId, $this->memberId, 'example.project', ['1', '2']);
        $writeProjects = $seeder->targetSet($this->tenantId, $this->memberId, 'example.project', ['1']);
        foreach ([
            ['example.reference-item', 'use', $readProjects],
            ['example.work-item', 'list', $readProjects],
            ['example.work-item', 'aggregate', $readProjects],
            ['example.work-item', 'create', $writeProjects],
            ['example.work-item', 'update', $writeProjects],
            ['example.work-item', 'policy-publish', $readProjects],
        ] as [$resourceKey, $operation, $targetSetId]) {
            $seeder->allowTargetGroups(
                $this->tenantId,
                $roleId,
                $this->memberId,
                $resourceKey,
                $operation,
                [['example.project' => $targetSetId]],
            );
        }
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    private function request(
        string $method,
        string $url,
        ?array $body = null,
        array $query = [],
        array $headers = [],
    ): Response {
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
                'authorization' => 'Bearer ' . $this->accessToken,
                'content-type' => 'application/json',
                'user-agent' => 'Peanut example HTTP test',
                'x-request-id' => 'req_example_' . bin2hex(random_bytes(8)),
                ...$headers,
            ]);
        if ($body !== null) {
            $request->withPost($body)->withInput(json_encode($body, JSON_THROW_ON_ERROR));
        }
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

    /** @return array{target_resource_key: string, target_role: string, target_id: string} */
    private function target(string $id): array
    {
        return [
            'target_resource_key' => 'example.project',
            'target_role' => 'primary',
            'target_id' => $id,
        ];
    }

    /** @return list<string> */
    private function sortedBoundaryLabels(Response $response): array
    {
        $labels = array_column(array_column($response->getData()['data'], 'boundary_target'), 'label');
        sort($labels, SORT_STRING);

        return $labels;
    }

    private function scalarCount(string $sql): int
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('The example HTTP count query failed.');
        }

        return (int) $statement->fetchColumn();
    }
}
