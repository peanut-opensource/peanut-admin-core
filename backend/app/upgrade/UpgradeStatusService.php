<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

use PeanutAdmin\App\command\InstallEnvironmentChecker;

final readonly class UpgradeStatusService
{
    public function __construct(
        private string $repositoryRoot,
        private ?string $releaseManifestPath,
        private ?string $backupManifestPath,
        private ?string $environment,
    ) {}

    public static function fromEnvironment(): self
    {
        $release = getenv('UPGRADE_RELEASE_MANIFEST');
        $backup = getenv('UPGRADE_BACKUP_MANIFEST');
        $environment = getenv('APP_ENVIRONMENT');

        return new self(
            dirname(__DIR__, 3),
            is_string($release) && $release !== '' ? $release : null,
            is_string($backup) && $backup !== '' ? $backup : null,
            is_string($environment) && preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $environment) === 1
                ? $environment
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        try {
            $repository = (new RepositoryInspector())->inspect($this->repositoryRoot);
        } catch (UpgradeFailure) {
            return $this->blocked('UPGRADE_REPOSITORY_STATE_UNAVAILABLE', null);
        }

        $current = [
            'commit' => $repository->commit,
            'tree' => $repository->tree,
            'clean' => $repository->clean,
        ];
        if ($this->environment === null) {
            return $this->configurationRequired('UPGRADE_ENVIRONMENT_REQUIRED', $current, $repository->clean);
        }
        if ($this->releaseManifestPath === null) {
            return $this->configurationRequired('UPGRADE_RELEASE_MANIFEST_REQUIRED', $current, $repository->clean);
        }

        try {
            $release = ReleaseManifest::fromFile($this->releaseManifestPath);
        } catch (UpgradeFailure $failure) {
            return $this->blocked($failure->errorCode, $current);
        }

        $backupConfigured = $this->backupManifestPath !== null;
        $backupValid = false;
        $backupSourceMatches = false;
        $backupErrorCode = null;
        $backup = null;
        if ($backupConfigured) {
            try {
                $backup = BackupManifest::fromFile((string) $this->backupManifestPath);
                $backupValid = hash_equals($this->environment, $backup->environment);
                $backupSourceMatches = $backup->source === $release->source;
            } catch (UpgradeFailure $failure) {
                $backupValid = false;
                $backupErrorCode = $failure->errorCode;
            }
        }
        $ready = false;
        $code = $backupErrorCode ?? 'UPGRADE_BACKUP_MANIFEST_REQUIRED';
        if ($backup instanceof BackupManifest) {
            try {
                if (!(new InstallEnvironmentChecker($this->repositoryRoot))->check()['ready']) {
                    throw new UpgradeFailure('UPGRADE_ENVIRONMENT_NOT_READY');
                }
                $validatedRepository = $this->repositoryInspector()->inspectRelease($this->repositoryRoot, $release);
                $targetInventory = (new TargetMigrationInventory())->scan($this->repositoryRoot);
                $confirmedRepository = $this->repositoryInspector()->inspect($this->repositoryRoot);
                if ($validatedRepository != $confirmedRepository) {
                    throw new UpgradeFailure('UPGRADE_REPOSITORY_CHANGED');
                }
                (new UpgradePreflight())->run(
                    $release,
                    $backup,
                    $confirmedRepository,
                    $targetInventory,
                    $this->environment,
                );
                $repository = $confirmedRepository;
                $current = [
                    'commit' => $repository->commit,
                    'tree' => $repository->tree,
                    'clean' => $repository->clean,
                ];
                $ready = true;
                $code = 'UPGRADE_PREFLIGHT_READY';
            } catch (UpgradeFailure $failure) {
                $code = $failure->errorCode;
            }
        }
        $targetMatches = $repository->commit === $release->target['commit']
            && $repository->tree === $release->target['tree'];

        return [
            'state' => $ready ? 'ready' : 'blocked',
            'current' => $current,
            'target' => [
                'release_id' => $release->releaseId,
                'commit' => $release->target['commit'],
                'tree' => $release->target['tree'],
            ],
            'preflight' => [
                'ready' => $ready,
                'code' => $code,
                'repository_clean' => $repository->clean,
                'source_identity_matches' => $backupSourceMatches,
                'target_identity_matches' => $targetMatches,
            ],
            'backup' => $this->backupStatus($backupConfigured, $backupValid, $backupSourceMatches),
            'execution' => $this->executionHandoff(),
        ];
    }

    private function repositoryInspector(): RepositoryInspector
    {
        return new RepositoryInspector();
    }

    /** @param array{commit: string, tree: string, clean: bool} $current
     *  @return array<string, mixed>
     */
    private function configurationRequired(string $code, array $current, bool $clean): array
    {
        return [
            'state' => 'configuration_required',
            'current' => $current,
            'target' => null,
            'preflight' => [
                'ready' => false,
                'code' => $code,
                'repository_clean' => $clean,
                'source_identity_matches' => false,
                'target_identity_matches' => false,
            ],
            'backup' => $this->backupStatus(false, false, false),
            'execution' => $this->executionHandoff(),
        ];
    }

    /** @param array{commit: string, tree: string, clean: bool}|null $current
     *  @return array<string, mixed>
     */
    private function blocked(string $code, ?array $current): array
    {
        return [
            'state' => 'blocked',
            'current' => $current,
            'target' => null,
            'preflight' => [
                'ready' => false,
                'code' => $code,
                'repository_clean' => $current['clean'] ?? false,
                'source_identity_matches' => false,
                'target_identity_matches' => false,
            ],
            'backup' => $this->backupStatus($this->backupManifestPath !== null, false, false),
            'execution' => $this->executionHandoff(),
        ];
    }

    /** @return array{required: true, configured: bool, valid: bool, source_identity_matches: bool} */
    private function backupStatus(bool $configured, bool $valid, bool $sourceMatches): array
    {
        return [
            'required' => true,
            'configured' => $configured,
            'valid' => $valid,
            'source_identity_matches' => $sourceMatches,
        ];
    }

    /** @return array{mode: string, remote_execution: false, command: string} */
    private function executionHandoff(): array
    {
        return [
            'mode' => 'operator_cli',
            'remote_execution' => false,
            'command' => './scripts/upgrade --release-manifest <release-manifest> --backup-manifest <backup-manifest> --environment <environment>',
        ];
    }
}
