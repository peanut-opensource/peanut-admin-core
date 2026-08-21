<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Upgrade;

use PeanutAdmin\App\upgrade\BackupManifest;
use PeanutAdmin\App\upgrade\ExecutionReport;
use PeanutAdmin\App\upgrade\MigrationInventory;
use PeanutAdmin\App\upgrade\ReleaseManifest;
use PeanutAdmin\App\upgrade\RepositoryInspector;
use PeanutAdmin\App\upgrade\RepositoryState;
use PeanutAdmin\App\upgrade\TargetMigrationInventory;
use PeanutAdmin\App\upgrade\UpgradeFailure;
use PeanutAdmin\App\upgrade\UpgradePreflight;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class UpgradeLifecycleTest extends TestCase
{
    private const SOURCE_COMMIT = '1111111111111111111111111111111111111111';
    private const SOURCE_TREE = '2222222222222222222222222222222222222222';
    private const TARGET_COMMIT = '3333333333333333333333333333333333333333';
    private const TARGET_TREE = '4444444444444444444444444444444444444444';

    public function testPreflightFixesReleaseBackupRepositoryAndAppendOnlyMigrationPlan(): void
    {
        $plan = (new UpgradePreflight())->run(
            $this->release(),
            $this->backup(),
            new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, true),
            new MigrationInventory($this->targetMigrations()),
            'staging',
        );

        self::assertSame('release-stage-a', $plan->releaseId);
        self::assertSame('backup-before-stage-a', $plan->backupId);
        self::assertSame(['kernel:002_add_member'], $plan->pendingMigrationKeys);
        self::assertSame(1, $plan->migrationPlan['source_count']);
        self::assertSame(2, $plan->migrationPlan['target_count']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $plan->migrationPlan['digest']);
    }

    /** @param callable(): array{ReleaseManifest, BackupManifest, RepositoryState, MigrationInventory, string} $case */
    #[DataProvider('failureCases')]
    public function testPreflightFailsClosed(callable $case, string $expectedCode): void
    {
        [$release, $backup, $repository, $inventory, $environment] = $case();

        try {
            (new UpgradePreflight())->run($release, $backup, $repository, $inventory, $environment);
        } catch (UpgradeFailure $failure) {
            self::assertSame($expectedCode, $failure->errorCode);
            self::assertSame('Upgrade preflight failed.', $failure->getMessage());

            return;
        }

        self::fail("Expected {$expectedCode}.");
    }

    /** @return iterable<string, array{callable(): array{ReleaseManifest, BackupManifest, RepositoryState, MigrationInventory, string}, string}> */
    public static function failureCases(): iterable
    {
        yield 'dirty target' => [static function (): array {
            $test = new self('testPreflightFailsClosed');

            return [
                $test->release(),
                $test->backup(),
                new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, false),
                new MigrationInventory($test->targetMigrations()),
                'staging',
            ];
        }, 'UPGRADE_WORKTREE_DIRTY'];

        yield 'wrong target commit' => [static function (): array {
            $test = new self('testPreflightFailsClosed');

            return [
                $test->release(),
                $test->backup(),
                new RepositoryState(str_repeat('9', 40), self::TARGET_TREE, true),
                new MigrationInventory($test->targetMigrations()),
                'staging',
            ];
        }, 'UPGRADE_TARGET_MISMATCH'];

        yield 'backup from another source' => [static function (): array {
            $test = new self('testPreflightFailsClosed');

            return [
                $test->release(),
                $test->backup(str_repeat('8', 40)),
                new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, true),
                new MigrationInventory($test->targetMigrations()),
                'staging',
            ];
        }, 'UPGRADE_SOURCE_MISMATCH'];

        yield 'backup from another environment' => [static function (): array {
            $test = new self('testPreflightFailsClosed');

            return [
                $test->release(),
                $test->backup(environment: 'production'),
                new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, true),
                new MigrationInventory($test->targetMigrations()),
                'staging',
            ];
        }, 'UPGRADE_ENVIRONMENT_MISMATCH'];

        yield 'historical migration missing' => [static function (): array {
            $test = new self('testPreflightFailsClosed');
            $target = [$test->targetMigrations()[1]];

            return [
                $test->release(target: $target),
                $test->backup(),
                new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, true),
                new MigrationInventory($target),
                'staging',
            ];
        }, 'UPGRADE_MIGRATION_MISSING'];

        yield 'historical migration rewritten' => [static function (): array {
            $test = new self('testPreflightFailsClosed');
            $target = $test->targetMigrations();
            $target[0] = [
                'owner' => $target[0]['owner'],
                'key' => $target[0]['key'],
                'checksum' => hash('sha256', 'rewritten'),
            ];

            return [
                $test->release(target: $target),
                $test->backup(),
                new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, true),
                new MigrationInventory($target),
                'staging',
            ];
        }, 'UPGRADE_MIGRATION_REWRITTEN'];

        yield 'backdated migration inserted into history' => [static function (): array {
            $test = new self('testPreflightFailsClosed');
            $target = [
                ['owner' => 'kernel', 'key' => '000_backdated', 'checksum' => hash('sha256', 'backdated')],
                ...$test->targetMigrations(),
            ];

            return [
                $test->release(target: $target),
                $test->backup(),
                new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, true),
                new MigrationInventory($target),
                'staging',
            ];
        }, 'UPGRADE_MIGRATION_BACKDATED'];

        yield 'release inventory differs from target tree' => [static function (): array {
            $test = new self('testPreflightFailsClosed');

            return [
                $test->release(),
                $test->backup(),
                new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, true),
                new MigrationInventory([$test->targetMigrations()[0]]),
                'staging',
            ];
        }, 'UPGRADE_RELEASE_MANIFEST_MISMATCH'];
    }

    public function testFailureReportIsStableAndDoesNotIncludeThrowableDetails(): void
    {
        $plan = (new UpgradePreflight())->run(
            $this->release(),
            $this->backup(),
            new RepositoryState(self::TARGET_COMMIT, self::TARGET_TREE, true),
            new MigrationInventory($this->targetMigrations()),
            'staging',
        );
        $report = ExecutionReport::failure($plan, 'MODULE_MIGRATION_FAILED');
        $json = json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        self::assertSame(1, $report['schema_version']);
        self::assertSame('failed', $report['status']);
        self::assertSame('passed', $report['preflight']['status']);
        self::assertSame(true, $report['execution']['performed']);
        self::assertSame(false, $report['recovery']['automatic_ddl_rollback']);
        self::assertSame(hash('sha256', 'backup'), $report['recovery']['backup_artifact_sha256']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $report['recovery']['release_manifest_sha256']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $report['recovery']['backup_manifest_sha256']);
        self::assertSame('Restore the verified backup with the matching source release.', $report['recovery']['operator_action']);
        self::assertStringNotContainsString('mysql:', $json);
        self::assertStringNotContainsString('/Users/', $json);
        self::assertStringNotContainsString('SELECT ', $json);
    }

    public function testBackupManifestRejectsANonRfc3339Timestamp(): void
    {
        $this->expectException(UpgradeFailure::class);
        BackupManifest::fromArray([
            'schema_version' => 1,
            'backup_id' => 'invalid-time',
            'environment' => 'staging',
            'source' => ['commit' => self::SOURCE_COMMIT, 'tree' => self::SOURCE_TREE],
            'artifact_sha256' => hash('sha256', 'backup'),
            'created_at' => '2026-07-24 00:00:00',
            'verified_at' => '2026-07-24T00:10:00Z',
            'restore_tested_at' => '2026-07-24T00:20:00Z',
        ]);
    }

    public function testTargetInventoryRejectsASymlinkedMigrationFile(): void
    {
        $root = sys_get_temp_dir() . '/peanut-upgrade-inventory-' . bin2hex(random_bytes(8));
        $external = $root . '-external.php';
        try {
            self::assertTrue(mkdir($root . '/packages/php/kernel/database/migrations', 0700, true));
            self::assertTrue(mkdir($root . '/packages/php/data-permission/database/migrations', 0700, true));
            self::assertTrue(mkdir($root . '/backend/config', 0700, true));
            self::assertNotFalse(file_put_contents($root . '/backend/config/modules.php', "<?php return ['roots' => []];\n"));
            self::assertNotFalse(file_put_contents($external, "<?php\n"));
            self::assertTrue(symlink(
                $external,
                $root . '/packages/php/kernel/database/migrations/20260724010101_external.php',
            ));

            $this->expectException(UpgradeFailure::class);
            (new TargetMigrationInventory())->scan($root);
        } finally {
            @unlink($root . '/packages/php/kernel/database/migrations/20260724010101_external.php');
            @unlink($root . '/backend/config/modules.php');
            @rmdir($root . '/packages/php/kernel/database/migrations');
            @rmdir($root . '/packages/php/kernel/database');
            @rmdir($root . '/packages/php/kernel');
            @rmdir($root . '/packages/php/data-permission/database/migrations');
            @rmdir($root . '/packages/php/data-permission/database');
            @rmdir($root . '/packages/php/data-permission');
            @rmdir($root . '/packages/php');
            @rmdir($root . '/packages');
            @rmdir($root . '/backend/config');
            @rmdir($root . '/backend');
            @rmdir($root);
            @unlink($external);
        }
    }

    public function testUpgradePlanIsOpaqueAndProductionWorkflowHasNoVerifierInjection(): void
    {
        $plan = new ReflectionClass(\PeanutAdmin\App\upgrade\UpgradePlan::class);
        self::assertTrue($plan->getConstructor()?->isPrivate());

        $workflow = new ReflectionClass(\PeanutAdmin\App\command\UpgradeWorkflow::class);
        self::assertCount(2, $workflow->getConstructor()?->getParameters() ?? []);
    }

    public function testReleaseInspectionRejectsAPackageWithoutGitMetadata(): void
    {
        $root = sys_get_temp_dir() . '/peanut-upgrade-no-git-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root, 0700, true));
        try {
            $this->expectException(UpgradeFailure::class);
            $this->expectExceptionMessage('Upgrade preflight failed.');
            (new RepositoryInspector())->inspectRelease($root, $this->release());
        } finally {
            rmdir($root);
        }
    }

    public function testTargetInventoryRejectsASymlinkedModuleConfig(): void
    {
        $root = sys_get_temp_dir() . '/peanut-upgrade-config-' . bin2hex(random_bytes(8));
        $external = $root . '-modules.php';
        try {
            self::assertTrue(mkdir($root . '/packages/php/kernel/database/migrations', 0700, true));
            self::assertTrue(mkdir($root . '/packages/php/data-permission/database/migrations', 0700, true));
            self::assertTrue(mkdir($root . '/backend/config', 0700, true));
            self::assertNotFalse(file_put_contents($external, "<?php return ['roots' => []];\n"));
            self::assertTrue(symlink($external, $root . '/backend/config/modules.php'));

            $this->expectException(UpgradeFailure::class);
            (new TargetMigrationInventory())->scan($root);
        } finally {
            @unlink($root . '/backend/config/modules.php');
            @rmdir($root . '/packages/php/kernel/database/migrations');
            @rmdir($root . '/packages/php/kernel/database');
            @rmdir($root . '/packages/php/kernel');
            @rmdir($root . '/packages/php/data-permission/database/migrations');
            @rmdir($root . '/packages/php/data-permission/database');
            @rmdir($root . '/packages/php/data-permission');
            @rmdir($root . '/packages/php');
            @rmdir($root . '/packages');
            @rmdir($root . '/backend/config');
            @rmdir($root . '/backend');
            @rmdir($root);
            @unlink($external);
        }
    }

    /** @param list<array{owner: string, key: string, checksum: string}>|null $target */
    private function release(?array $target = null): ReleaseManifest
    {
        return ReleaseManifest::fromArray([
            'schema_version' => 1,
            'release_id' => 'release-stage-a',
            'source' => ['commit' => self::SOURCE_COMMIT, 'tree' => self::SOURCE_TREE],
            'target' => ['commit' => self::TARGET_COMMIT, 'tree' => self::TARGET_TREE],
            'migrations' => [
                'source' => [$this->targetMigrations()[0]],
                'target' => $target ?? $this->targetMigrations(),
            ],
        ]);
    }

    private function backup(
        string $sourceCommit = self::SOURCE_COMMIT,
        string $environment = 'staging',
    ): BackupManifest {
        return BackupManifest::fromArray([
            'schema_version' => 1,
            'backup_id' => 'backup-before-stage-a',
            'environment' => $environment,
            'source' => ['commit' => $sourceCommit, 'tree' => self::SOURCE_TREE],
            'artifact_sha256' => hash('sha256', 'backup'),
            'created_at' => '2026-07-24T00:00:00Z',
            'verified_at' => '2026-07-24T00:10:00Z',
            'restore_tested_at' => '2026-07-24T00:20:00Z',
        ]);
    }

    /** @return list<array{owner: string, key: string, checksum: string}> */
    private function targetMigrations(): array
    {
        return [
            ['owner' => 'kernel', 'key' => '001_create_account', 'checksum' => hash('sha256', 'one')],
            ['owner' => 'kernel', 'key' => '002_add_member', 'checksum' => hash('sha256', 'two')],
        ];
    }
}
