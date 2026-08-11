<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Model;

final readonly class CollaborationParticipantLease
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public int $sessionId,
        public string $leaseKey,
        public string $clientKey,
        public int $memberId,
        public int $accountId,
        public string $capability,
        public string $authorizationBasisSha256,
        public string $status,
        public int $revision,
        public string $issuedAt,
        public string $heartbeatAt,
        public string $expiresAt,
        public ?string $revokedAt,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['tenant_id'],
            (int) $row['session_id'],
            (string) $row['lease_key'],
            (string) $row['client_key'],
            (int) $row['member_id'],
            (int) $row['account_id'],
            (string) $row['capability'],
            (string) $row['authorization_basis_sha256'],
            (string) $row['status'],
            (int) $row['revision'],
            (string) $row['issued_at'],
            (string) $row['heartbeat_at'],
            (string) $row['expires_at'],
            $row['revoked_at'] === null ? null : (string) $row['revoked_at'],
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'session_id' => $this->sessionId,
            'lease_key' => $this->leaseKey,
            'client_key' => $this->clientKey,
            'member_id' => $this->memberId,
            'account_id' => $this->accountId,
            'capability' => $this->capability,
            'authorization_basis_sha256' => $this->authorizationBasisSha256,
            'status' => $this->status,
            'revision' => $this->revision,
            'issued_at' => $this->issuedAt,
            'heartbeat_at' => $this->heartbeatAt,
            'expires_at' => $this->expiresAt,
            'revoked_at' => $this->revokedAt,
        ];
    }
}
