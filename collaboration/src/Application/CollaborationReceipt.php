<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Application;

use PeanutAdmin\Collaboration\Package;

final readonly class CollaborationReceipt
{
    /** @var list<string> */
    private const KEYS = [
        'operation',
        'session_key',
        'session_status',
        'session_revision',
        'base_revision_key',
        'base_revision_sha256',
        'lease_key',
        'lease_capability',
        'lease_expires_at',
        'update_key',
        'sequence',
        'byte_length',
        'sha256',
        'snapshot_key',
        'covered_sequence',
        'published_revision_key',
        'published_revision_sha256',
        'retain_until',
    ];

    public function __construct(
        public string $operation,
        public string $sessionKey,
        public string $sessionStatus,
        public int $sessionRevision,
        public ?string $baseRevisionKey = null,
        public ?string $baseRevisionSha256 = null,
        public ?string $leaseKey = null,
        public ?string $leaseCapability = null,
        public ?string $leaseExpiresAt = null,
        public ?string $updateKey = null,
        public ?int $sequence = null,
        public ?int $byteLength = null,
        public ?string $sha256 = null,
        public ?string $snapshotKey = null,
        public ?int $coveredSequence = null,
        public ?string $publishedRevisionKey = null,
        public ?string $publishedRevisionSha256 = null,
        public ?string $retainUntil = null,
    ) {
        $this->assertOperationShape();
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'session_key' => $this->sessionKey,
            'session_status' => $this->sessionStatus,
            'session_revision' => $this->sessionRevision,
            'base_revision_key' => $this->baseRevisionKey,
            'base_revision_sha256' => $this->baseRevisionSha256,
            'lease_key' => $this->leaseKey,
            'lease_capability' => $this->leaseCapability,
            'lease_expires_at' => $this->leaseExpiresAt,
            'update_key' => $this->updateKey,
            'sequence' => $this->sequence,
            'byte_length' => $this->byteLength,
            'sha256' => $this->sha256,
            'snapshot_key' => $this->snapshotKey,
            'covered_sequence' => $this->coveredSequence,
            'published_revision_key' => $this->publishedRevisionKey,
            'published_revision_sha256' => $this->publishedRevisionSha256,
            'retain_until' => $this->retainUntil,
        ];
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value, string $expectedOperation): self
    {
        $expectedKeys = self::KEYS;
        $actualKeys = array_keys($value);
        sort($expectedKeys, SORT_STRING);
        sort($actualKeys, SORT_STRING);
        $operations = [
            Package::OPEN_OPERATION,
            Package::JOIN_OPERATION,
            Package::HEARTBEAT_OPERATION,
            Package::APPEND_OPERATION,
            Package::SNAPSHOT_OPERATION,
            Package::PUBLISH_OPERATION,
            Package::CLOSE_OPERATION,
        ];
        if ($actualKeys !== $expectedKeys
            || !in_array($expectedOperation, $operations, true)
            || !is_string($value['operation'])
            || !hash_equals($expectedOperation, $value['operation'])
            || !self::key($value['session_key'], 'session')
            || !is_string($value['session_status'])
            || !in_array($value['session_status'], ['active', 'published', 'closed'], true)
            || !is_int($value['session_revision'])
            || $value['session_revision'] < 1
            || !self::nullableKey($value['base_revision_key'], 'revision')
            || !self::nullableDigest($value['base_revision_sha256'])
            || !self::nullableKey($value['lease_key'], 'lease')
            || ($value['lease_capability'] !== null
                && (!is_string($value['lease_capability'])
                    || !in_array($value['lease_capability'], ['read', 'write'], true)))
            || ($value['lease_expires_at'] !== null && !is_string($value['lease_expires_at']))
            || !self::nullableKey($value['update_key'], 'update')
            || ($value['sequence'] !== null && (!is_int($value['sequence']) || $value['sequence'] < 1))
            || ($value['byte_length'] !== null && (!is_int($value['byte_length']) || $value['byte_length'] < 1))
            || !self::nullableDigest($value['sha256'])
            || !self::nullableKey($value['snapshot_key'], 'snapshot')
            || ($value['covered_sequence'] !== null
                && (!is_int($value['covered_sequence']) || $value['covered_sequence'] < 0))
            || !self::nullableKey($value['published_revision_key'], 'revision')
            || !self::nullableDigest($value['published_revision_sha256'])
            || ($value['retain_until'] !== null && !is_string($value['retain_until']))) {
            throw CollaborationException::internal();
        }

        return new self(
            $value['operation'],
            $value['session_key'],
            $value['session_status'],
            $value['session_revision'],
            $value['base_revision_key'],
            $value['base_revision_sha256'],
            $value['lease_key'],
            $value['lease_capability'],
            $value['lease_expires_at'],
            $value['update_key'],
            $value['sequence'],
            $value['byte_length'],
            $value['sha256'],
            $value['snapshot_key'],
            $value['covered_sequence'],
            $value['published_revision_key'],
            $value['published_revision_sha256'],
            $value['retain_until'],
        );
    }

    private function assertOperationShape(): void
    {
        $valid = match ($this->operation) {
            Package::OPEN_OPERATION => $this->sessionStatus === 'active'
                && $this->baseRevisionKey !== null && $this->baseRevisionSha256 !== null
                && $this->allNull([
                    $this->leaseKey, $this->leaseCapability, $this->leaseExpiresAt,
                    $this->updateKey, $this->sequence, $this->byteLength, $this->sha256,
                    $this->snapshotKey, $this->coveredSequence,
                    $this->publishedRevisionKey, $this->publishedRevisionSha256, $this->retainUntil,
                ]),
            Package::JOIN_OPERATION, Package::HEARTBEAT_OPERATION => $this->sessionStatus === 'active'
                && $this->leaseKey !== null && $this->leaseCapability !== null && $this->leaseExpiresAt !== null
                && $this->allNull([
                    $this->baseRevisionKey, $this->baseRevisionSha256,
                    $this->updateKey, $this->sequence, $this->byteLength, $this->sha256,
                    $this->snapshotKey, $this->coveredSequence,
                    $this->publishedRevisionKey, $this->publishedRevisionSha256, $this->retainUntil,
                ]),
            Package::APPEND_OPERATION => $this->sessionStatus === 'active'
                && $this->updateKey !== null && $this->sequence !== null
                && $this->byteLength !== null && $this->sha256 !== null
                && $this->allNull([
                    $this->baseRevisionKey, $this->baseRevisionSha256,
                    $this->leaseKey, $this->leaseCapability, $this->leaseExpiresAt,
                    $this->snapshotKey, $this->coveredSequence,
                    $this->publishedRevisionKey, $this->publishedRevisionSha256, $this->retainUntil,
                ]),
            Package::SNAPSHOT_OPERATION => $this->sessionStatus === 'active'
                && $this->snapshotKey !== null && $this->coveredSequence !== null
                && $this->byteLength !== null && $this->sha256 !== null
                && $this->allNull([
                    $this->baseRevisionKey, $this->baseRevisionSha256,
                    $this->leaseKey, $this->leaseCapability, $this->leaseExpiresAt,
                    $this->updateKey, $this->sequence,
                    $this->publishedRevisionKey, $this->publishedRevisionSha256, $this->retainUntil,
                ]),
            Package::PUBLISH_OPERATION => $this->sessionStatus === 'published'
                && $this->publishedRevisionKey !== null
                && $this->publishedRevisionSha256 !== null && $this->retainUntil !== null
                && $this->allNull([
                    $this->baseRevisionKey, $this->baseRevisionSha256,
                    $this->leaseKey, $this->leaseCapability, $this->leaseExpiresAt,
                    $this->updateKey, $this->sequence, $this->byteLength, $this->sha256,
                    $this->snapshotKey, $this->coveredSequence,
                ]),
            Package::CLOSE_OPERATION => $this->sessionStatus === 'closed'
                && $this->retainUntil !== null
                && $this->allNull([
                    $this->baseRevisionKey, $this->baseRevisionSha256,
                    $this->leaseKey, $this->leaseCapability, $this->leaseExpiresAt,
                    $this->updateKey, $this->sequence, $this->byteLength, $this->sha256,
                    $this->snapshotKey, $this->coveredSequence,
                    $this->publishedRevisionKey, $this->publishedRevisionSha256,
                ]),
            default => false,
        };
        if (!$valid) {
            throw CollaborationException::internal();
        }
    }

    /** @param list<mixed> $values */
    private function allNull(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null) {
                return false;
            }
        }

        return true;
    }

    private static function key(mixed $value, string $prefix): bool
    {
        return is_string($value) && preg_match('/^' . $prefix . '_[0-9a-f]{32}$/D', $value) === 1;
    }

    private static function nullableKey(mixed $value, string $prefix): bool
    {
        return $value === null || self::key($value, $prefix);
    }

    private static function nullableDigest(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && preg_match('/^[0-9a-f]{64}$/D', $value) === 1);
    }
}
