<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final readonly class ReleaseManifest
{
    /** @param array{commit: string, tree: string} $source
     *  @param array{commit: string, tree: string} $target
     */
    private function __construct(
        public string $releaseId,
        public array $source,
        public array $target,
        public MigrationInventory $sourceMigrations,
        public MigrationInventory $targetMigrations,
        public string $manifestDigest,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        self::exactKeys($data, ['schema_version', 'release_id', 'source', 'target', 'migrations']);
        if (($data['schema_version'] ?? null) !== 1
            || !is_string($data['release_id'] ?? null)
            || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,127}$/D', $data['release_id']) !== 1) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
        }
        $source = self::identity($data['source'] ?? null);
        $target = self::identity($data['target'] ?? null);
        if ($source === $target) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
        }
        $migrations = $data['migrations'] ?? null;
        if (!is_array($migrations) || !is_array($migrations['source'] ?? null) || !is_array($migrations['target'] ?? null)) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
        }
        self::exactKeys($migrations, ['source', 'target']);

        /** @var list<array{owner: string, key: string, checksum: string}> $sourceEntries */
        $sourceEntries = array_values($migrations['source']);
        /** @var list<array{owner: string, key: string, checksum: string}> $targetEntries */
        $targetEntries = array_values($migrations['target']);
        $sourceMigrations = new MigrationInventory($sourceEntries);
        $targetMigrations = new MigrationInventory($targetEntries);
        $normalized = [
            'schema_version' => 1,
            'release_id' => $data['release_id'],
            'source' => $source,
            'target' => $target,
            'migrations' => [
                'source' => $sourceMigrations->entries,
                'target' => $targetMigrations->entries,
            ],
        ];

        return new self(
            $data['release_id'],
            $source,
            $target,
            $sourceMigrations,
            $targetMigrations,
            hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        );
    }

    public static function fromFile(string $path): self
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;
        if (!is_string($contents)) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_UNREADABLE');
        }
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
        }
        if (!is_array($data)) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
        }

        return self::fromArray($data);
    }

    /** @return array{commit: string, tree: string} */
    private static function identity(mixed $value): array
    {
        if (!is_array($value)) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
        }
        self::exactKeys($value, ['commit', 'tree']);
        $commit = $value['commit'] ?? null;
        $tree = $value['tree'] ?? null;
        if (!is_string($commit) || preg_match('/^[a-f0-9]{40}$/D', $commit) !== 1
            || !is_string($tree) || preg_match('/^[a-f0-9]{40}$/D', $tree) !== 1) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
        }

        return ['commit' => $commit, 'tree' => $tree];
    }

    /** @param array<string, mixed> $value
     *  @param list<string> $expected
     */
    private static function exactKeys(array $value, array $expected): void
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected) {
            throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
        }
    }
}
