<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Persistence;

use PDO;
use PeanutAdmin\Collaboration\Model\CollaborationParticipantLease;
use PeanutAdmin\Collaboration\Model\CollaborationSession;
use PeanutAdmin\Collaboration\Model\CollaborationSnapshotEnvelope;
use PeanutAdmin\Collaboration\Model\CollaborationUpdateEnvelope;

interface CollaborationRepository
{
    public function connection(): PDO;

    public function session(int $tenantId, string $sessionKey, bool $forUpdate = false): ?CollaborationSession;

    public function activeSession(
        int $tenantId,
        string $artifactType,
        string $artifactKey,
        bool $forUpdate = false,
    ): ?CollaborationSession;

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
    ): CollaborationSession;

    /**
     * Complete one active session. Published completion supplies the immutable
     * revision fields; closed and expired completion leave them null.
     */
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
    ): CollaborationSession;

    public function lease(
        int $tenantId,
        int $sessionId,
        string $leaseKey,
        bool $forUpdate = false,
    ): ?CollaborationParticipantLease;

    public function activeLeaseForClient(
        int $tenantId,
        int $sessionId,
        string $clientKey,
        bool $forUpdate = false,
    ): ?CollaborationParticipantLease;

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
    ): CollaborationParticipantLease;

    public function heartbeatLease(
        int $tenantId,
        int $sessionId,
        string $leaseKey,
        int $expectedRevision,
        string $expiresAt,
        string $now,
    ): CollaborationParticipantLease;

    public function expireLeases(int $tenantId, int $sessionId, string $now): int;

    public function revokeLeases(int $tenantId, int $sessionId, string $now): int;

    public function updateEnvelope(
        int $tenantId,
        int $sessionId,
        string $updateKey,
    ): ?CollaborationUpdateEnvelope;

    /**
     * Append under the caller's transaction after it has validated the locked
     * session and lease. The repository allocates exactly the next sequence.
     */
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
    ): CollaborationUpdateEnvelope;

    /** @return list<CollaborationUpdateEnvelope> */
    public function updatesAfter(int $tenantId, int $sessionId, int $afterSequence, int $pageSize): array;

    /** @return array{count: int, bytes: int} */
    public function updateWindowAfter(int $tenantId, int $sessionId, int $coveredSequence): array;

    public function snapshotEnvelope(
        int $tenantId,
        int $sessionId,
        string $snapshotKey,
    ): ?CollaborationSnapshotEnvelope;

    public function latestSnapshot(int $tenantId, int $sessionId): ?CollaborationSnapshotEnvelope;

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
    ): CollaborationSnapshotEnvelope;

    public function retainSnapshotsUntil(
        int $tenantId,
        int $sessionId,
        string $retainUntil,
    ): int;
}
