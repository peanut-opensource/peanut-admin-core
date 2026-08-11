<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Application;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PeanutAdmin\Collaboration\Contract\CollaborationPolicy;
use PeanutAdmin\Collaboration\Contract\CollaborationPolicyProvider;
use PeanutAdmin\Collaboration\Contract\CollaborationRevisionPublisher;
use PeanutAdmin\Collaboration\Contract\CollaborationSubmission;
use PeanutAdmin\Collaboration\Contract\CollaborationSubmissionProvider;
use PeanutAdmin\Collaboration\Model\CollaborationParticipantLease;
use PeanutAdmin\Collaboration\Model\CollaborationSession;
use PeanutAdmin\Collaboration\Package;
use PeanutAdmin\Collaboration\Persistence\CollaborationRepository;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Idempotency\IdempotencyKey;
use PeanutAdmin\Kernel\Idempotency\PdoIdempotencyRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoTransactionManager;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final readonly class CollaborationService
{
    private const MAX_READ_PAGE = 100;

    private PdoTransactionManager $transactions;
    private PdoIdempotencyRepository $idempotency;
    private PdoAuditRepository $audit;
    private Closure $clock;

    public function __construct(
        private CollaborationRepository $repository,
        private CollaborationPolicyProvider $policies,
        private CollaborationSubmissionProvider $submissions,
        private CollaborationRevisionPublisher $revisions,
        ?Closure $clock = null,
    ) {
        try {
            $pdo = $repository->connection();
            foreach ([$policies, $submissions, $revisions] as $adapter) {
                if ($adapter->connection() !== $pdo) {
                    throw CollaborationException::providerUnavailable();
                }
            }
        } catch (CollaborationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw CollaborationException::providerUnavailable();
        }
        $this->transactions = new PdoTransactionManager($pdo);
        $this->idempotency = new PdoIdempotencyRepository($pdo);
        $this->audit = new PdoAuditRepository($pdo);
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function openSession(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $engineName,
        string $engineVersion,
        string $baseRevisionKey,
        string $baseRevisionSha256,
        string $idempotencyKey,
    ): CollaborationReceipt {
        $this->assertContext($context, $artifactType, $artifactKey, 'write');
        $this->assertArtifactIdentity($artifactType, $artifactKey);
        $this->assertIdentifier($engineName, 64);
        $this->assertIdentifier($engineVersion, 64);
        $this->assertKey($baseRevisionKey, 'revision');
        $this->assertDigest($baseRevisionSha256);

        return $this->command(
            $context,
            Package::OPEN_OPERATION,
            [
                'artifact_type' => $artifactType,
                'artifact_key' => $artifactKey,
                'engine_name' => $engineName,
                'engine_version' => $engineVersion,
                'base_revision_key' => $baseRevisionKey,
                'base_revision_sha256' => $baseRevisionSha256,
                'authorization_basis_digest' => $context->authorizationBasisDigest,
            ],
            $idempotencyKey,
            function (DateTimeImmutable $now, string $timestamp) use (
                $context,
                $artifactType,
                $artifactKey,
                $engineName,
                $engineVersion,
                $baseRevisionKey,
                $baseRevisionSha256,
            ): CollaborationReceipt {
                $policy = $this->policy($context, $artifactType, $artifactKey, 'write', $now);
                $tenantId = $context->tenantContext->tenantId;
                $active = $this->repository->activeSession($tenantId, $artifactType, $artifactKey, true);
                if ($active !== null) {
                    if ($active->expiresAt > $timestamp) {
                        throw CollaborationException::conflict();
                    }
                    $retainUntil = $this->after($now, $policy->retentionSeconds);
                    $this->repository->revokeLeases($tenantId, $active->id, $timestamp);
                    $this->repository->retainSnapshotsUntil($tenantId, $active->id, $retainUntil);
                    $this->repository->completeSession(
                        $tenantId,
                        $active->id,
                        $active->revision,
                        'expired',
                        null,
                        null,
                        null,
                        null,
                        $timestamp,
                        $retainUntil,
                    );
                }
                $this->assertBaseRevision(
                    $context,
                    $artifactType,
                    $artifactKey,
                    $baseRevisionKey,
                    $baseRevisionSha256,
                );
                $session = $this->repository->createSession(
                    $tenantId,
                    $this->newKey('session'),
                    $artifactType,
                    $artifactKey,
                    $engineName,
                    $engineVersion,
                    $baseRevisionKey,
                    $baseRevisionSha256,
                    $context->tenantContext->memberId,
                    $context->tenantContext->accountId,
                    $this->after($now, $policy->sessionTtlSeconds),
                    $timestamp,
                );
                $receipt = new CollaborationReceipt(
                    Package::OPEN_OPERATION,
                    $session->sessionKey,
                    $session->status,
                    $session->revision,
                    $session->baseRevisionKey,
                    $session->baseRevisionSha256,
                );
                $this->audit($context, 'tenant.collaboration.session.opened', $session, [
                    'session_key' => $session->sessionKey,
                    'base_revision_key' => $session->baseRevisionKey,
                    'base_revision_sha256' => $session->baseRevisionSha256,
                ]);

                return $receipt;
            },
        );
    }

    public function joinSession(
        AuthorizedOperationContext $context,
        string $sessionKey,
        string $clientKey,
        string $capability,
        string $idempotencyKey,
    ): CollaborationReceipt {
        $this->assertTrustedIdentity($context);
        $this->assertKey($sessionKey, 'session');
        $this->assertAscii($clientKey, 128);
        if (!in_array($capability, ['read', 'write'], true)) {
            throw CollaborationException::invalid();
        }

        return $this->command(
            $context,
            Package::JOIN_OPERATION,
            [
                'session_key' => $sessionKey,
                'client_key' => $clientKey,
                'capability' => $capability,
                'authorization_basis_digest' => $context->authorizationBasisDigest,
            ],
            $idempotencyKey,
            function (DateTimeImmutable $now, string $timestamp) use (
                $context,
                $sessionKey,
                $clientKey,
                $capability,
            ): CollaborationReceipt {
                $session = $this->activeSession($context, $sessionKey, $capability, $timestamp);
                $policy = $this->policy($context, $session->artifactType, $session->artifactKey, $capability, $now);
                $tenantId = $context->tenantContext->tenantId;
                $this->repository->expireLeases($tenantId, $session->id, $timestamp);
                if ($this->repository->activeLeaseForClient($tenantId, $session->id, $clientKey, true) !== null) {
                    throw CollaborationException::conflict();
                }
                $lease = $this->repository->createLease(
                    $tenantId,
                    $session->id,
                    $this->newKey('lease'),
                    $clientKey,
                    $context->tenantContext->memberId,
                    $context->tenantContext->accountId,
                    $capability,
                    $context->authorizationBasisDigest,
                    $this->leaseExpiry($now, $policy, $session),
                    $timestamp,
                );
                $receipt = $this->leaseReceipt(Package::JOIN_OPERATION, $session, $lease);
                $this->audit($context, 'tenant.collaboration.session.joined', $session, [
                    'session_key' => $session->sessionKey,
                    'lease_key' => $lease->leaseKey,
                    'capability' => $lease->capability,
                    'authorization_basis_sha256' => $lease->authorizationBasisSha256,
                ]);

                return $receipt;
            },
        );
    }

    public function heartbeat(
        AuthorizedOperationContext $context,
        string $sessionKey,
        string $leaseKey,
        string $idempotencyKey,
    ): CollaborationReceipt {
        $this->assertTrustedIdentity($context);
        $this->assertKey($sessionKey, 'session');
        $this->assertKey($leaseKey, 'lease');

        return $this->command(
            $context,
            Package::HEARTBEAT_OPERATION,
            [
                'session_key' => $sessionKey,
                'lease_key' => $leaseKey,
                'authorization_basis_digest' => $context->authorizationBasisDigest,
            ],
            $idempotencyKey,
            function (DateTimeImmutable $now, string $timestamp) use ($context, $sessionKey, $leaseKey): CollaborationReceipt {
                $session = $this->session($context, $sessionKey, $timestamp);
                $lease = $this->lease($context, $session, $leaseKey, $timestamp, true);
                $policy = $this->policy(
                    $context,
                    $session->artifactType,
                    $session->artifactKey,
                    $lease->capability,
                    $now,
                );
                $renewed = $this->repository->heartbeatLease(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $lease->leaseKey,
                    $lease->revision,
                    $this->leaseExpiry($now, $policy, $session),
                    $timestamp,
                );
                $receipt = $this->leaseReceipt(Package::HEARTBEAT_OPERATION, $session, $renewed);
                $this->audit($context, 'tenant.collaboration.lease.heartbeat', $session, [
                    'session_key' => $session->sessionKey,
                    'lease_key' => $renewed->leaseKey,
                    'capability' => $renewed->capability,
                    'authorization_basis_sha256' => $renewed->authorizationBasisSha256,
                ]);

                return $receipt;
            },
        );
    }

    public function appendUpdate(
        AuthorizedOperationContext $context,
        string $sessionKey,
        string $leaseKey,
        string $clientKey,
        int $baseSequence,
        string $opaquePayload,
        string $updateSha256,
        string $idempotencyKey,
    ): CollaborationReceipt {
        $this->assertTrustedIdentity($context);
        $this->assertKey($sessionKey, 'session');
        $this->assertKey($leaseKey, 'lease');
        $this->assertAscii($clientKey, 128);
        $this->assertNonNegative($baseSequence);
        $this->assertDigest($updateSha256);
        if ($opaquePayload === '') {
            throw CollaborationException::invalid();
        }
        if (!hash_equals($updateSha256, hash('sha256', $opaquePayload))) {
            throw CollaborationException::integrityFailure();
        }

        return $this->command(
            $context,
            Package::APPEND_OPERATION,
            [
                'session_key' => $sessionKey,
                'lease_key' => $leaseKey,
                'client_key' => $clientKey,
                'base_sequence' => $baseSequence,
                'byte_length' => strlen($opaquePayload),
                'update_sha256' => $updateSha256,
                'authorization_basis_digest' => $context->authorizationBasisDigest,
            ],
            $idempotencyKey,
            function (DateTimeImmutable $now, string $timestamp) use (
                $context,
                $sessionKey,
                $leaseKey,
                $clientKey,
                $baseSequence,
                $opaquePayload,
                $updateSha256,
            ): CollaborationReceipt {
                $session = $this->session($context, $sessionKey, $timestamp);
                $policy = $this->policy($context, $session->artifactType, $session->artifactKey, 'write', $now);
                $lease = $this->lease($context, $session, $leaseKey, $timestamp, true);
                if ($lease->capability !== 'write' || !hash_equals($lease->clientKey, $clientKey)) {
                    throw CollaborationException::denied();
                }
                if ($baseSequence > $session->latestSequence) {
                    throw CollaborationException::invalid();
                }
                $byteLength = strlen($opaquePayload);
                if ($byteLength > $policy->updateLimitBytes) {
                    throw CollaborationException::payloadTooLarge();
                }
                $snapshot = $this->repository->latestSnapshot($context->tenantContext->tenantId, $session->id);
                $covered = $snapshot?->coveredSequence ?? 0;
                $window = $this->repository->updateWindowAfter(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $covered,
                );
                if ($window['count'] >= $policy->maxUnsnapshottedUpdates
                    || $window['bytes'] >= $policy->snapshotLimitBytes
                    || $byteLength > $policy->snapshotLimitBytes - $window['bytes']) {
                    throw CollaborationException::backpressure();
                }
                $update = $this->repository->appendUpdate(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $session->revision,
                    $session->latestSequence,
                    $this->newKey('update'),
                    $clientKey,
                    $leaseKey,
                    $session->engineName,
                    $session->engineVersion,
                    $opaquePayload,
                    $updateSha256,
                    $context->tenantContext->memberId,
                    $context->tenantContext->accountId,
                    $timestamp,
                );
                $receipt = new CollaborationReceipt(
                    Package::APPEND_OPERATION,
                    $session->sessionKey,
                    'active',
                    $session->revision + 1,
                    updateKey: $update->updateKey,
                    sequence: $update->sequenceNo,
                    byteLength: $update->byteLength,
                    sha256: $update->updateSha256,
                );
                $this->audit($context, 'tenant.collaboration.update.appended', $session, [
                    'session_key' => $session->sessionKey,
                    'update_key' => $update->updateKey,
                    'lease_key' => $lease->leaseKey,
                    'capability' => $lease->capability,
                    'sequence' => $update->sequenceNo,
                    'byte_length' => $update->byteLength,
                    'sha256' => $update->updateSha256,
                ]);

                return $receipt;
            },
        );
    }

    public function saveSnapshot(
        AuthorizedOperationContext $context,
        string $sessionKey,
        string $leaseKey,
        int $coveredSequence,
        string $opaqueSnapshot,
        string $snapshotSha256,
        string $opaqueStateVector,
        string $stateVectorSha256,
        string $idempotencyKey,
    ): CollaborationReceipt {
        $this->assertTrustedIdentity($context);
        $this->assertKey($sessionKey, 'session');
        $this->assertKey($leaseKey, 'lease');
        $this->assertNonNegative($coveredSequence);
        $this->assertDigest($snapshotSha256);
        $this->assertDigest($stateVectorSha256);
        if ($opaqueSnapshot === '' || $opaqueStateVector === '') {
            throw CollaborationException::invalid();
        }
        if (!hash_equals($snapshotSha256, hash('sha256', $opaqueSnapshot))
            || !hash_equals($stateVectorSha256, hash('sha256', $opaqueStateVector))) {
            throw CollaborationException::integrityFailure();
        }

        return $this->command(
            $context,
            Package::SNAPSHOT_OPERATION,
            [
                'session_key' => $sessionKey,
                'lease_key' => $leaseKey,
                'covered_sequence' => $coveredSequence,
                'snapshot_byte_length' => strlen($opaqueSnapshot),
                'snapshot_sha256' => $snapshotSha256,
                'state_vector_byte_length' => strlen($opaqueStateVector),
                'state_vector_sha256' => $stateVectorSha256,
                'authorization_basis_digest' => $context->authorizationBasisDigest,
            ],
            $idempotencyKey,
            function (DateTimeImmutable $now, string $timestamp) use (
                $context,
                $sessionKey,
                $leaseKey,
                $coveredSequence,
                $opaqueSnapshot,
                $snapshotSha256,
                $opaqueStateVector,
                $stateVectorSha256,
            ): CollaborationReceipt {
                $session = $this->session($context, $sessionKey, $timestamp);
                $policy = $this->policy($context, $session->artifactType, $session->artifactKey, 'write', $now);
                $lease = $this->lease($context, $session, $leaseKey, $timestamp, true);
                if ($lease->capability !== 'write') {
                    throw CollaborationException::denied();
                }
                if (strlen($opaqueSnapshot) > $policy->snapshotLimitBytes
                    || strlen($opaqueStateVector) > $policy->snapshotLimitBytes) {
                    throw CollaborationException::payloadTooLarge();
                }
                if ($coveredSequence > $session->latestSequence) {
                    throw CollaborationException::invalid();
                }
                $snapshot = $this->repository->saveSnapshot(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $session->revision,
                    $this->newKey('snapshot'),
                    $coveredSequence,
                    $session->engineName,
                    $session->engineVersion,
                    $opaqueSnapshot,
                    $snapshotSha256,
                    $opaqueStateVector,
                    $stateVectorSha256,
                    $context->tenantContext->memberId,
                    $context->tenantContext->accountId,
                    $timestamp,
                    $this->after($now, $policy->retentionSeconds),
                );
                $receipt = new CollaborationReceipt(
                    Package::SNAPSHOT_OPERATION,
                    $session->sessionKey,
                    'active',
                    $session->revision + 1,
                    byteLength: $snapshot->snapshotByteLength,
                    sha256: $snapshot->snapshotSha256,
                    snapshotKey: $snapshot->snapshotKey,
                    coveredSequence: $snapshot->coveredSequence,
                );
                $this->audit($context, 'tenant.collaboration.snapshot.saved', $session, [
                    'session_key' => $session->sessionKey,
                    'snapshot_key' => $snapshot->snapshotKey,
                    'lease_key' => $lease->leaseKey,
                    'capability' => $lease->capability,
                    'covered_sequence' => $snapshot->coveredSequence,
                    'byte_length' => $snapshot->snapshotByteLength,
                    'sha256' => $snapshot->snapshotSha256,
                ]);

                return $receipt;
            },
        );
    }

    public function state(
        AuthorizedOperationContext $context,
        string $sessionKey,
        int $afterSequence,
        int $pageSize = self::MAX_READ_PAGE,
    ): CollaborationState {
        $this->assertTrustedIdentity($context);
        $this->assertKey($sessionKey, 'session');
        $this->assertNonNegative($afterSequence);
        if ($pageSize < 1 || $pageSize > self::MAX_READ_PAGE) {
            throw CollaborationException::invalid();
        }
        if (!hash_equals($context->operation, 'read')) {
            throw CollaborationException::denied();
        }

        try {
            $now = $this->now();
            $timestamp = $this->databaseTime($now);
            return $this->transactions->run(function () use (
                $context,
                $sessionKey,
                $afterSequence,
                $pageSize,
                $now,
                $timestamp,
            ): CollaborationState {
                $session = $this->session($context, $sessionKey, $timestamp, false);
                $this->policy($context, $session->artifactType, $session->artifactKey, 'read', $now);
                if ($afterSequence > $session->latestSequence) {
                    throw CollaborationException::invalid();
                }
                $snapshot = $this->repository->latestSnapshot($context->tenantContext->tenantId, $session->id);
                $effectiveAfter = max($afterSequence, $snapshot?->coveredSequence ?? 0);
                $updates = $this->repository->updatesAfter(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $effectiveAfter,
                    $pageSize + 1,
                );
                $hasMore = count($updates) > $pageSize;
                if ($hasMore) {
                    array_pop($updates);
                }
                $last = $updates === [] ? null : $updates[count($updates) - 1];

                $visibleSnapshot = $snapshot !== null && $snapshot->coveredSequence >= $afterSequence
                    ? $snapshot
                    : null;

                return new CollaborationState(
                    $session,
                    $visibleSnapshot,
                    $updates,
                    $afterSequence,
                    $last?->sequenceNo ?? $effectiveAfter,
                    $hasMore,
                );
            });
        } catch (CollaborationException $exception) {
            throw $exception;
        } catch (UnexpectedValueException) {
            throw CollaborationException::integrityFailure();
        } catch (RuntimeException $exception) {
            throw $this->mapRepositoryFailure($exception);
        } catch (Throwable) {
            throw CollaborationException::internal();
        }
    }

    public function publish(
        AuthorizedOperationContext $context,
        string $sessionKey,
        string $idempotencyKey,
    ): CollaborationReceipt {
        $this->assertTrustedIdentity($context);
        $this->assertKey($sessionKey, 'session');
        if (!hash_equals($context->operation, 'publish')) {
            throw CollaborationException::denied();
        }

        return $this->command(
            $context,
            Package::PUBLISH_OPERATION,
            [
                'session_key' => $sessionKey,
                'authorization_basis_digest' => $context->authorizationBasisDigest,
            ],
            $idempotencyKey,
            function (DateTimeImmutable $now, string $timestamp) use (
                $context,
                $sessionKey,
                $idempotencyKey,
            ): CollaborationReceipt {
                $session = $this->session($context, $sessionKey, $timestamp);
                $policy = $this->policy($context, $session->artifactType, $session->artifactKey, 'publish', $now);
                $snapshot = $this->repository->latestSnapshot($context->tenantContext->tenantId, $session->id)
                    ?? throw CollaborationException::conflict();
                if ($snapshot->coveredSequence !== $session->latestSequence) {
                    throw CollaborationException::conflict();
                }
                $submission = $this->submission($context, $session, $snapshot, $now);
                $publication = $this->publishRevision($context, $session, $submission, $idempotencyKey);
                $retainUntil = $this->after($now, $policy->retentionSeconds);
                $completed = $this->repository->completeSession(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $session->revision,
                    'published',
                    $context->tenantContext->memberId,
                    $context->tenantContext->accountId,
                    $publication['revision_key'],
                    $publication['revision_sha256'],
                    $timestamp,
                    $retainUntil,
                );
                $this->repository->revokeLeases($context->tenantContext->tenantId, $session->id, $timestamp);
                $this->repository->retainSnapshotsUntil(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $retainUntil,
                );
                $receipt = new CollaborationReceipt(
                    Package::PUBLISH_OPERATION,
                    $completed->sessionKey,
                    $completed->status,
                    $completed->revision,
                    publishedRevisionKey: $publication['revision_key'],
                    publishedRevisionSha256: $publication['revision_sha256'],
                    retainUntil: $retainUntil,
                );
                $this->audit($context, 'tenant.collaboration.session.published', $completed, [
                    'session_key' => $completed->sessionKey,
                    'snapshot_key' => $snapshot->snapshotKey,
                    'sequence' => $session->latestSequence,
                    'sha256' => $snapshot->snapshotSha256,
                    'published_revision_key' => $publication['revision_key'],
                    'published_revision_sha256' => $publication['revision_sha256'],
                ]);

                return $receipt;
            },
        );
    }

    public function closeSession(
        AuthorizedOperationContext $context,
        string $sessionKey,
        string $idempotencyKey,
    ): CollaborationReceipt {
        $this->assertTrustedIdentity($context);
        $this->assertKey($sessionKey, 'session');
        if (!hash_equals($context->operation, 'write')) {
            throw CollaborationException::denied();
        }

        return $this->command(
            $context,
            Package::CLOSE_OPERATION,
            [
                'session_key' => $sessionKey,
                'authorization_basis_digest' => $context->authorizationBasisDigest,
            ],
            $idempotencyKey,
            function (DateTimeImmutable $now, string $timestamp) use ($context, $sessionKey): CollaborationReceipt {
                $session = $this->session($context, $sessionKey, $timestamp);
                $policy = $this->policy($context, $session->artifactType, $session->artifactKey, 'write', $now);
                $retainUntil = $this->after($now, $policy->retentionSeconds);
                $completed = $this->repository->completeSession(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $session->revision,
                    'closed',
                    $context->tenantContext->memberId,
                    $context->tenantContext->accountId,
                    null,
                    null,
                    $timestamp,
                    $retainUntil,
                );
                $this->repository->revokeLeases($context->tenantContext->tenantId, $session->id, $timestamp);
                $this->repository->retainSnapshotsUntil(
                    $context->tenantContext->tenantId,
                    $session->id,
                    $retainUntil,
                );
                $receipt = new CollaborationReceipt(
                    Package::CLOSE_OPERATION,
                    $completed->sessionKey,
                    $completed->status,
                    $completed->revision,
                    retainUntil: $retainUntil,
                );
                $this->audit($context, 'tenant.collaboration.session.closed', $completed, [
                    'session_key' => $completed->sessionKey,
                ]);

                return $receipt;
            },
        );
    }

    /**
     * @param array<string, int|string|null> $semanticInputs
     * @param callable(DateTimeImmutable, string): CollaborationReceipt $operation
     */
    private function command(
        AuthorizedOperationContext $context,
        string $operationKey,
        array $semanticInputs,
        string $rawIdempotencyKey,
        callable $operation,
    ): CollaborationReceipt {
        try {
            $key = IdempotencyKey::fromString($rawIdempotencyKey);
            $requestHash = hash('sha256', $this->canonicalJson($semanticInputs));
            $now = $this->now();
            $expiresAt = $now->modify('+1 day');

            return $this->transactions->run(function () use (
                $context,
                $operationKey,
                $key,
                $requestHash,
                $now,
                $expiresAt,
                $operation,
            ): CollaborationReceipt {
                $record = $this->idempotency->beginTenant(
                    $context->tenantContext->tenantId,
                    $context->tenantContext->memberId,
                    $operationKey,
                    $key,
                    $requestHash,
                    $expiresAt,
                    $now,
                );
                if ($record->replayable()) {
                    return CollaborationReceipt::fromArray((array) $record->responseBody, $operationKey);
                }
                if (!$record->acquiredForExecution()) {
                    throw new ApiException(
                        'IDEMPOTENCY_REQUEST_PROCESSING',
                        409,
                        'Another collaboration request with this Idempotency-Key is processing.',
                    );
                }
                $receipt = $operation($now, $this->databaseTime($now));
                $this->idempotency->completeTenant(
                    $record->id,
                    200,
                    $receipt->toArray(),
                    'collaboration_session',
                    $receipt->sessionKey,
                );

                return $receipt;
            });
        } catch (CollaborationException|ApiException $exception) {
            throw $exception;
        } catch (UnexpectedValueException) {
            throw CollaborationException::integrityFailure();
        } catch (RuntimeException $exception) {
            throw $this->mapRepositoryFailure($exception);
        } catch (Throwable) {
            throw CollaborationException::internal();
        }
    }

    private function activeSession(
        AuthorizedOperationContext $context,
        string $sessionKey,
        string $capability,
        string $timestamp,
    ): CollaborationSession {
        $session = $this->session($context, $sessionKey, $timestamp);
        if (!hash_equals($context->operation, $capability)) {
            throw CollaborationException::denied();
        }

        return $session;
    }

    private function session(
        AuthorizedOperationContext $context,
        string $sessionKey,
        string $timestamp,
        bool $forUpdate = true,
    ): CollaborationSession {
        $session = $this->repository->session(
            $context->tenantContext->tenantId,
            $sessionKey,
            $forUpdate,
        ) ?? throw CollaborationException::notFound();
        $this->assertTarget($context, $session->artifactType, $session->artifactKey);
        if (!$session->isActive() || $session->expiresAt <= $timestamp) {
            throw CollaborationException::notFound();
        }

        return $session;
    }

    private function lease(
        AuthorizedOperationContext $context,
        CollaborationSession $session,
        string $leaseKey,
        string $timestamp,
        bool $forUpdate,
    ): CollaborationParticipantLease {
        $lease = $this->repository->lease(
            $context->tenantContext->tenantId,
            $session->id,
            $leaseKey,
            $forUpdate,
        ) ?? throw CollaborationException::notFound();
        if ($lease->memberId !== $context->tenantContext->memberId
            || $lease->accountId !== $context->tenantContext->accountId) {
            throw CollaborationException::notFound();
        }
        if (!hash_equals($context->operation, $lease->capability)) {
            throw CollaborationException::denied();
        }
        if (!$lease->isActive() || $lease->expiresAt <= $timestamp) {
            throw CollaborationException::leaseExpired();
        }

        return $lease;
    }

    private function policy(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $capability,
        DateTimeImmutable $now,
    ): CollaborationPolicy {
        try {
            $policy = $this->policies->policy($context, $artifactType, $artifactKey, $capability, $now);
        } catch (Throwable) {
            throw CollaborationException::providerUnavailable();
        }
        if ($policy === null) {
            throw CollaborationException::denied();
        }

        return $policy;
    }

    private function submission(
        AuthorizedOperationContext $context,
        CollaborationSession $session,
        \PeanutAdmin\Collaboration\Model\CollaborationSnapshotEnvelope $snapshot,
        DateTimeImmutable $now,
    ): CollaborationSubmission {
        try {
            $submission = $this->submissions->submission(
                $context,
                $session->artifactType,
                $session->artifactKey,
                $session->sessionKey,
                $snapshot->snapshotKey,
                $snapshot->snapshotSha256,
                $session->latestSequence,
                $now,
            );
        } catch (Throwable) {
            throw CollaborationException::providerUnavailable();
        }
        if ($submission === null) {
            throw CollaborationException::denied();
        }

        return $submission;
    }

    private function assertBaseRevision(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $revisionKey,
        string $revisionSha256,
    ): void {
        try {
            $this->revisions->assertBaseRevision(
                $context,
                $artifactType,
                $artifactKey,
                $revisionKey,
                $revisionSha256,
            );
        } catch (CollaborationException|ApiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw CollaborationException::providerUnavailable();
        }
    }

    /** @return array{revision_key: string, revision_sha256: string} */
    private function publishRevision(
        AuthorizedOperationContext $context,
        CollaborationSession $session,
        CollaborationSubmission $submission,
        string $idempotencyKey,
    ): array {
        try {
            $publication = $this->revisions->publish(
                $context,
                $session->artifactType,
                $session->artifactKey,
                $session->baseRevisionKey,
                $submission,
                $idempotencyKey,
            );
        } catch (CollaborationException|ApiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw CollaborationException::providerUnavailable();
        }
        if (array_keys($publication) !== ['revision_key', 'revision_sha256']) {
            throw CollaborationException::integrityFailure();
        }
        if (!is_string($publication['revision_key'])
            || preg_match('/^revision_[0-9a-f]{32}$/D', $publication['revision_key']) !== 1
            || !is_string($publication['revision_sha256'])
            || preg_match('/^[0-9a-f]{64}$/D', $publication['revision_sha256']) !== 1) {
            throw CollaborationException::integrityFailure();
        }

        return $publication;
    }

    private function leaseReceipt(
        string $operation,
        CollaborationSession $session,
        CollaborationParticipantLease $lease,
    ): CollaborationReceipt {
        return new CollaborationReceipt(
            $operation,
            $session->sessionKey,
            $session->status,
            $session->revision,
            leaseKey: $lease->leaseKey,
            leaseCapability: $lease->capability,
            leaseExpiresAt: $lease->expiresAt,
        );
    }

    /** @param array<string, bool|int|string|null> $metadata */
    private function audit(
        AuthorizedOperationContext $context,
        string $eventType,
        CollaborationSession $session,
        array $metadata,
    ): void {
        $this->audit->appendTenantMember(
            $context->tenantContext,
            $eventType,
            $context->resourceKey . '.' . $context->operation,
            $session->artifactType,
            $session->artifactKey,
            targetCount: 1,
            metadata: $metadata,
        );
    }

    private function assertContext(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $capability,
    ): void {
        $this->assertTrustedIdentity($context);
        $this->assertTarget($context, $artifactType, $artifactKey);
        if (!hash_equals($context->operation, $capability)) {
            throw CollaborationException::denied();
        }
    }

    private function assertTrustedIdentity(AuthorizedOperationContext $context): void
    {
        if ($context->tenantContext->tenantId < 1
            || $context->tenantContext->memberId < 1
            || $context->tenantContext->accountId < 1
            || $context->tenantContext->requestId === ''
            || $context->resourceKey === ''
            || $context->operation === ''
            || preg_match('/^[0-9a-f]{64}$/D', $context->authorizationBasisDigest) !== 1) {
            throw CollaborationException::invalid();
        }
    }

    private function assertTarget(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
    ): void {
        $targets = array_values($context->targets);
        $target = $targets[0] ?? null;
        if (!hash_equals($context->resourceKey, $artifactType)
            || count($targets) !== 1
            || $target === null
            || $target->targetRole !== 'primary'
            || !hash_equals($target->targetResourceKey, $artifactType)
            || count($target->targetIds) !== 1
            || !hash_equals($target->targetIds[0], $artifactKey)) {
            throw CollaborationException::notFound();
        }
    }

    private function assertArtifactIdentity(string $artifactType, string $artifactKey): void
    {
        $this->assertIdentifier($artifactType, 64);
        $this->assertAscii($artifactKey, 128);
    }

    private function assertIdentifier(string $value, int $maximumLength): void
    {
        if (strlen($value) > $maximumLength
            || preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $value) !== 1) {
            throw CollaborationException::invalid();
        }
    }

    private function assertAscii(string $value, int $maximumLength): void
    {
        if (strlen($value) > $maximumLength || preg_match('/^[\x21-\x7E]+$/D', $value) !== 1) {
            throw CollaborationException::invalid();
        }
    }

    private function assertKey(string $value, string $prefix): void
    {
        if (preg_match('/^' . $prefix . '_[0-9a-f]{32}$/D', $value) !== 1) {
            throw CollaborationException::invalid();
        }
    }

    private function assertDigest(string $value): void
    {
        if (preg_match('/^[0-9a-f]{64}$/D', $value) !== 1) {
            throw CollaborationException::invalid();
        }
    }

    private function assertNonNegative(int $value): void
    {
        if ($value < 0) {
            throw CollaborationException::invalid();
        }
    }

    private function newKey(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(16));
    }

    private function now(): DateTimeImmutable
    {
        try {
            $now = ($this->clock)();
            if (!$now instanceof DateTimeImmutable) {
                throw CollaborationException::internal();
            }

            return $now->setTimezone(new DateTimeZone('UTC'));
        } catch (CollaborationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw CollaborationException::internal();
        }
    }

    private function databaseTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private function after(DateTimeImmutable $now, int $seconds): string
    {
        return $this->databaseTime($now->modify('+' . $seconds . ' seconds'));
    }

    private function leaseExpiry(
        DateTimeImmutable $now,
        CollaborationPolicy $policy,
        CollaborationSession $session,
    ): string {
        $expiry = $this->after($now, $policy->leaseTtlSeconds);

        return min($expiry, $session->expiresAt);
    }

    /** @param array<string, int|string|null> $value */
    private function canonicalJson(array $value): string
    {
        ksort($value, SORT_STRING);
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw CollaborationException::internal();
        }
    }

    private function mapRepositoryFailure(RuntimeException $exception): CollaborationException
    {
        return match ($exception->getMessage()) {
            'The collaboration session is unavailable.',
            'The collaboration participant lease is unavailable.' => CollaborationException::notFound(),
            'The collaboration session identity is unavailable.',
            'The collaboration participant lease identity is unavailable.',
            'The collaboration session transition is unavailable.',
            'The collaboration completion actor is invalid.',
            'The collaboration completion actor is required.',
            'The collaboration publication result is invalid.',
            'The collaboration session has changed.',
            'The collaboration session is not active.',
            'The collaboration session has not expired.',
            'The collaboration participant lease has changed.',
            'The collaboration update identity is unavailable.',
            'The collaboration snapshot identity is unavailable.',
            'The collaboration snapshot sequence is unavailable.',
            'The collaboration update page is invalid.',
            'The collaboration snapshot sequence is invalid.' => CollaborationException::conflict(),
            default => CollaborationException::internal(),
        };
    }
}
