<?php

declare(strict_types=1);

use PeanutAdmin\App\command\InstallProductProfile;
use PeanutAdmin\App\command\InstallWorkflow;
use PeanutAdmin\App\authorization\DataPermissionRuntimeFactory;
use PeanutAdmin\App\middleware\TenantAuthRuntimeFactory;
use PeanutAdmin\App\Modules\Example\Reference\Infrastructure\Authorization\PdoReferenceScopeProvider;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization\PdoTargetResolver;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Persistence\PdoTargetQuery;
use PeanutAdmin\App\Modules\Example\WorkItem\Infrastructure\Persistence\PdoWorkItemQuery;
use PeanutAdmin\DataPermission\Context\AuthorizationContext;
use PeanutAdmin\DataPermission\Constraint\PdoQueryConstraintCompiler;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PeanutAdmin\Kernel\Auth\TenantAuthentication;
use PeanutAdmin\Testing\Authorization\PdoAuthorizationFixtureSeeder;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);
$rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: 'peanut_admin_root_dev';
$databaseName = 'peanut_admin_performance_' . getmypid();
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $rootPassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

/** @param callable(): mixed $operation
 *  @return array{p50_ms: float, p95_ms: float, p99_ms: float, samples: int}
 */
function benchmark(callable $operation, int $samples, int $warmups = 2): array
{
    for ($index = 0; $index < $warmups; ++$index) {
        $operation();
    }
    $durations = [];
    for ($index = 0; $index < $samples; ++$index) {
        $started = hrtime(true);
        $operation();
        $durations[] = (hrtime(true) - $started) / 1_000_000;
    }
    sort($durations, SORT_NUMERIC);
    $percentile = static function (float $value) use ($durations): float {
        $position = max(0, min(count($durations) - 1, (int) ceil(count($durations) * $value) - 1));

        return round($durations[$position], 3);
    };

    return [
        'p50_ms' => $percentile(0.50),
        'p95_ms' => $percentile(0.95),
        'p99_ms' => $percentile(0.99),
        'samples' => count($durations),
    ];
}

