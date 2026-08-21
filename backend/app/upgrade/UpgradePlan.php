<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final readonly class UpgradePlan
{
    /** @param array{commit: string, tree: string} $source
     *  @param array{commit: string, tree: string} $target
     *  @param list<string> $pendingMigrationKeys
     *  @param array{source_count: int, target_count: int, pending_count: int, digest: string} $migrationPlan
     */
    private function __construct(
        public string $releaseId,
        public string $environment,
        public array $source,
        public array $target,
        public string $backupId,
        public string $backupArtifactSha256,
        public string $backupCreatedAt,
        public string $backupVerifiedAt,
        public string $backupRestoreTestedAt,
        public string $releaseManifestDigest,
        public string $backupManifestDigest,
        public MigrationInventory $sourceMigrations,
        public MigrationInventory $targetMigrations,
        public array $pendingMigrationKeys,
        public array $migrationPlan,
    ) {}

    public static function validated(
        ReleaseManifest $release,
        BackupManifest $backup,
        RepositoryState $repository,
        MigrationInventory $actualTargetMigrations,
        string $environment,
    ): self {
        if (!$repository->clean) {
            throw new UpgradeFailure('UPGRADE_WORKTREE_DIRTY');
        }
        if ($repository->commit !== $release->target['commit'] || $repository->tree !== $release->target['tree']) {
            throw new UpgradeFailure('UPGRADE_TARGET_MISMATCH');
        }
        if ($backup->source !== $release->source) {
            throw new UpgradeFailure('UPGRADE_SOURCE_MISMATCH');
        }
        if ($environment === '' || $backup->environment !== $environment) {
            throw new UpgradeFailure('UPGRADE_ENVIRONMENT_MISMATCH');
        }
        $pending = $release->sourceMigrations->assertAppendOnlyTo($release->targetMigrations);
        if (!hash_equals($release->targetMigrations->digest(), $actualTargetMigrations->digest())) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_MISMATCH');
        }

        return new self(
            $release->releaseId,
            $environment,
            $release->source,
            $release->target,
            $backup->backupId,
            $backup->artifactSha256,
            $backup->createdAt,
            $backup->verifiedAt,
            $backup->restoreTestedAt,
            $release->manifestDigest,
            $backup->manifestDigest,
            $release->sourceMigrations,
            $release->targetMigrations,
            $pending,
            self::migrationPlan($release->sourceMigrations, $release->targetMigrations, $pending),
        );
    }

    public function assertInternallyConsistent(): void
    {
        $pending = $this->sourceMigrations->assertAppendOnlyTo($this->targetMigrations);
        $migrationPlan = self::migrationPlan($this->sourceMigrations, $this->targetMigrations, $pending);
        if ($pending !== $this->pendingMigrationKeys || $migrationPlan !== $this->migrationPlan) {
            throw new UpgradeFailure('UPGRADE_PLAN_INVALID');
        }
    }

    /** @param list<string> $pending
     *  @return array{source_count: int, target_count: int, pending_count: int, digest: string}
     */
    private static function migrationPlan(
        MigrationInventory $source,
        MigrationInventory $target,
        array $pending,
    ): array {
        return [
            'source_count' => count($source->entries),
            'target_count' => count($target->entries),
            'pending_count' => count($pending),
            'digest' => $target->digest(),
        ];
    }
}
