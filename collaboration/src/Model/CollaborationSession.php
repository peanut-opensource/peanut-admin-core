<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Model;

final readonly class CollaborationSession
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $sessionKey,
        public string $artifactType,
        public string $artifactKey,
        public string $engineName,
        public string $engineVersion,
        public string $baseRevisionKey,
        public string $baseRevisionSha256,
        public int $latestSequence,
        public int $revision,
        public string $status,
        public int $openedByMemberId,
        public int $openedByAccountId,
        public ?int $closedByMemberId,
        public ?int $closedByAccountId,
        public string $expiresAt,
        public string $createdAt,
        public string $updatedAt,
        public ?string $closedAt,
        public ?string $publishedAt,
        public ?string $publishedRevisionKey,
        public ?string $publishedRevisionSha256,
        public ?string $retainUntil,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['tenant_id'],
            (string) $row['session_key'],
            (string) $row['artifact_type'],
            (string) $row['artifact_key'],
            (string) $row['engine_name'],
            (string) $row['engine_version'],
            (string) $row['base_revision_key'],
            (string) $row['base_revision_sha256'],
            (int) $row['latest_sequence'],
            (int) $row['revision'],
            (string) $row['status'],
            (int) $row['opened_by_member_id'],
            (int) $row['opened_by_account_id'],
            $row['closed_by_member_id'] === null ? null : (int) $row['closed_by_member_id'],
            $row['closed_by_account_id'] === null ? null : (int) $row['closed_by_account_id'],
            (string) $row['expires_at'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
            $row['closed_at'] === null ? null : (string) $row['closed_at'],
            $row['published_at'] === null ? null : (string) $row['published_at'],
            $row['published_revision_key'] === null ? null : (string) $row['published_revision_key'],
            $row['published_revision_sha256'] === null ? null : (string) $row['published_revision_sha256'],
            $row['retain_until'] === null ? null : (string) $row['retain_until'],
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
            'session_key' => $this->sessionKey,
            'artifact_type' => $this->artifactType,
            'artifact_key' => $this->artifactKey,
            'engine_name' => $this->engineName,
            'engine_version' => $this->engineVersion,
            'base_revision_key' => $this->baseRevisionKey,
            'base_revision_sha256' => $this->baseRevisionSha256,
            'latest_sequence' => $this->latestSequence,
            'revision' => $this->revision,
            'status' => $this->status,
            'opened_by_member_id' => $this->openedByMemberId,
            'opened_by_account_id' => $this->openedByAccountId,
            'closed_by_member_id' => $this->closedByMemberId,
            'closed_by_account_id' => $this->closedByAccountId,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'closed_at' => $this->closedAt,
            'published_at' => $this->publishedAt,
            'published_revision_key' => $this->publishedRevisionKey,
            'published_revision_sha256' => $this->publishedRevisionSha256,
            'retain_until' => $this->retainUntil,
        ];
    }
}
