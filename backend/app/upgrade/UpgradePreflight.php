<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final class UpgradePreflight
{
    public function run(
        ReleaseManifest $release,
        BackupManifest $backup,
        RepositoryState $repository,
        MigrationInventory $actualTargetMigrations,
        string $environment,
    ): UpgradePlan {
        return UpgradePlan::validated(
            $release,
            $backup,
            $repository,
            $actualTargetMigrations,
            $environment,
        );
    }
}
