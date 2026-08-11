<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Persistence;

use PDO;
use PDOException;
use PDOStatement;
use PeanutAdmin\Collaboration\Model\CollaborationParticipantLease;
use PeanutAdmin\Collaboration\Model\CollaborationSession;
use PeanutAdmin\Collaboration\Model\CollaborationSnapshotEnvelope;
use PeanutAdmin\Collaboration\Model\CollaborationUpdateEnvelope;
use RuntimeException;
use UnexpectedValueException;

final readonly class PdoCollaborationRepository implements CollaborationRepository
{
    private const MAX_SEQUENCE = 9_223_372_036_854_775_807;

    public function __construct(private PDO $pdo) {}

    public function connection(): PDO
    {
        return $this->pdo;
    }

    public function session(int $tenantId, string $sessionKey, bool $forUpdate = false): ?CollaborationSession
    {
        $row = $this->one(<<<'SQL'
SELECT * FROM pa_collaboration_session
WHERE tenant_id = :tenant_id AND session_key = :session_key
SQL . ($forUpdate ? ' FOR UPDATE' : ''), [
            'tenant_id' => $tenantId,
            'session_key' => $sessionKey,
        ]);

        return $row === null ? null : CollaborationSession::fromRow($row);
    }

    public function activeSession(
        int $tenantId,
        string $artifactType,
        string $artifactKey,
        bool $forUpdate = false,
    ): ?CollaborationSession {
        $row = $this->one(<<<'SQL'
SELECT * FROM pa_collaboration_session
WHERE tenant_id = :tenant_id AND artifact_type = :artifact_type
  AND artifact_key = :artifact_key AND status = 'active'
SQL . ($forUpdate ? ' FOR UPDATE' : ''), [
            'tenant_id' => $tenantId,
            'artifact_type' => $artifactType,
            'artifact_key' => $artifactKey,
        ]);

        return $row === null ? null : CollaborationSession::fromRow($row);
    }

    public function createSession(
        int $tenantId,
        string $sessionKey,
        string $artifactType,
        string $artifactKey,
        string $engineName,
        string $engineVersion,
        string $baseRevisionKey,
        string $baseRevisionSha256,
        int $memberId,
        int $accountId,
        string $expiresAt,
        string $now,
    ): CollaborationSession {
        try {
            $this->execute(<<<'SQL'
INSERT INTO pa_collaboration_session (
  tenant_id, session_key, artifact_type, artifact_key, engine_name, engine_version,
  base_revision_key, base_revision_sha256, latest_sequence, revision, status,
  opened_by_member_id, opened_by_account_id, closed_by_member_id,
  closed_by_account_id, expires_at, created_at, updated_at, closed_at,
  published_at, published_revision_key, published_revision_sha256, retain_until
) VALUES (
  :tenant_id, :session_key, :artifact_type, :artifact_key, :engine_name, :engine_version,
  :base_revision_key, :base_revision_sha256, 0, 1, 'active',
  :member_id, :account_id, NULL, NULL, :expires_at, :created_at, :updated_at,
  NULL, NULL, NULL, NULL, NULL
)
SQL, [
                'tenant_id' => $tenantId,
                'session_key' => $sessionKey,
                'artifact_type' => $artifactType,
                'artifact_key' => $artifactKey,
                'engine_name' => $engineName,
                'engine_version' => $engineVersion,
                'base_revision_key' => $baseRevisionKey,
                'base_revision_sha256' => $baseRevisionSha256,
                'member_id' => $memberId,
                'account_id' => $accountId,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (PDOException $exception) {
            if ($this->duplicate($exception)) {
                throw $this->conflict('The collaboration session identity is unavailable.');
            }
            throw $exception;
        }

        return $this->session($tenantId, $sessionKey)
            ?? throw $this->internal('The collaboration session could not be read back.');
    }

    public function completeSession(
        int $tenantId,
        int $sessionId,
        int $expectedRevision,
        string $status,
        ?int $memberId,
        ?int $accountId,
        ?string $publishedRevisionKey,
        ?string $publishedRevisionSha256,
        string $now,
        string $retainUntil,
    ): CollaborationSession {
        if (!in_array($status, ['published', 'closed', 'expired'], true)) {
            throw $this->conflict('The collaboration session transition is unavailable.');
        }
        if (($status === 'published'
                && ($publishedRevisionKey === null || $publishedRevisionSha256 === null))
            || ($status !== 'published'
                && ($publishedRevisionKey !== null || $publishedRevisionSha256 !== null))) {
            throw $this->conflict('The collaboration publication result is invalid.');
        }
        if (($memberId === null) !== ($accountId === null)) {
            throw $this->conflict('The collaboration completion actor is invalid.');
        }
        if ($status !== 'expired' && $memberId === null) {
            throw $this->conflict('The collaboration completion actor is required.');
        }

        $session = $this->sessionById($tenantId, $sessionId, true)
            ?? throw $this->notFound('The collaboration session is unavailable.');
        if (!$session->isActive() || $session->revision !== $expectedRevision) {
            throw $this->conflict('The collaboration session has changed.');
        }
        if ($status === 'published' && $session->expiresAt <= $now) {
            throw $this->conflict('The collaboration session is not active.');
        }
        if ($status === 'expired' && $session->expiresAt > $now) {
            throw $this->conflict('The collaboration session has not expired.');
        }

        $updated = $this->execute(<<<'SQL'
UPDATE pa_collaboration_session
SET status = :status, revision = revision + 1,
    closed_by_member_id = :member_id, closed_by_account_id = :account_id,
    updated_at = :updated_at, closed_at = :closed_at,
    published_at = :published_at, published_revision_key = :published_revision_key,
    published_revision_sha256 = :published_revision_sha256,
    retain_until = :retain_until
WHERE tenant_id = :tenant_id AND id = :session_id
  AND status = 'active' AND revision = :expected_revision
SQL, [
            'status' => $status,
            'member_id' => $memberId,
            'account_id' => $accountId,
            'updated_at' => $now,
            'closed_at' => $now,
            'published_at' => $status === 'published' ? $now : null,
            'published_revision_key' => $publishedRevisionKey,
            'published_revision_sha256' => $publishedRevisionSha256,
            'retain_until' => $retainUntil,
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'expected_revision' => $expectedRevision,
        ]);
        if ($updated !== 1) {
            throw $this->conflict('The collaboration session has changed.');
        }

        return $this->sessionById($tenantId, $sessionId)
            ?? throw $this->internal('The completed collaboration session could not be read back.');
    }

    public function lease(
        int $tenantId,
        int $sessionId,
        string $leaseKey,
        bool $forUpdate = false,
    ): ?CollaborationParticipantLease {
        $row = $this->one(<<<'SQL'
SELECT * FROM pa_collaboration_participant_lease
WHERE tenant_id = :tenant_id AND session_id = :session_id AND lease_key = :lease_key
SQL . ($forUpdate ? ' FOR UPDATE' : ''), [
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'lease_key' => $leaseKey,
        ]);

        return $row === null ? null : CollaborationParticipantLease::fromRow($row);
    }

    public function activeLeaseForClient(
        int $tenantId,
        int $sessionId,
        string $clientKey,
        bool $forUpdate = false,
    ): ?CollaborationParticipantLease {
        $row = $this->one(<<<'SQL'
SELECT * FROM pa_collaboration_participant_lease
WHERE tenant_id = :tenant_id AND session_id = :session_id
  AND client_key = :client_key AND status = 'active'
SQL . ($forUpdate ? ' FOR UPDATE' : ''), [
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'client_key' => $clientKey,
        ]);

        return $row === null ? null : CollaborationParticipantLease::fromRow($row);
    }

    public function createLease(
        int $tenantId,
        int $sessionId,
        string $leaseKey,
        string $clientKey,
        int $memberId,
        int $accountId,
        string $capability,
        string $authorizationBasisSha256,
        string $expiresAt,
        string $now,
    ): CollaborationParticipantLease {
        $session = $this->sessionById($tenantId, $sessionId, true)
            ?? throw $this->notFound('The collaboration session is unavailable.');
        if (!$session->isActive() || $session->expiresAt <= $now || $expiresAt > $session->expiresAt) {
            throw $this->conflict('The collaboration session is not active.');
        }

        try {
            $this->execute(<<<'SQL'
INSERT INTO pa_collaboration_participant_lease (
  tenant_id, session_id, lease_key, client_key, member_id, account_id,
  capability, authorization_basis_sha256, status, revision, issued_at,
  heartbeat_at, expires_at, revoked_at
) VALUES (
  :tenant_id, :session_id, :lease_key, :client_key, :member_id, :account_id,
  :capability, :authorization_basis_sha256, 'active', 1, :issued_at,
  :heartbeat_at, :expires_at, NULL
)
SQL, [
                'tenant_id' => $tenantId,
                'session_id' => $sessionId,
                'lease_key' => $leaseKey,
                'client_key' => $clientKey,
                'member_id' => $memberId,
                'account_id' => $accountId,
                'capability' => $capability,
                'authorization_basis_sha256' => $authorizationBasisSha256,
                'issued_at' => $now,
                'heartbeat_at' => $now,
                'expires_at' => $expiresAt,
            ]);
        } catch (PDOException $exception) {
            if ($this->duplicate($exception)) {
                throw $this->conflict('The collaboration participant lease identity is unavailable.');
            }
            throw $exception;
        }

        return $this->lease($tenantId, $sessionId, $leaseKey)
            ?? throw $this->internal('The collaboration participant lease could not be read back.');
    }

    public function heartbeatLease(
        int $tenantId,
        int $sessionId,
        string $leaseKey,
        int $expectedRevision,
        string $expiresAt,
        string $now,
    ): CollaborationParticipantLease {
        $session = $this->sessionById($tenantId, $sessionId, true)
            ?? throw $this->notFound('The collaboration session is unavailable.');
        $lease = $this->lease($tenantId, $sessionId, $leaseKey, true)
            ?? throw $this->notFound('The collaboration participant lease is unavailable.');
        if (!$session->isActive() || $session->expiresAt <= $now || !$lease->isActive()
            || $lease->revision !== $expectedRevision || $lease->expiresAt <= $now
            || $expiresAt <= $lease->expiresAt || $expiresAt > $session->expiresAt) {
            throw $this->conflict('The collaboration participant lease has changed.');
        }

        $updated = $this->execute(<<<'SQL'
UPDATE pa_collaboration_participant_lease
SET revision = revision + 1, heartbeat_at = :heartbeat_at, expires_at = :expires_at
WHERE tenant_id = :tenant_id AND session_id = :session_id AND lease_key = :lease_key
  AND status = 'active' AND revision = :expected_revision AND expires_at > :heartbeat_at
SQL, [
            'heartbeat_at' => $now,
            'expires_at' => $expiresAt,
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'lease_key' => $leaseKey,
            'expected_revision' => $expectedRevision,
        ]);
        if ($updated !== 1) {
            throw $this->conflict('The collaboration participant lease has changed.');
        }

        return $this->lease($tenantId, $sessionId, $leaseKey)
            ?? throw $this->internal('The collaboration participant lease could not be read back.');
    }

    public function expireLeases(int $tenantId, int $sessionId, string $now): int
    {
        return $this->execute(<<<'SQL'
UPDATE pa_collaboration_participant_lease
SET status = 'expired', revision = revision + 1
WHERE tenant_id = :tenant_id AND session_id = :session_id
  AND status = 'active' AND expires_at <= :expires_at
SQL, ['tenant_id' => $tenantId, 'session_id' => $sessionId, 'expires_at' => $now]);
    }

    public function revokeLeases(int $tenantId, int $sessionId, string $now): int
    {
        return $this->execute(<<<'SQL'
UPDATE pa_collaboration_participant_lease
SET status = 'revoked', revision = revision + 1, revoked_at = :revoked_at
WHERE tenant_id = :tenant_id AND session_id = :session_id AND status = 'active'
SQL, ['revoked_at' => $now, 'tenant_id' => $tenantId, 'session_id' => $sessionId]);
    }

    public function updateEnvelope(
        int $tenantId,
        int $sessionId,
        string $updateKey,
    ): ?CollaborationUpdateEnvelope {
        $row = $this->one(<<<'SQL'
SELECT * FROM pa_collaboration_update_envelope
WHERE tenant_id = :tenant_id AND session_id = :session_id AND update_key = :update_key
SQL, ['tenant_id' => $tenantId, 'session_id' => $sessionId, 'update_key' => $updateKey]);

        return $this->updateModel($row);
    }

    public function appendUpdate(
        int $tenantId,
        int $sessionId,
        int $expectedSessionRevision,
        int $expectedLatestSequence,
        string $updateKey,
        string $clientKey,
        string $leaseKey,
        string $engineName,
        string $engineVersion,
        string $opaquePayload,
        string $updateSha256,
        int $memberId,
        int $accountId,
        string $now,
    ): CollaborationUpdateEnvelope {
        $session = $this->sessionById($tenantId, $sessionId, true)
            ?? throw $this->notFound('The collaboration session is unavailable.');
        if (!$session->isActive() || $session->revision !== $expectedSessionRevision
            || $session->latestSequence !== $expectedLatestSequence
            || $session->engineName !== $engineName || $session->engineVersion !== $engineVersion
            || $session->expiresAt <= $now || $expectedLatestSequence >= self::MAX_SEQUENCE) {
            throw $this->conflict('The collaboration session has changed.');
        }
        $lease = $this->lease($tenantId, $sessionId, $leaseKey, true)
            ?? throw $this->notFound('The collaboration participant lease is unavailable.');
        if (!$lease->isActive() || $lease->capability !== 'write' || $lease->clientKey !== $clientKey
            || $lease->memberId !== $memberId || $lease->accountId !== $accountId || $lease->expiresAt <= $now) {
            throw $this->conflict('The collaboration participant lease is unavailable.');
        }
        $byteLength = strlen($opaquePayload);
        if ($byteLength < 1 || $byteLength > 262_144
            || !hash_equals($updateSha256, hash('sha256', $opaquePayload))) {
            throw $this->integrity('The collaboration update envelope is invalid.');
        }
        $sequence = $expectedLatestSequence + 1;

        try {
            $this->execute(<<<'SQL'
INSERT INTO pa_collaboration_update_envelope (
  tenant_id, session_id, sequence_no, update_key, client_key, lease_key,
  engine_name, engine_version, byte_length, update_sha256, opaque_payload,
  author_member_id, author_account_id, occurred_at
) VALUES (
  :tenant_id, :session_id, :sequence_no, :update_key, :client_key, :lease_key,
  :engine_name, :engine_version, :byte_length, :update_sha256, :opaque_payload,
  :member_id, :account_id, :occurred_at
)
SQL, [
                'tenant_id' => $tenantId,
                'session_id' => $sessionId,
                'sequence_no' => $sequence,
                'update_key' => $updateKey,
                'client_key' => $clientKey,
                'lease_key' => $leaseKey,
                'engine_name' => $engineName,
                'engine_version' => $engineVersion,
                'byte_length' => $byteLength,
                'update_sha256' => $updateSha256,
                'opaque_payload' => $opaquePayload,
                'member_id' => $memberId,
                'account_id' => $accountId,
                'occurred_at' => $now,
            ]);
        } catch (PDOException $exception) {
            if ($this->duplicate($exception)) {
                throw $this->conflict('The collaboration update identity is unavailable.');
            }
            throw $exception;
        }

        $advanced = $this->execute(<<<'SQL'
UPDATE pa_collaboration_session
SET latest_sequence = :latest_sequence, revision = revision + 1, updated_at = :updated_at
WHERE tenant_id = :tenant_id AND id = :session_id AND status = 'active'
  AND revision = :expected_revision AND latest_sequence = :expected_latest_sequence
SQL, [
            'latest_sequence' => $sequence,
            'updated_at' => $now,
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'expected_revision' => $expectedSessionRevision,
            'expected_latest_sequence' => $expectedLatestSequence,
        ]);
        if ($advanced !== 1) {
            throw $this->conflict('The collaboration session has changed.');
        }

        return $this->updateEnvelope($tenantId, $sessionId, $updateKey)
            ?? throw $this->internal('The collaboration update envelope could not be read back.');
    }

    public function updatesAfter(int $tenantId, int $sessionId, int $afterSequence, int $pageSize): array
    {
        if ($afterSequence < 0 || $pageSize < 1 || $pageSize > 1000) {
            throw $this->conflict('The collaboration update page is invalid.');
        }
        $rows = $this->all(<<<SQL
SELECT * FROM pa_collaboration_update_envelope
WHERE tenant_id = :tenant_id AND session_id = :session_id AND sequence_no > :after_sequence
ORDER BY sequence_no ASC
LIMIT {$pageSize}
SQL, ['tenant_id' => $tenantId, 'session_id' => $sessionId, 'after_sequence' => $afterSequence]);

        return array_map(fn(array $row): CollaborationUpdateEnvelope => $this->requiredUpdateModel($row), $rows);
    }

    public function updateWindowAfter(int $tenantId, int $sessionId, int $coveredSequence): array
    {
        if ($coveredSequence < 0) {
            throw $this->conflict('The collaboration snapshot sequence is invalid.');
        }
        $row = $this->one(<<<'SQL'
SELECT COUNT(*) AS update_count, COALESCE(SUM(byte_length), 0) AS update_bytes
FROM pa_collaboration_update_envelope
WHERE tenant_id = :tenant_id AND session_id = :session_id AND sequence_no > :covered_sequence
SQL, ['tenant_id' => $tenantId, 'session_id' => $sessionId, 'covered_sequence' => $coveredSequence])
            ?? throw $this->internal('The collaboration update window could not be read.');

        return ['count' => (int) $row['update_count'], 'bytes' => (int) $row['update_bytes']];
    }

    public function snapshotEnvelope(
        int $tenantId,
        int $sessionId,
        string $snapshotKey,
    ): ?CollaborationSnapshotEnvelope {
        $row = $this->one(<<<'SQL'
SELECT * FROM pa_collaboration_snapshot_envelope
WHERE tenant_id = :tenant_id AND session_id = :session_id AND snapshot_key = :snapshot_key
SQL, ['tenant_id' => $tenantId, 'session_id' => $sessionId, 'snapshot_key' => $snapshotKey]);

        return $this->snapshotModel($row);
    }

    public function latestSnapshot(int $tenantId, int $sessionId): ?CollaborationSnapshotEnvelope
    {
        $row = $this->one(<<<'SQL'
SELECT * FROM pa_collaboration_snapshot_envelope
WHERE tenant_id = :tenant_id AND session_id = :session_id
ORDER BY covered_sequence DESC, id DESC
LIMIT 1
SQL, ['tenant_id' => $tenantId, 'session_id' => $sessionId]);

        return $this->snapshotModel($row);
    }

    public function saveSnapshot(
        int $tenantId,
        int $sessionId,
        int $expectedSessionRevision,
        string $snapshotKey,
        int $coveredSequence,
        string $engineName,
        string $engineVersion,
        string $opaqueSnapshot,
        string $snapshotSha256,
        string $opaqueStateVector,
        string $stateVectorSha256,
        int $memberId,
        int $accountId,
        string $now,
        string $retainUntil,
    ): CollaborationSnapshotEnvelope {
        $session = $this->sessionById($tenantId, $sessionId, true)
            ?? throw $this->notFound('The collaboration session is unavailable.');
        if (!$session->isActive() || $session->revision !== $expectedSessionRevision
            || $session->engineName !== $engineName || $session->engineVersion !== $engineVersion
            || $session->expiresAt <= $now || $coveredSequence < 0
            || $coveredSequence > $session->latestSequence) {
            throw $this->conflict('The collaboration snapshot sequence is unavailable.');
        }
        if ($coveredSequence > 0 && !$this->updateSequenceExists($tenantId, $sessionId, $coveredSequence)) {
            throw $this->conflict('The collaboration snapshot sequence is unavailable.');
        }
        $snapshotLength = strlen($opaqueSnapshot);
        $stateVectorLength = strlen($opaqueStateVector);
        if ($snapshotLength < 1 || $snapshotLength > 8_388_608
            || $stateVectorLength < 1 || $stateVectorLength > 8_388_608
            || !hash_equals($snapshotSha256, hash('sha256', $opaqueSnapshot))
            || !hash_equals($stateVectorSha256, hash('sha256', $opaqueStateVector))) {
            throw $this->integrity('The collaboration snapshot envelope is invalid.');
        }

        try {
            $this->execute(<<<'SQL'
INSERT INTO pa_collaboration_snapshot_envelope (
  tenant_id, session_id, snapshot_key, covered_sequence, engine_name,
  engine_version, snapshot_byte_length, snapshot_sha256, opaque_snapshot,
  state_vector_byte_length, state_vector_sha256, opaque_state_vector,
  author_member_id, author_account_id, created_at, retain_until
) VALUES (
  :tenant_id, :session_id, :snapshot_key, :covered_sequence, :engine_name,
  :engine_version, :snapshot_byte_length, :snapshot_sha256, :opaque_snapshot,
  :state_vector_byte_length, :state_vector_sha256, :opaque_state_vector,
  :member_id, :account_id, :created_at, :retain_until
)
SQL, [
                'tenant_id' => $tenantId,
                'session_id' => $sessionId,
                'snapshot_key' => $snapshotKey,
                'covered_sequence' => $coveredSequence,
                'engine_name' => $engineName,
                'engine_version' => $engineVersion,
                'snapshot_byte_length' => $snapshotLength,
                'snapshot_sha256' => $snapshotSha256,
                'opaque_snapshot' => $opaqueSnapshot,
                'state_vector_byte_length' => $stateVectorLength,
                'state_vector_sha256' => $stateVectorSha256,
                'opaque_state_vector' => $opaqueStateVector,
                'member_id' => $memberId,
                'account_id' => $accountId,
                'created_at' => $now,
                'retain_until' => $retainUntil,
            ]);
        } catch (PDOException $exception) {
            if ($this->duplicate($exception)) {
                throw $this->conflict('The collaboration snapshot identity is unavailable.');
            }
            throw $exception;
        }

        $updated = $this->execute(<<<'SQL'
UPDATE pa_collaboration_session
SET revision = revision + 1, updated_at = :updated_at
WHERE tenant_id = :tenant_id AND id = :session_id
  AND status = 'active' AND revision = :expected_revision
SQL, [
            'updated_at' => $now,
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'expected_revision' => $expectedSessionRevision,
        ]);
        if ($updated !== 1) {
            throw $this->conflict('The collaboration session has changed.');
        }

        return $this->snapshotEnvelope($tenantId, $sessionId, $snapshotKey)
            ?? throw $this->internal('The collaboration snapshot envelope could not be read back.');
    }

    public function retainSnapshotsUntil(int $tenantId, int $sessionId, string $retainUntil): int
    {
        return $this->execute(<<<'SQL'
UPDATE pa_collaboration_snapshot_envelope
SET retain_until = GREATEST(retain_until, :retain_until)
WHERE tenant_id = :tenant_id AND session_id = :session_id
SQL, ['retain_until' => $retainUntil, 'tenant_id' => $tenantId, 'session_id' => $sessionId]);
    }

    private function sessionById(int $tenantId, int $sessionId, bool $forUpdate = false): ?CollaborationSession
    {
        $row = $this->one(<<<'SQL'
SELECT * FROM pa_collaboration_session
WHERE tenant_id = :tenant_id AND id = :session_id
SQL . ($forUpdate ? ' FOR UPDATE' : ''), ['tenant_id' => $tenantId, 'session_id' => $sessionId]);

        return $row === null ? null : CollaborationSession::fromRow($row);
    }

    private function updateSequenceExists(int $tenantId, int $sessionId, int $sequence): bool
    {
        return $this->one(<<<'SQL'
SELECT id FROM pa_collaboration_update_envelope
WHERE tenant_id = :tenant_id AND session_id = :session_id AND sequence_no = :sequence_no
SQL, ['tenant_id' => $tenantId, 'session_id' => $sessionId, 'sequence_no' => $sequence]) !== null;
    }

    /** @param array<string, mixed>|null $row */
    private function updateModel(?array $row): ?CollaborationUpdateEnvelope
    {
        if ($row === null) {
            return null;
        }

        return $this->requiredUpdateModel($row);
    }

    /** @param array<string, mixed> $row */
    private function requiredUpdateModel(array $row): CollaborationUpdateEnvelope
    {
        $model = CollaborationUpdateEnvelope::fromRow($row);
        try {
            $model->assertPayloadIntegrity();
        } catch (UnexpectedValueException $exception) {
            throw $this->integrity('Collaboration update envelope integrity failure.', $exception);
        }

        return $model;
    }

    /** @param array<string, mixed>|null $row */
    private function snapshotModel(?array $row): ?CollaborationSnapshotEnvelope
    {
        if ($row === null) {
            return null;
        }
        $model = CollaborationSnapshotEnvelope::fromRow($row);
        try {
            $model->assertPayloadIntegrity();
        } catch (UnexpectedValueException $exception) {
            throw $this->integrity('Collaboration snapshot envelope integrity failure.', $exception);
        }

        return $model;
    }

    /** @param array<string, int|string|null> $parameters
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $parameters): ?array
    {
        $statement = $this->statement($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array<string, int|string|null> $parameters
     * @return list<array<string, mixed>>
     */
    private function all(string $sql, array $parameters): array
    {
        $statement = $this->statement($sql);
        $statement->execute($parameters);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($rows, 'is_array'));
    }

    /** @param array<string, int|string|null> $parameters */
    private function execute(string $sql, array $parameters): int
    {
        $statement = $this->statement($sql);
        $statement->execute($parameters);

        return $statement->rowCount();
    }

    private function statement(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw $this->internal('Could not prepare collaboration statement.');
        }

        return $statement;
    }

    private function duplicate(PDOException $exception): bool
    {
        return (string) ($exception->errorInfo[0] ?? $exception->getCode()) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    private function conflict(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }

    private function notFound(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }

    private function internal(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }

    private function integrity(string $message, ?\Throwable $previous = null): UnexpectedValueException
    {
        return new UnexpectedValueException($message, 0, $previous);
    }
}
