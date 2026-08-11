<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Model;

use UnexpectedValueException;

final readonly class CollaborationUpdateEnvelope
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public int $sessionId,
        public int $sequenceNo,
        public string $updateKey,
        public string $clientKey,
        public string $leaseKey,
        public string $engineName,
        public string $engineVersion,
        public int $byteLength,
        public string $updateSha256,
        public string $opaquePayload,
        public int $authorMemberId,
        public int $authorAccountId,
        public string $occurredAt,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['tenant_id'],
            (int) $row['session_id'],
            (int) $row['sequence_no'],
            (string) $row['update_key'],
            (string) $row['client_key'],
            (string) $row['lease_key'],
            (string) $row['engine_name'],
            (string) $row['engine_version'],
            (int) $row['byte_length'],
            (string) $row['update_sha256'],
            (string) $row['opaque_payload'],
            (int) $row['author_member_id'],
            (int) $row['author_account_id'],
            (string) $row['occurred_at'],
        );
    }

    public function assertPayloadIntegrity(): void
    {
        if (strlen($this->opaquePayload) !== $this->byteLength
            || !hash_equals($this->updateSha256, hash('sha256', $this->opaquePayload))) {
            throw new UnexpectedValueException('Collaboration update envelope integrity failure.');
        }
    }
}