try {
    $admin->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    putenv("DB_HOST={$host}");
    putenv("DB_PORT={$port}");
    putenv("DB_DATABASE={$databaseName}");
    putenv('DB_USERNAME=root');
    putenv("DB_PASSWORD={$rootPassword}");
    putenv('AUTH_IDENTIFIER_HMAC_KEY=peanut-admin-performance-hmac-key-2026');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        'root',
        $rootPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
    $profile = InstallProductProfile::load(
        $root . '/profiles/reference-admin.json',
        $root . '/schemas/product-profile.schema.json',
    );
    $password = 'Performance-P0-Only-2026!';
    $installation = (new InstallWorkflow($root, $pdo))->run(
        $profile,
        'performance@example.test',
        $password,
        'Performance Owner',
        [
            'code' => 'performance',
            'name' => 'Performance Tenant',
            'owner_email' => 'performance@example.test',
            'owner_name' => 'Performance Owner',
        ],
    );
    $tenantId = (int) ($installation['tenant']['tenant_id'] ?? 0);
    $memberId = (int) ($installation['tenant']['owner_member_id'] ?? 0);
    if ($tenantId <= 0 || $memberId <= 0) {
        throw new RuntimeException('Performance fixture installation did not return a tenant owner.');
    }

    $insertSet = $pdo->prepare(<<<'SQL'
INSERT INTO pa_data_permission_target_set
    (tenant_id, name, target_mode, target_resource_key, created_by_member_id, updated_by_member_id, created_at, updated_at)
VALUES (?, 'P0 performance targets', 'resource', 'example.project', ?, ?, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))
SQL);
    $insertSet->execute([$tenantId, $memberId, $memberId]);
    $targetSetId = (int) $pdo->lastInsertId();
    for ($offset = 1; $offset <= 5000; $offset += 500) {
        $values = [];
        $parameters = [];
        for ($id = $offset; $id < $offset + 500; ++$id) {
            $values[] = '(?, ?, ?, ?, UTC_TIMESTAMP(3))';
            array_push($parameters, $tenantId, $targetSetId, (string) $id, $memberId);
        }
        $sql = sprintf(<<<'SQL'
INSERT INTO pa_data_permission_target
    (tenant_id, target_set_id, target_id, added_by_member_id, added_at)
VALUES %s
SQL, implode(', ', $values));
        $pdo->prepare($sql)->execute($parameters);
    }

    $pdo->prepare(<<<'SQL'
INSERT INTO pa_example_reference_item
    (owner_type, owner_tenant_id, code, name, status, created_at, updated_at)
VALUES ('deployment', NULL, 'P0-REFERENCE', 'P0 Reference', 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))
SQL)->execute();
    $referenceId = (int) $pdo->lastInsertId();
    $pdo->prepare(<<<'SQL'
INSERT INTO pa_example_reference_scope
    (reference_item_id, scope_kind, target_tenant_id, target_resource_key, target_id, capability, status)
VALUES (?, 'typed_target', ?, 'example.project', '1', 'view', 'active')
SQL)->execute([$referenceId, $tenantId]);

    for ($offset = 1; $offset <= 5000; $offset += 500) {
        $projectValues = [];
        $projectParameters = [];
        for ($index = $offset; $index < $offset + 500; ++$index) {
            $projectValues[] = "(?, ?, ?, 'active', UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))";
            $code = 'PERF-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT);
            array_push($projectParameters, $tenantId, $code, $code);
        }
        $pdo->prepare(sprintf(<<<'SQL'
INSERT INTO pa_example_project (tenant_id, code, name, status, created_at, updated_at)
VALUES %s
SQL, implode(', ', $projectValues)))->execute($projectParameters);
    }
    $projectStatement = $pdo->query(
        'SELECT id FROM pa_example_project WHERE tenant_id = ' . $tenantId . ' ORDER BY id',
    );
    if ($projectStatement === false) {
        throw new RuntimeException('Performance fixture projects could not be read.');
    }
    $projectIds = array_values(array_map('strval', $projectStatement->fetchAll(PDO::FETCH_COLUMN)));
    if (count($projectIds) !== 5000) {
        throw new RuntimeException('Performance fixture did not create 5,000 projects.');
    }
    foreach (array_chunk($projectIds, 500) as $chunk) {
        $workItemValues = [];
        $workItemParameters = [];
        foreach ($chunk as $projectId) {
            $workItemValues[] = "(?, ?, NULL, ?, ?, NULL, ?, 'open', ?, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))";
            array_push(
                $workItemParameters,
                $tenantId,
                $projectId,
                $referenceId,
                $memberId,
                'Performance work item ' . $projectId,
                $memberId,
            );
        }
        $pdo->prepare(sprintf(<<<'SQL'
INSERT INTO pa_example_work_item
    (tenant_id, project_id, queue_id, reference_item_id, owner_member_id, department_id,
     title, status, created_by_member_id, created_at, updated_at)
VALUES %s
SQL, implode(', ', $workItemValues)))->execute($workItemParameters);
    }

    $authorizationFixture = new PdoAuthorizationFixtureSeeder($pdo);
    $roleId = $authorizationFixture->roleForMember($tenantId, $memberId);
    $authorizationFixture->grantPermissions($tenantId, $roleId, ['example.work-item.read']);
    $authorizationFixture->allowTargetGroups(
        $tenantId,
        $roleId,
        $memberId,
        'example.work-item',
        'list',
        [['example.project' => $targetSetId]],
    );

    $auth = TenantAuthRuntimeFactory::create(pdo: $pdo);
    $initial = $auth->login(
        'performance@example.test',
        $password,
        'performance',
        '127.0.0.1',
        'Peanut performance gate',
        'perf-fixture-context',
    );
    if (!$initial instanceof TenantAuthentication) {
        throw new RuntimeException('Performance fixture login unexpectedly required tenant selection.');
    }
    $resolver = new PdoTargetResolver($pdo);
    $authorization = DataPermissionRuntimeFactory::create($pdo, $root);
    $workItems = new PdoWorkItemQuery($pdo, $authorization, new PdoTargetQuery($pdo));
    $results = [];
    foreach ([10, 500, 5000] as $size) {
        $ids = array_slice($projectIds, 0, $size);
        $typedSet = new TypedResourceTargetSet('example.project', $ids);
        $typedTargets = new TypedResourceTargetCollection([$typedSet]);
        $compiled = (new PdoQueryConstraintCompiler())->compile($authorization->queryConstraint(
            $initial->context,
            'example.work-item',
            'list',
            $typedTargets,
        ));
        $resolved = $resolver->resolveAndValidate($initial->context, $typedSet);
        if (count($resolved->targets->sets[0]->targetIds) !== $size) {
            throw new RuntimeException("Typed-target resolver changed the {$size}-target set.");
        }
        $operationsPerSample = match ($size) {
            10 => 30,
            500 => 10,
            default => 3,
        };
        $verify = static function () use (
            $workItems,
            $initial,
            $typedTargets,
            $size,
        ): void {
            $page = $workItems->list($initial->context, $typedTargets, 1, 20);
            if ($page->total !== $size || count($page->items) !== min(20, $size)) {
                throw new RuntimeException("Work-item query returned the wrong {$size}-target page.");
            }
        };
        $verify();
        $results["typed-targets-{$size}"] = benchmark(
            static function () use ($verify, $operationsPerSample): void {
                for ($index = 0; $index < $operationsPerSample; ++$index) {
                    $verify();
                }
            },
            30,
            3,
        ) + [
            'operations_per_sample' => $operationsPerSample,
            'sql_parameters_per_query' => count($compiled->parameters),
            'page_size' => 20,
        ];
    }
    try {
        $resolver->resolveAndValidate(
            $initial->context,
            new TypedResourceTargetSet('example.project', ['5001']),
        );
        throw new RuntimeException('Typed-target resolver accepted a missing target.');
    } catch (\PeanutAdmin\Kernel\Module\ModuleException $exception) {
        if ($exception->errorCode !== 'AUTHZ_TARGET_NOT_FOUND') {
            throw $exception;
        }
    }

    $loginTokens = [];
    $results['tenant-login'] = benchmark(
        static function () use ($auth, $password, &$loginTokens): void {
            $authentication = $auth->login(
                'performance@example.test',
                $password,
                'performance',
                '127.0.0.1',
                'Peanut performance gate',
                'perf-login-' . count($loginTokens),
            );
            if (!$authentication instanceof TenantAuthentication) {
                throw new RuntimeException('Performance login unexpectedly required tenant selection.');
            }
            $loginTokens[] = $authentication;
        },
        20,
        0,
    );
    $refreshIndex = 0;
    $refreshed = [];
    $results['tenant-refresh'] = benchmark(
        static function () use ($auth, &$loginTokens, &$refreshIndex, &$refreshed): void {
            $authentication = $loginTokens[$refreshIndex++];
            $refreshed[] = $auth->refresh(
                $authentication->tokens->refresh->expose(),
                '127.0.0.1',
                'Peanut performance gate',
                'perf-refresh-' . $refreshIndex,
            );
        },
        20,
        0,
    );
    $accessToken = $refreshed[0]->tokens->access->expose();
    $results['tenant-context'] = benchmark(
        static function () use ($auth, $accessToken): void {
            for ($index = 0; $index < 20; ++$index) {
                $auth->context($accessToken, 'perf-context-' . $index);
            }
        },
        30,
    ) + ['operations_per_sample' => 20];
    $tenantContext = $refreshed[0]->context;
    $authorizationContext = new AuthorizationContext($tenantContext, null);
    $targets = new TypedResourceTargetCollection([
        new TypedResourceTargetSet('example.project', array_map('strval', range(1, 10))),
    ]);
    $scope = new PdoReferenceScopeProvider($pdo);
    $results['shared-master-scope'] = benchmark(
        static function () use ($scope, $authorizationContext, $targets, $referenceId): void {
            for ($index = 0; $index < 20; ++$index) {
                if ($scope->allowedIds($authorizationContext, $targets, 'view') !== [(string) $referenceId]) {
                    throw new RuntimeException('Shared-master scope returned a different candidate set.');
                }
            }
        },
        20,
    ) + ['operations_per_sample' => 20, 'query_upper_bound_per_operation' => 1, 'typed_targets' => 10];

    $versionStatement = $pdo->query('SELECT VERSION()');
    if ($versionStatement === false) {
        throw new RuntimeException('MySQL version could not be read.');
    }
    $mysqlVersion = (string) $versionStatement->fetchColumn();
    fwrite(STDOUT, json_encode([
        'schema_version' => 3,
        'environment' => [
            'database_image' => 'mysql:8.4.10',
            'database_version' => $mysqlVersion,
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
            'architecture' => php_uname('m'),
        ],
        'scenarios' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: performance qualification failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
}
