<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final class ExecutionReport
{
    /** @param array{modules: list<string>, applied_module_migrations: int} $result
     *  @return array<string, mixed>
     */
    public static function success(UpgradePlan $plan, array $result, bool $preflightOnly = false): array
    {
        return [
            'schema_version' => 1,
            'status' => $preflightOnly ? 'preflight_passed' : 'succeeded',
            'release_id' => $plan->releaseId,
            'environment' => $plan->environment,
            'source' => $plan->source,
            'target' => $plan->target,
            'preflight' => ['status' => 'passed', 'migration_plan' => $plan->migrationPlan],
            'execution' => [
                'performed' => !$preflightOnly,
                'modules' => $result['modules'],
                'applied_module_migrations' => $result['applied_module_migrations'],
            ],
            'modules' => $result['modules'],
            'applied_module_migrations' => $result['applied_module_migrations'],
            'recovery' => self::recovery($plan),
            'error' => null,
        ];
    }

    /** @return array<string, mixed> */
    public static function failure(UpgradePlan $plan, string $errorCode): array
    {
        return [
            'schema_version' => 1,
            'status' => 'failed',
            'release_id' => $plan->releaseId,
            'environment' => $plan->environment,
            'source' => $plan->source,
            'target' => $plan->target,
            'preflight' => ['status' => 'passed'],
            'execution' => ['performed' => true, 'status' => 'failed'],
            'recovery' => self::recovery($plan),
            'error' => ['code' => $errorCode, 'message' => 'Upgrade did not complete.'],
        ];
    }

    /** @return array<string, mixed> */
    private static function recovery(UpgradePlan $plan): array
    {
        return [
            'required_on_failure' => true,
            'backup_id' => $plan->backupId,
            'source' => $plan->source,
            'backup_artifact_sha256' => $plan->backupArtifactSha256,
            'backup_created_at' => $plan->backupCreatedAt,
            'backup_verified_at' => $plan->backupVerifiedAt,
            'backup_restore_tested_at' => $plan->backupRestoreTestedAt,
            'release_manifest_sha256' => $plan->releaseManifestDigest,
            'backup_manifest_sha256' => $plan->backupManifestDigest,
            'automatic_ddl_rollback' => false,
            'operator_action' => 'Restore the verified backup with the matching source release.',
        ];
    }
}
