<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use PeanutAdmin\App\upgrade\BackupManifest;
use PeanutAdmin\App\upgrade\ExecutionReport;
use PeanutAdmin\App\upgrade\ReleaseManifest;
use PeanutAdmin\App\upgrade\RepositoryInspector;
use PeanutAdmin\App\upgrade\TargetMigrationInventory;
use PeanutAdmin\App\upgrade\UpgradeFailure;
use PeanutAdmin\App\upgrade\UpgradePlan;
use PeanutAdmin\App\upgrade\UpgradePreflight;
use PeanutAdmin\Kernel\Module\ModuleException;
use Throwable;

final class UpgradeCli
{
    private function __construct() {}

    /** @param list<string> $arguments */
    public static function run(string $root, array $arguments = []): int
    {
        $plan = null;
        try {
            (new InstallEnvironmentChecker($root))->assertReady();
            if ($arguments === ['--print-target-inventory']) {
                $repository = (new RepositoryInspector())->inspect($root);
                if (!$repository->clean) {
                    throw new UpgradeFailure('UPGRADE_WORKTREE_DIRTY');
                }
                $inventory = (new TargetMigrationInventory())->scan($root);
                fwrite(STDOUT, json_encode([
                    'schema_version' => 1,
                    'target' => ['commit' => $repository->commit, 'tree' => $repository->tree],
                    'migration_inventory' => $inventory->entries,
                    'migration_inventory_sha256' => $inventory->digest(),
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

                return 0;
            }
            $options = self::options($arguments);
            if ($options === null) {
                $result = UpgradeWorkflow::fromEnvironment($root)->assertCurrentReleaseNoop();
                fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

                return 0;
            }
            $release = ReleaseManifest::fromFile($options['release_manifest']);
            $backup = BackupManifest::fromFile($options['backup_manifest']);
            $repositoryInspector = new RepositoryInspector();
            $plan = (new UpgradePreflight())->run(
                $release,
                $backup,
                $repositoryInspector->inspectRelease($root, $release),
                (new TargetMigrationInventory())->scan($root),
                $options['environment'],
            );
            if ($options['preflight_only']) {
                $report = ExecutionReport::success($plan, ['modules' => [], 'applied_module_migrations' => 0], true);
            } else {
                $report = ExecutionReport::success(
                    $plan,
                    UpgradeWorkflow::fromEnvironment($root)->run($plan),
                );
            }
            fwrite(STDOUT, json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

            return 0;
        } catch (Throwable $exception) {
            $errorCode = $exception instanceof UpgradeFailure || $exception instanceof ModuleException
                ? $exception->errorCode
                : 'UPGRADE_EXECUTION_FAILED';
            $report = $plan instanceof UpgradePlan
                ? ExecutionReport::failure($plan, $errorCode)
                : [
                    'schema_version' => 1,
                    'status' => 'failed',
                    'error' => ['code' => $errorCode, 'message' => 'Upgrade did not complete.'],
                    'recovery' => [
                        'automatic_ddl_rollback' => false,
                        'operator_action' => 'Resolve preflight evidence before retrying.',
                    ],
                ];
            fwrite(STDERR, json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

            return 1;
        }
    }

    /** @param list<string> $arguments
     *  @return array{release_manifest: string, backup_manifest: string, environment: string, preflight_only: bool}|null
     */
    private static function options(array $arguments): ?array
    {
        if ($arguments === []) {
            return null;
        }
        $options = ['preflight_only' => false];
        for ($index = 0; $index < count($arguments); $index++) {
            $argument = $arguments[$index];
            if ($argument === '--preflight-only') {
                $options['preflight_only'] = true;
                continue;
            }
            if (!in_array($argument, ['--release-manifest', '--backup-manifest', '--environment'], true)) {
                throw new UpgradeFailure('UPGRADE_ARGUMENT_INVALID');
            }
            $value = $arguments[++$index] ?? null;
            if (!is_string($value) || $value === '') {
                throw new UpgradeFailure('UPGRADE_ARGUMENT_INVALID');
            }
            $key = match ($argument) {
                '--release-manifest' => 'release_manifest',
                '--backup-manifest' => 'backup_manifest',
                '--environment' => 'environment',
            };
            $options[$key] = $value;
        }
        $releaseManifest = $options['release_manifest'] ?? null;
        $backupManifest = $options['backup_manifest'] ?? null;
        $environment = $options['environment'] ?? null;
        if (!is_string($releaseManifest)
            || !is_string($backupManifest)
            || !is_string($environment)) {
            throw new UpgradeFailure('UPGRADE_ARGUMENT_INVALID');
        }

        return [
            'release_manifest' => $releaseManifest,
            'backup_manifest' => $backupManifest,
            'environment' => $environment,
            'preflight_only' => $options['preflight_only'],
        ];
    }
}
