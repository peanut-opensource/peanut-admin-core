<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final class RepositoryInspector
{
    public function inspectRelease(string $root, ReleaseManifest $release): RepositoryState
    {
        if (!file_exists($root . '/.git') || is_link($root . '/.git')) {
            throw new UpgradeFailure('UPGRADE_GIT_METADATA_REQUIRED');
        }
        $this->assertCommitTree($root, $release->source);
        $this->assertCommitTree($root, $release->target);
        $sourceInventory = $this->inventoryAtCommit($root, $release->source['commit']);
        if (!hash_equals($release->sourceMigrations->digest(), $sourceInventory->digest())) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_MISMATCH');
        }

        return $this->inspect($root);
    }

    public function inspect(string $root): RepositoryState
    {
        return new RepositoryState(
            $this->git($root, ['rev-parse', 'HEAD']),
            $this->git($root, ['rev-parse', 'HEAD^{tree}']),
            $this->git($root, ['status', '--porcelain', '--untracked-files=all']) === '',
        );
    }

    /** @param list<string> $arguments */
    private function git(string $root, array $arguments): string
    {
        $command = ['git', '-C', $root, ...$arguments];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new UpgradeFailure('UPGRADE_REPOSITORY_STATE_UNAVAILABLE');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || !is_string($stdout) || !is_string($stderr)) {
            throw new UpgradeFailure('UPGRADE_REPOSITORY_STATE_UNAVAILABLE');
        }

        return trim($stdout);
    }

    /** @param array{commit: string, tree: string} $identity */
    private function assertCommitTree(string $root, array $identity): void
    {
        $commit = $this->git($root, ['rev-parse', '--verify', $identity['commit'] . '^{commit}']);
        $tree = $this->git($root, ['rev-parse', '--verify', $identity['commit'] . '^{tree}']);
        if ($commit !== $identity['commit'] || $tree !== $identity['tree']) {
            throw new UpgradeFailure('UPGRADE_RELEASE_IDENTITY_INVALID');
        }
    }

    public function inventoryAtCommit(string $root, string $commit): MigrationInventory
    {
        if (preg_match('/^[a-f0-9]{40}$/D', $commit) !== 1) {
            throw new UpgradeFailure('UPGRADE_RELEASE_IDENTITY_INVALID');
        }
        $temporary = rtrim(sys_get_temp_dir(), '/') . '/peanut-upgrade-source-' . bin2hex(random_bytes(12));
        $archive = $temporary . '/source.tar';
        $extracted = $temporary . '/source';
        if (!mkdir($temporary, 0700, true)) {
            throw new UpgradeFailure('UPGRADE_SOURCE_INVENTORY_UNAVAILABLE');
        }
        try {
            if (!mkdir($extracted, 0700, true)) {
                throw new UpgradeFailure('UPGRADE_SOURCE_INVENTORY_UNAVAILABLE');
            }
            $this->process([
                'git', '-C', $root, 'archive', '--format=tar', '--output=' . $archive, $commit,
            ]);
            $this->process(['tar', '-xf', $archive, '-C', $extracted]);

            return (new TargetMigrationInventory())->scan($extracted);
        } finally {
            $this->removeDirectory($temporary);
        }
    }

    /** @param list<string> $command */
    private function process(array $command): void
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new UpgradeFailure('UPGRADE_SOURCE_INVENTORY_UNAVAILABLE');
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new UpgradeFailure('UPGRADE_SOURCE_INVENTORY_UNAVAILABLE');
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($path);
    }

}
