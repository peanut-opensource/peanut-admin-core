<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Contract;

use InvalidArgumentException;

final readonly class CollaborationPolicy
{
    public function __construct(
        public int $sessionTtlSeconds,
        public int $leaseTtlSeconds,
        public int $updateLimitBytes,
        public int $snapshotLimitBytes,
        public int $maxUnsnapshottedUpdates,
        public int $retentionSeconds,
    ) {
        if ($sessionTtlSeconds < 300 || $sessionTtlSeconds > 86_400
            || $leaseTtlSeconds < 30 || $leaseTtlSeconds > 300
            || $leaseTtlSeconds > $sessionTtlSeconds
            || $updateLimitBytes < 1 || $updateLimitBytes > 262_144
            || $snapshotLimitBytes < 1 || $snapshotLimitBytes > 8_388_608
            || $maxUnsnapshottedUpdates < 1 || $maxUnsnapshottedUpdates > 1_000
            || $retentionSeconds < 30 || $retentionSeconds > 7_776_000) {
            throw new InvalidArgumentException('The collaboration policy is outside the bounded contract.');
        }
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'session_ttl_seconds' => $this->sessionTtlSeconds,
            'lease_ttl_seconds' => $this->leaseTtlSeconds,
            'update_limit_bytes' => $this->updateLimitBytes,
            'snapshot_limit_bytes' => $this->snapshotLimitBytes,
            'max_unsnapshotted_updates' => $this->maxUnsnapshottedUpdates,
            'retention_seconds' => $this->retentionSeconds,
        ];
    }
}
