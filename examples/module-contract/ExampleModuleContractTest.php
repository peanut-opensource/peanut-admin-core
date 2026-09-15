<?php

declare(strict_types=1);

namespace PeanutAdmin\Examples\ModuleContract;

use DateTimeImmutable;
use PDO;
use PeanutAdmin\App\Tests\Support\ThinkPhpTestConnection;
use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\modules\example\reference\contracts\ReferenceQuery;
use PeanutAdmin\App\modules\example\target\infrastructure\authorization\ThinkPhpTargetResolver;
use PeanutAdmin\App\modules\example\work_item\contracts\CreateWorkItem;
use PeanutAdmin\App\modules\example\work_item\services\WorkItemCommandService;
use PeanutAdmin\App\modules\example\work_item\services\WorkItemPolicyPublisher;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemQuery;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TargetCatalogQuery;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Testing\Authorization\ThinkPhpAuthorizationFixtureSeeder;
use PHPUnit\Framework\TestCase;
use think\App;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;

final class ExampleModuleContractTest extends TestCase
{
    private const DATABASE = 'peanut_admin_example_module_test';

    private PDO $admin;
    private PDO $pdo;
    private DataPermissionEngine $authorization;
    private App $app;

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];
    private int $tenantId;
    private int $memberId;
    private int $accountId;

    protected function setUp(): void
    {
        if (getenv('PEANUT_INTEGRATION') !== '1') {
            self::markTestSkipped('Run with PEANUT_INTEGRATION=1.');
        }
        $this->admin = $this->connect();
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->admin->exec('CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $this->pdo = $this->connect(self::DATABASE);
        $connection = ThinkPhpTestConnection::fromPdo($this->pdo);
        $root = dirname(__DIR__, 2);
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $name) {
            $this->originalEnvironment[$name] = getenv($name);
        }
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=' . (getenv('MYSQL_PORT') ?: '3306'));
        putenv('DB_DATABASE=' . self::DATABASE);
        putenv('DB_USERNAME=root');
        putenv('DB_PASSWORD=' . (getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev'));
        $result = (new InstallWorkflow($root))->run(
            InstallProductProfile::load(
                $root . '/profiles/reference-admin.json',
                $root . '/schemas/product-profile.schema.json',
            ),
            'fixture-owner@example.test',
            'Fixture-Owner-P0-2026!',
            'Fixture Owner',
            [
                'code' => 'fixture',
                'name' => 'Fixture Tenant',
                'owner_email' => 'fixture-owner@example.test',
                'owner_name' => 'Fixture Owner',
            ],
        );
        $this->tenantId = (int) $result['tenant']['tenant_id'];
        $this->memberId = (int) $result['tenant']['owner_member_id'];
        $this->accountId = (int) $this->scalar(
            "SELECT account_id FROM pa_tenant_member WHERE id = {$this->memberId}",
        );
        $this->seedBusinessFixtures();
        $this->seedAuthorization();
        $this->app = new App($root . '/backend');
        $this->app->initialize();
        $this->authorization = $this->app->make(DataPermissionEngine::class);
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

    public function testTypedTargetsUnifiedReferenceAndWorkItemContracts(): void
    {
        $tenant = $this->tenantContext();
        $resolver = $this->app->make(DataPermissionRuntimeRegistry::class)->targetResolvers->get(ThinkPhpTargetResolver::class);
        $project = $resolver->resolveAndValidate($tenant, new TypedResourceTargetSet('example.project', ['1']));
        $queue = $resolver->resolveAndValidate($tenant, new TypedResourceTargetSet('example.queue', ['1'], 'related'));
        self::assertSame('example.project', $project->targets->sets[0]->targetResourceKey);
        self::assertSame('example.queue', $queue->targets->sets[0]->targetResourceKey);
        $options = $this->authorization->searchAllowedTargets(
            $tenant,
            'example.work-item',
            'list',
            new TargetCatalogQuery('example.project', '', 1, 20),
        );
        self::assertSame(['1', '2'], array_column($options->items, 'id'));

        $query = $this->app->make(ReferenceQuery::class);
        $projectA = new TypedResourceTargetCollection([new TypedResourceTargetSet('example.project', ['1'])]);
        $projectB = new TypedResourceTargetCollection([new TypedResourceTargetSet('example.project', ['2'])]);
        self::assertSame(['private-a', 'public-ref'], array_map(
            static fn($item): string => $item->code,
            $query->candidates($tenant, $projectA, 'use'),
        ));
        self::assertSame(['public-ref'], array_map(
            static fn($item): string => $item->code,
            $query->candidates($tenant, $projectB, 'use'),
        ));

        $createTargets = new TypedResourceTargetCollection([
            new TypedResourceTargetSet('example.project', ['1']),
            new TypedResourceTargetSet('example.queue', ['1'], 'related'),
        ]);
        $workItemId = ($this->app->make(WorkItemCommandService::class))->create(
            $tenant,
            $createTargets,
            new CreateWorkItem('1', '1', '2', 'Fixture work item'),
        );
        self::assertSame('1', $workItemId);

        $page = ($this->app->make(WorkItemQuery::class))->list(
            $tenant,
            new TypedResourceTargetCollection([new TypedResourceTargetSet('example.project', ['1', '2'])]),
        );
        self::assertCount(1, $page->items);
        self::assertSame(1, $page->total);

        $policyId = ($this->app->make(WorkItemPolicyPublisher::class))->publish(
            $tenant,
            new TypedResourceTargetCollection([new TypedResourceTargetSet('example.project', ['1', '2'])]),
            'Fixture policy',
            ['status' => ['open']],
        );
        self::assertSame('1', $policyId);
        self::assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM pa_example_work_item_policy_publication'));
    }

    public function testCategoryConfusionPrivateScopeAndBulkWriteFailClosed(): void
    {
        $tenant = $this->tenantContext();
        $resolver = $this->app->make(DataPermissionRuntimeRegistry::class)->targetResolvers->get(ThinkPhpTargetResolver::class);
        try {
            $resolver->resolveAndValidate($tenant, new TypedResourceTargetSet('example.queue', ['2']));
            self::fail('Project ID must not be interpreted as Queue ID.');
        } catch (ModuleException $exception) {
            self::assertSame('AUTHZ_TARGET_NOT_FOUND', $exception->errorCode);
        }

        $service = $this->app->make(WorkItemCommandService::class);
        try {
            $service->create(
                $tenant,
                new TypedResourceTargetCollection([new TypedResourceTargetSet('example.project', ['1'])]),
                new CreateWorkItem('1', null, '3', 'Denied reference'),
            );
            self::fail('Project A must not use Project C private reference.');
        } catch (ModuleException $exception) {
            self::assertSame('AUTHZ_SHARED_MASTER_SCOPE_DENIED', $exception->errorCode);
        }

        $this->expectException(ModuleException::class);
        $service->bulkWrite();
    }

    private function seedBusinessFixtures(): void
    {
        $now = '2026-07-16 12:00:00.000';
        $tenant = $this->tenantId;
        $this->pdo->exec("INSERT INTO pa_example_project (id, tenant_id, code, name, status, revision, created_at, updated_at) VALUES (1,{$tenant},'A','Project A','active',1,'{$now}','{$now}'),(2,{$tenant},'B','Project B','active',1,'{$now}','{$now}'),(3,{$tenant},'C','Project C','active',1,'{$now}','{$now}')");
        $this->pdo->exec("INSERT INTO pa_example_queue (id, tenant_id, code, name, status, revision, created_at, updated_at) VALUES (1,{$tenant},'A','Queue A','active',1,'{$now}','{$now}')");
        $this->pdo->exec("INSERT INTO pa_example_reference_item (id, owner_type, owner_tenant_id, code, name, status, revision, created_at, updated_at) VALUES (1,'deployment',NULL,'public-ref','Public Reference','active',1,'{$now}','{$now}'),(2,'tenant',{$tenant},'private-a','Private A','active',1,'{$now}','{$now}'),(3,'tenant',{$tenant},'private-c','Private C','active',1,'{$now}','{$now}')");
        $this->pdo->exec("INSERT INTO pa_example_reference_scope (reference_item_id, scope_kind, target_tenant_id, target_resource_key, target_id, capability, status, revision) VALUES (1,'all_tenants',NULL,NULL,NULL,'use','active',1),(2,'typed_target',{$tenant},'example.project','1','use','active',1),(3,'typed_target',{$tenant},'example.project','3','use','active',1)");
    }

    private function seedAuthorization(): void
    {
        $seeder = new ThinkPhpAuthorizationFixtureSeeder();
        $roleId = $seeder->roleForMember($this->tenantId, $this->memberId);
        $seeder->grantPermissions($this->tenantId, $roleId, [
            'example.target.read',
            'example.target.manage',
            'example.reference.read',
            'example.reference.use',
            'example.work-item.read',
            'example.work-item.create',
            'example.work-item.policy-publish',
        ]);
        $readProjects = $seeder->targetSet($this->tenantId, $this->memberId, 'example.project', ['1', '2']);
        $writeProjects = $seeder->targetSet($this->tenantId, $this->memberId, 'example.project', ['1']);
        $queues = $seeder->targetSet($this->tenantId, $this->memberId, 'example.queue', ['1']);
        $seeder->allowTargetGroups(
            $this->tenantId,
            $roleId,
            $this->memberId,
            'example.reference-item',
            'use',
            [['example.project' => $readProjects]],
        );
        $seeder->allowTargetGroups(
            $this->tenantId,
            $roleId,
            $this->memberId,
            'example.work-item',
            'list',
            [['example.project' => $readProjects]],
        );
        $seeder->allowTargetGroups(
            $this->tenantId,
            $roleId,
            $this->memberId,
            'example.work-item',
            'create',
            [
                ['example.project' => $writeProjects],
                ['example.project' => $writeProjects, 'example.queue' => $queues],
            ],
        );
        $seeder->allowTargetGroups(
            $this->tenantId,
            $roleId,
            $this->memberId,
            'example.work-item',
            'policy-publish',
            [['example.project' => $readProjects]],
        );
    }

    private function tenantContext(): TenantContext
    {
        $revision = (int) $this->scalar(
            "SELECT authorization_revision FROM pa_tenant WHERE id = {$this->tenantId}",
        );

        return TenantContext::fromValidatedSession(new ValidatedTenantSession(
            1,
            'fixture-session',
            $this->tenantId,
            $this->accountId,
            $this->memberId,
            'admin-web',
            new DateTimeImmutable('2026-07-16T12:00:00Z'),
            $revision,
        ), 'fixture-request');
    }

    private function scalar(string $sql): mixed
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('The example fixture query could not be prepared.');
        }

        return $statement->fetchColumn();
    }

    private function connect(?string $database = null): PDO
    {
        $dsn = 'mysql:host=127.0.0.1;port=' . (getenv('MYSQL_PORT') ?: '3306')
            . ($database === null ? '' : ";dbname={$database}") . ';charset=utf8mb4';
        return new PDO($dsn, 'root', getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
