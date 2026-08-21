<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Integration;

use PDO;
use PeanutAdmin\App\authorization\DataPermissionRuntimeFactory;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\middleware\TenantAuthRuntimeFactory;
use PeanutAdmin\App\Modules\Example\Reference\Infrastructure\Authorization\PdoReferenceScopeProvider;
use PeanutAdmin\App\Modules\Example\Reference\Infrastructure\Persistence\PdoReferenceQuery;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization\PdoTargetResolver;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Persistence\PdoTargetQuery;
use PeanutAdmin\App\Modules\Example\WorkItem\Infrastructure\Persistence\PdoWorkItemQuery;
use PeanutAdmin\DataPermission\Context\AuthorizationContext;
use PeanutAdmin\DataPermission\Target\TargetCatalogQuery;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Testing\Authorization\PdoAuthorizationFixtureSeeder;
use PHPUnit\Framework\TestCase;

final class ExampleModuleQueryIntegrationTest extends TestCase
{
    private const DATABASE = 'peanut_admin_example_query_test';

    private PDO $admin;
    private PDO $pdo;

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
        putenv('AUTH_IDENTIFIER_HMAC_KEY=example-query-integration-hmac-key-2026');
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

    public function testLargeTargetSetsUseBoundedQueriesAndDatabasePagination(): void
    {
        $root = dirname(__DIR__, 3);
        $profile = InstallProductProfile::load(
            $root . '/profiles/reference-admin.json',
            $root . '/schemas/product-profile.schema.json',
        );
        $password = 'Example-Query-P0-Only-2026!';
        $installation = (new InstallWorkflow($root, $this->pdo))->run(
            $profile,
            'query-owner@example.test',
            $password,
            'Query Owner',
            [
                'code' => 'query-test',
                'name' => 'Query Test',
                'owner_email' => 'query-owner@example.test',
                'owner_name' => 'Query Owner',
            ],
        );
        $tenantId = (int) $installation['tenant']['tenant_id'];
        $memberId = (int) $installation['tenant']['owner_member_id'];
        $referenceId = $this->seedReference($tenantId);
        $projectIds = $this->seedProjectsAndWorkItems($tenantId, $memberId, $referenceId, 501);
        if ($projectIds === []) {
            throw new \RuntimeException('The example query fixture did not create Projects.');
        }
        $authorizationFixture = new PdoAuthorizationFixtureSeeder($this->pdo);
        $roleId = $authorizationFixture->roleForMember($tenantId, $memberId);
        $authorizationFixture->grantPermissions($tenantId, $roleId, [
            'example.reference.read',
            'example.work-item.read',
        ]);
        $projectSetId = $authorizationFixture->targetSet(
            $tenantId,
            $memberId,
            'example.project',
            $projectIds,
        );
        $authorizationFixture->allowTargetGroups(
            $tenantId,
            $roleId,
            $memberId,
            'example.work-item',
            'list',
            [['example.project' => $projectSetId]],
        );
        $authorizationFixture->allowTargetGroups(
            $tenantId,
            $roleId,
            $memberId,
            'example.reference-item',
            'list',
            [['example.project' => $projectSetId]],
        );

        $authentication = TenantAuthRuntimeFactory::create(pdo: $this->pdo)->login(
            'query-owner@example.test',
            $password,
            'query-test',
            '127.0.0.1',
            'Example query integration',
            'req_example_query_login',
        );
        self::assertInstanceOf(TenantAuthentication::class, $authentication);
        $targetSet = new TypedResourceTargetSet('example.project', $projectIds);
        $resolved = (new PdoTargetResolver($this->pdo))->resolveAndValidate(
            $authentication->context,
            $targetSet,
        );
        self::assertCount(501, $resolved->targets->sets[0]->targetIds);

        $authorization = DataPermissionRuntimeFactory::create($this->pdo);
        $targets = new TypedResourceTargetCollection([$targetSet]);
        $page = (new PdoWorkItemQuery($this->pdo, $authorization, new PdoTargetQuery($this->pdo)))->list(
            $authentication->context,
            $targets,
            2,
            50,
        );
        self::assertSame(501, $page->total);
        self::assertSame(2, $page->page);
        self::assertSame(50, $page->pageSize);
        self::assertCount(50, $page->items);

        $authorizationContext = new AuthorizationContext($authentication->context, null);
        $scope = new PdoReferenceScopeProvider($this->pdo);
        self::assertSame(
            [(string) $referenceId],
            $scope->allowedIds(
                $authorizationContext,
                new TypedResourceTargetCollection([$targetSet]),
                'view',
            ),
        );
        self::assertCount(1, (new PdoReferenceQuery($this->pdo, $authorization))->candidates(
            $authentication->context,
            new TypedResourceTargetCollection([$targetSet]),
            'view',
        ));

        $catalogPage = $authorization->searchAllowedTargets(
            $authentication->context,
            'example.work-item',
            'list',
            new TargetCatalogQuery('example.project', '', 2, 50),
        );
        self::assertSame(501, $catalogPage->total);
        self::assertCount(50, $catalogPage->items);
    }

    private function seedReference(int $tenantId): int
    {
        $this->pdo->exec(<<<'SQL'
INSERT INTO pa_example_reference_item
    (owner_type, owner_tenant_id, code, name, status, created_at, updated_at)
VALUES ('deployment', NULL, 'QUERY-REFERENCE', 'Query Reference', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))
SQL);
        $referenceId = (int) $this->pdo->lastInsertId();
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_example_reference_scope
    (reference_item_id, scope_kind, target_tenant_id, target_resource_key, target_id, capability, status)
VALUES (?, 'typed_target', ?, 'example.project', ?, 'view', 'active')
SQL);
        $statement->execute([$referenceId, $tenantId, '501']);

        return $referenceId;
    }

    /** @return list<string> */
    private function seedProjectsAndWorkItems(
        int $tenantId,
        int $memberId,
        int $referenceId,
        int $count,
    ): array {
        $project = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_example_project (tenant_id, code, name, status, created_at, updated_at)
VALUES (?, ?, ?, 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))
SQL);
        $workItem = $this->pdo->prepare(<<<'SQL'
INSERT INTO pa_example_work_item
    (tenant_id, project_id, queue_id, reference_item_id, owner_member_id, department_id,
     title, status, created_by_member_id, created_at, updated_at)
VALUES (?, ?, NULL, ?, ?, NULL, ?, 'open', ?, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))
SQL);
        $ids = [];
        for ($index = 1; $index <= $count; ++$index) {
            $code = 'PROJECT-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT);
            $project->execute([$tenantId, $code, $code]);
            $projectId = (int) $this->pdo->lastInsertId();
            $ids[] = (string) $projectId;
            $workItem->execute([
                $tenantId,
                $projectId,
                $referenceId,
                $memberId,
                'Work item ' . $index,
                $memberId,
            ]);
        }

        return $ids;
    }

}
