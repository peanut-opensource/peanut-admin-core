<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Model;

use UnexpectedValueException;

final readonly class CollaborationSnapshotEnvelope
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public int $sessionId,
        public string $snapshotKey,
        public int $coveredSequence,
        public string $engineName,
        public string $engineVersion,
        public int $snapshotByteLength,
        public string $snapshotSha256,
        public string $opaqueSnapshot,
        public int $stateVectorByteLength,
        public string $stateVectorSha256,
        public string $opaqueStateVector,
        public int $authorMemberId,
        public int $authorAccountId,
        public string $createdAt,
        public string $retainUntil,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['tenant_id'],
            (int) $row['session_id'],
            (string) $row['snapshot_key'],
            (int) $row['covered_sequence'],
            (string) $row['engine_name'],
            (string) $row['engine_version'],
            (int) $row['snapshot_byte_length'],
            (string) $row['snapshot_sha256'],
            (string) $row['opaque_snapshot'],
            (int) $row['state_vector_byte_length'],
            (string) $row['state_vector_sha256'],
            (string) $row['opaque_state_vector'],
            (int) $row['author_member_id'],
            (int) $row['author_account_id'],
            (string) $row['created_at'],
            (string) $row['retain_until'],
        );
    }

    public function assertPayloadIntegrity(): void
    {
        if (strlen($this->opaqueSnapshot) !== $this->snapshotByteLength
            || !hash_equals($this->snapshotSha256, hash('sha256', $this->opaqueSnapshot))
            || strlen($this->opaqueStateVector) !== $this->stateVectorByteLength
            || !hash_equals($this->stateVectorSha256, hash('sha256', $this->opaqueStateVector))) {
            throw new UnexpectedValueException('Collaboration snapshot envelope integrity failure.');
        }
    }
}
