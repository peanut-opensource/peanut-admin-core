<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final class TargetMigrationInventory
{
    public function scan(string $root): MigrationInventory
    {
        $repositoryRoot = realpath($root);
        if ($repositoryRoot === false || !is_dir($repositoryRoot)) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        $entries = [];
        $kernelRoot = $this->ownedDirectory($repositoryRoot, $repositoryRoot, 'packages/php/kernel');
        $dataPermissionRoot = $this->ownedDirectory(
            $repositoryRoot,
            $repositoryRoot,
            'packages/php/data-permission',
        );
        $this->scanDirectory(
            $entries,
            'kernel',
            $this->ownedDirectory($repositoryRoot, $kernelRoot, 'database/migrations'),
        );
        $this->scanDirectory(
            $entries,
            'data-permission',
            $this->ownedDirectory($repositoryRoot, $dataPermissionRoot, 'database/migrations'),
        );

        $configPath = $repositoryRoot . '/backend/config/modules.php';
        $physicalConfig = realpath($configPath);
        if ($physicalConfig !== $configPath || is_link($configPath) || !is_file($configPath)) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        $config = is_readable($configPath)
            ? require $configPath
            : null;
        if (!is_array($config) || !is_array($config['roots'] ?? null)) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        foreach ($config['roots'] as $relativeRoot) {
            if (!is_string($relativeRoot)) {
                throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
            }
            $moduleRoot = $this->ownedDirectory($repositoryRoot, $repositoryRoot, $relativeRoot);
            $manifestPath = $moduleRoot . '/module.json';
            if (is_link($manifestPath) || !is_file($manifestPath)) {
                throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
            }
            $manifest = $this->json($manifestPath);
            $moduleKey = $manifest['key'] ?? null;
            $backend = $manifest['backend'] ?? null;
            $migrations = is_array($backend) ? ($backend['migrations'] ?? null) : null;
            if ($migrations === null) {
                continue;
            }
            if (!is_string($moduleKey) || !is_string($migrations) || $migrations === '') {
                throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
            }
            $this->scanDirectory(
                $entries,
                'module:' . $moduleKey,
                $this->ownedDirectory($repositoryRoot, $moduleRoot, $migrations),
            );
        }

        return new MigrationInventory($entries);
    }

    /** @param list<array{owner: string, key: string, checksum: string}> $entries */
    private function scanDirectory(array &$entries, string $owner, string $directory): void
    {
        if (!is_dir($directory)) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        $files = glob($directory . '/*.php');
        if ($files === false) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            if (is_link($file) || !is_file($file)) {
                throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
            }
            $physicalFile = realpath($file);
            if ($physicalFile === false
                || !str_starts_with($physicalFile, $directory . DIRECTORY_SEPARATOR)) {
                throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
            }
            $key = pathinfo($file, PATHINFO_FILENAME);
            if (preg_match('/^\d{14}_[a-z0-9_]+$/D', $key) !== 1) {
                throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
            }
            $checksum = hash_file('sha256', $file);
            if (!is_string($checksum)) {
                throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
            }
            $entries[] = ['owner' => $owner, 'key' => $key, 'checksum' => $checksum];
        }
    }

    private function ownedDirectory(string $repositoryRoot, string $ownerRoot, string $relativePath): string
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '\\')) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        $segments = explode('/', $relativePath);
        if (in_array('..', $segments, true) || in_array('', $segments, true)) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        $candidate = $ownerRoot . '/' . $relativePath;
        $physical = realpath($candidate);
        if ($physical === false || !is_dir($physical)
            || !str_starts_with($physical, $ownerRoot . DIRECTORY_SEPARATOR)
            || !str_starts_with($physical, $repositoryRoot . DIRECTORY_SEPARATOR)) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        $relativeToRepository = substr($candidate, strlen($repositoryRoot) + 1);
        $cursor = $repositoryRoot;
        foreach (explode('/', $relativeToRepository) as $segment) {
            $cursor .= '/' . $segment;
            if (is_link($cursor)) {
                throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
            }
        }

        return $physical;
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        try {
            $value = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }
        if (!is_array($value)) {
            throw new UpgradeFailure('UPGRADE_TARGET_INVENTORY_UNAVAILABLE');
        }

        return $value;
    }
}
