<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final readonly class MigrationInventory
{
    /** @var list<array{owner: string, key: string, checksum: string}> */
    public array $entries;

    /** @param list<mixed> $entries */
    public function __construct(array $entries)
    {
        $normalized = [];
        foreach ($entries as $entry) {
            $keys = is_array($entry) ? array_keys($entry) : [];
            sort($keys, SORT_STRING);
            if ($keys !== ['checksum', 'key', 'owner']) {
                throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
            }
            $owner = $entry['owner'] ?? '';
            $key = $entry['key'] ?? '';
            $checksum = $entry['checksum'] ?? '';
            if (!is_string($owner) || preg_match('/^(?:kernel|data-permission|module:[a-z0-9][a-z0-9.-]*)$/D', $owner) !== 1
                || !is_string($key) || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]*$/D', $key) !== 1
                || !is_string($checksum) || preg_match('/^[a-f0-9]{64}$/D', $checksum) !== 1) {
                throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
            }
            $identity = $owner . ':' . $key;
            if (isset($normalized[$identity])) {
                throw new UpgradeFailure('UPGRADE_RELEASE_MANIFEST_INVALID');
            }
            $normalized[$identity] = ['owner' => $owner, 'key' => $key, 'checksum' => $checksum];
        }
        ksort($normalized, SORT_STRING);
        $this->entries = array_values($normalized);
    }

    /** @return array<string, string> */
    public function checksums(): array
    {
        $checksums = [];
        foreach ($this->entries as $entry) {
            $checksums[$entry['owner'] . ':' . $entry['key']] = $entry['checksum'];
        }

        return $checksums;
    }

    public function digest(): string
    {
        return hash('sha256', json_encode($this->entries, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return list<string> */
    public function assertAppendOnlyTo(self $target): array
    {
        $sourceByOwner = $this->byOwner();
        $targetByOwner = $target->byOwner();
        $pending = [];
        foreach ($sourceByOwner as $owner => $sourceEntries) {
            $targetEntries = $targetByOwner[$owner] ?? [];
            if (count($targetEntries) < count($sourceEntries)) {
                throw new UpgradeFailure('UPGRADE_MIGRATION_MISSING');
            }
            foreach ($sourceEntries as $index => $sourceEntry) {
                $targetEntry = $targetEntries[$index] ?? null;
                if (!is_array($targetEntry) || $targetEntry['key'] !== $sourceEntry['key']) {
                    $targetChecksums = array_column($targetEntries, 'checksum', 'key');
                    if (!array_key_exists($sourceEntry['key'], $targetChecksums)) {
                        throw new UpgradeFailure('UPGRADE_MIGRATION_MISSING');
                    }
                    throw new UpgradeFailure('UPGRADE_MIGRATION_BACKDATED');
                }
                if (!hash_equals($sourceEntry['checksum'], $targetEntry['checksum'])) {
                    throw new UpgradeFailure('UPGRADE_MIGRATION_REWRITTEN');
                }
            }
            if ($sourceEntries === []) {
                continue;
            }
            $sourceMaximum = $sourceEntries[count($sourceEntries) - 1]['key'];
            foreach (array_slice($targetEntries, count($sourceEntries)) as $entry) {
                if (strcmp($entry['key'], $sourceMaximum) <= 0) {
                    throw new UpgradeFailure('UPGRADE_MIGRATION_BACKDATED');
                }
                $pending[] = $owner . ':' . $entry['key'];
            }
            unset($targetByOwner[$owner]);
        }
        foreach ($targetByOwner as $owner => $entries) {
            foreach ($entries as $entry) {
                $pending[] = $owner . ':' . $entry['key'];
            }
        }
        sort($pending, SORT_STRING);

        return $pending;
    }

    /** @return array<string, list<array{owner: string, key: string, checksum: string}>> */
    private function byOwner(): array
    {
        $grouped = [];
        foreach ($this->entries as $entry) {
            $grouped[$entry['owner']][] = $entry;
        }
        foreach ($grouped as &$entries) {
            usort($entries, static fn(array $left, array $right): int => strcmp($left['key'], $right['key']));
        }
        unset($entries);
        ksort($grouped, SORT_STRING);

        return $grouped;
    }
}
