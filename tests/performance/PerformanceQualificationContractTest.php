<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PerformanceQualificationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testVersionedBaselineCoversSecurityCriticalScenarios(): void
    {
        $path = $this->root . '/tests/performance/p0-baseline.json';
        self::assertFileExists($path);
        $baseline = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(3, $baseline['schema_version'] ?? null);
        self::assertSame(1.2, $baseline['maximum_regression_ratio'] ?? null);
        self::assertSame('mysql:8.4.10', $baseline['environment']['database_image'] ?? null);
        self::assertSame('authorized-structured-target-query-v2', $baseline['environment']['operation_shape'] ?? null);

        $scenarios = $baseline['scenarios'] ?? [];
        self::assertIsArray($scenarios);
        foreach (['typed-targets-10', 'typed-targets-500', 'typed-targets-5000', 'shared-master-scope'] as $scenario) {
            self::assertArrayHasKey($scenario, $scenarios);
            self::assertIsFloat($scenarios[$scenario]['p95_ms'] ?? null);
            self::assertGreaterThan(0, $scenarios[$scenario]['p95_ms']);
            if (str_starts_with($scenario, 'typed-targets-')) {
                self::assertIsInt($scenarios[$scenario]['operations_per_sample'] ?? null);
                self::assertIsInt($scenarios[$scenario]['sql_parameters_per_query'] ?? null);
                self::assertSame(20, $scenarios[$scenario]['page_size'] ?? null);
            }
        }
    }

    public function testPerformanceGateIsPartOfTheRepositoryGate(): void
    {
        $script = $this->root . '/scripts/test-performance';
        self::assertFileExists($script);
        self::assertTrue(is_executable($script), $script);
        self::assertFileExists($this->root . '/docs/performance/p0-baseline.md');
        self::assertStringContainsString(
            './scripts/test-performance',
            (string) file_get_contents($this->root . '/scripts/check'),
        );
    }

    public function testCiUsesTheFixedPhpPerformanceImage(): void
    {
        $dockerfile = (string) file_get_contents($this->root . '/docker/php/Dockerfile');
        self::assertStringStartsWith("FROM php:8.3.24-cli-bookworm\n", $dockerfile);

        $script = (string) file_get_contents($this->root . '/scripts/test-performance');
        self::assertStringContainsString('PEANUT_PERFORMANCE_PHP_IMAGE', $script);
        self::assertStringContainsString('docker compose ps -q mysql', $script);
        self::assertStringContainsString(".State.Health.Status", $script);
        self::assertStringContainsString('docker run --rm --network "container:${mysql_container}"', $script);
        self::assertStringContainsString('--user "$(id -u):$(id -g)"', $script);
        self::assertStringContainsString("--env 'DB_HOST=127.0.0.1'", $script);
        self::assertStringContainsString("--env 'DB_PORT=3306'", $script);
        self::assertStringContainsString("--volume \"\$phpunit_cache:/tmp/peanut-admin-phpunit-cache\"", $script);
        self::assertStringContainsString("report_argument='/tmp/peanut-admin-phpunit-cache/performance-report.json'", $script);
        self::assertStringContainsString('--cache-directory "$phpunit_cache_argument"', $script);
        self::assertStringNotContainsString('docker run --rm --network host', $script);
        self::assertStringNotContainsString("catch (Throwable) {\n        exit(1);", $script);

        $workflow = (string) file_get_contents($this->root . '/.github/workflows/performance.yml');
        self::assertStringContainsString('docker build --tag peanut-admin-performance-php:8.3.24', $workflow);
        self::assertStringContainsString('PEANUT_PERFORMANCE_PHP_IMAGE: peanut-admin-performance-php:8.3.24', $workflow);

        $qualityWorkflow = (string) file_get_contents($this->root . '/.github/workflows/ci.yml');
        self::assertStringContainsString('docker build --tag peanut-admin-performance-php:8.3.24', $qualityWorkflow);
        self::assertStringContainsString('PEANUT_PERFORMANCE_PHP_IMAGE: peanut-admin-performance-php:8.3.24', $qualityWorkflow);
    }

    public function testTypedTargetBenchmarkUsesTheRealResolverAndPaginatedQuery(): void
    {
        $runner = (string) file_get_contents($this->root . '/tests/performance/run.php');
        self::assertStringContainsString('ThinkPhpTargetResolver', $runner);
        self::assertStringContainsString('$app->make(WorkItemQuery::class)', $runner);
        self::assertStringContainsString("->list(\$initial->context, \$typedTargets, 1, 20)", $runner);

        $resolver = (string) file_get_contents(
            $this->root . '/backend/app/modules/example/target/infrastructure/authorization/ThinkPhpTargetResolver.php',
        );
        self::assertStringContainsString("::scope('tenant',", $resolver);
        $sharedMaster = (string) file_get_contents(
            $this->root . '/backend/app/modules/example/reference/infrastructure/authorization/ThinkPhpReferenceScopeProvider.php',
        );
        self::assertStringContainsString('ReferenceItem::alias(', $sharedMaster);
        self::assertStringNotContainsString('PDO', $resolver . $sharedMaster);
        $applier = (string) file_get_contents(
            $this->root . '/packages/php/data-permission/src/Constraint/ThinkPhpQueryConstraintApplier.php',
        );
        self::assertStringContainsString('JSON_TABLE', $applier);
        $query = (string) file_get_contents(
            $this->root . '/backend/app/modules/example/work_item/infrastructure/persistence/ThinkPhpWorkItemQuery.php',
        );
        self::assertStringContainsString('queryConstraint', $query);
        self::assertStringContainsString('ThinkPhpQueryConstraintApplier', $query);
        $catalog = (string) file_get_contents(
            $this->root . '/backend/app/modules/example/target/infrastructure/authorization/ThinkPhpTargetCatalogProvider.php',
        );
        self::assertStringContainsString('EffectivePolicySet', $catalog);
        self::assertStringNotContainsString('allowedTargetIds', $catalog);
    }

    public function testTenantRefreshUsesTwentyExistingLoginTokens(): void
    {
        $runner = (string) file_get_contents($this->root . '/tests/performance/run.php');
        $matched = preg_match(
            '/\$results\[\'tenant-refresh\'\]\s*=\s*benchmark\(.*?\n\s*(\d+),\s*0,\s*\n\s*\);/s',
            $runner,
            $refreshBenchmark,
        );

        self::assertSame(1, $matched);
        self::assertSame('20', $refreshBenchmark[1]);
        self::assertStringContainsString('$authentication = $loginTokens[$refreshIndex++];', $runner);
    }
}
