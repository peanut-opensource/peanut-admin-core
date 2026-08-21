<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final class RepositoryUpgradeTargetVerifier
{
    public function verify(string $root, UpgradePlan $plan): void
    {
        $release = ReleaseManifest::fromArray([
            'schema_version' => 1,
            'release_id' => $plan->releaseId,
            'source' => $plan->source,
            'target' => $plan->target,
            'migrations' => [
                'source' => $plan->sourceMigrations->entries,
                'target' => $plan->targetMigrations->entries,
            ],
        ]);
        if (!hash_equals($plan->releaseManifestDigest, $release->manifestDigest)) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_MISMATCH');
        }
        $backup = BackupManifest::fromArray([
            'schema_version' => 1,
            'backup_id' => $plan->backupId,
            'environment' => $plan->environment,
            'source' => $plan->source,
            'artifact_sha256' => $plan->backupArtifactSha256,
            'created_at' => $plan->backupCreatedAt,
            'verified_at' => $plan->backupVerifiedAt,
            'restore_tested_at' => $plan->backupRestoreTestedAt,
        ]);
        if (!hash_equals($plan->backupManifestDigest, $backup->manifestDigest)) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }
        $repository = (new RepositoryInspector())->inspectRelease($root, $release);
        if (!$repository->clean) {
            throw new UpgradeFailure('UPGRADE_WORKTREE_DIRTY');
        }
        if ($repository->commit !== $plan->target['commit'] || $repository->tree !== $plan->target['tree']) {
            throw new UpgradeFailure('UPGRADE_TARGET_MISMATCH');
        }
        $actual = (new TargetMigrationInventory())->scan($root);
        if (!hash_equals($plan->targetMigrations->digest(), $actual->digest())) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_MISMATCH');
        }
    }
}
