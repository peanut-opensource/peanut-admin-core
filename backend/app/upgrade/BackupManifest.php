<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

use DateTimeImmutable;

final readonly class BackupManifest
{
    /** @param array{commit: string, tree: string} $source */
    private function __construct(
        public string $backupId,
        public string $environment,
        public array $source,
        public string $artifactSha256,
        public string $createdAt,
        public string $verifiedAt,
        public string $restoreTestedAt,
        public string $manifestDigest,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        self::exactKeys($data, [
            'schema_version', 'backup_id', 'environment', 'source', 'artifact_sha256',
            'created_at', 'verified_at', 'restore_tested_at',
        ]);
        if (($data['schema_version'] ?? null) !== 1
            || !is_string($data['backup_id'] ?? null)
            || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,127}$/D', $data['backup_id']) !== 1
            || !is_string($data['environment'] ?? null)
            || preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $data['environment']) !== 1
            || !is_string($data['artifact_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $data['artifact_sha256']) !== 1) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }
        $source = $data['source'] ?? null;
        if (!is_array($source)
            || !is_string($source['commit'] ?? null) || preg_match('/^[a-f0-9]{40}$/D', $source['commit']) !== 1
            || !is_string($source['tree'] ?? null) || preg_match('/^[a-f0-9]{40}$/D', $source['tree']) !== 1) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }
        self::exactKeys($source, ['commit', 'tree']);
        $timestamps = [];
        $normalizedTimestamps = [];
        foreach (['created_at', 'verified_at', 'restore_tested_at'] as $field) {
            if (!is_string($data[$field] ?? null)) {
                throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
            }
            [$timestamps[], $normalizedTimestamps[]] = self::timestamp($data[$field]);
        }
        if ($timestamps[0] > $timestamps[1] || $timestamps[1] > $timestamps[2]) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }

        $identity = ['commit' => $source['commit'], 'tree' => $source['tree']];
        $normalized = [
            'schema_version' => 1,
            'backup_id' => $data['backup_id'],
            'environment' => $data['environment'],
            'source' => $identity,
            'artifact_sha256' => $data['artifact_sha256'],
            'created_at' => $normalizedTimestamps[0],
            'verified_at' => $normalizedTimestamps[1],
            'restore_tested_at' => $normalizedTimestamps[2],
        ];

        return new self(
            $data['backup_id'],
            $data['environment'],
            $identity,
            $data['artifact_sha256'],
            $normalizedTimestamps[0],
            $normalizedTimestamps[1],
            $normalizedTimestamps[2],
            hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        );
    }

    public static function fromFile(string $path): self
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;
        if (!is_string($contents)) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_UNREADABLE');
        }
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }
        if (!is_array($data)) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }

        return self::fromArray($data);
    }

    /** @return array{DateTimeImmutable, string} */
    private static function timestamp(string $value): array
    {
        if (preg_match(
            '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})$/D',
            $value,
            $matches,
        ) !== 1) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }
        $fraction = $matches[2];
        $offset = $matches[3] === 'Z' ? '+00:00' : $matches[3];
        $candidate = $matches[1]
            . ($fraction === '' ? '' : '.' . str_pad($fraction, 6, '0'))
            . $offset;
        $format = $fraction === '' ? '!Y-m-d\TH:i:sP' : '!Y-m-d\TH:i:s.uP';
        $timestamp = DateTimeImmutable::createFromFormat($format, $candidate);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$timestamp instanceof DateTimeImmutable
            || (is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))) {
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }

        return [$timestamp, $timestamp->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z')];
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
            throw new UpgradeFailure('UPGRADE_BACKUP_MANIFEST_INVALID');
        }
    }
}
