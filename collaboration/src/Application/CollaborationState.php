<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Application;

use PeanutAdmin\Collaboration\Model\CollaborationSession;
use PeanutAdmin\Collaboration\Model\CollaborationSnapshotEnvelope;
use PeanutAdmin\Collaboration\Model\CollaborationUpdateEnvelope;

final readonly class CollaborationState
{
    /** @param list<CollaborationUpdateEnvelope> $updates */
    public function __construct(
        public CollaborationSession $session,
        public ?CollaborationSnapshotEnvelope $snapshot,
        public array $updates,
        public int $requestedAfterSequence,
        public int $nextAfterSequence,
        public bool $hasMore,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'session' => $this->session->toArray(),
            'snapshot' => $this->snapshot === null ? null : [
                'snapshot_key' => $this->snapshot->snapshotKey,
                'covered_sequence' => $this->snapshot->coveredSequence,
                'engine_name' => $this->snapshot->engineName,
                'engine_version' => $this->snapshot->engineVersion,
                'snapshot_byte_length' => $this->snapshot->snapshotByteLength,
                'snapshot_sha256' => $this->snapshot->snapshotSha256,
                'opaque_snapshot' => $this->snapshot->opaqueSnapshot,
                'state_vector_byte_length' => $this->snapshot->stateVectorByteLength,
                'state_vector_sha256' => $this->snapshot->stateVectorSha256,
                'opaque_state_vector' => $this->snapshot->opaqueStateVector,
                'created_at' => $this->snapshot->createdAt,
            ],
            'updates' => array_map(static fn(CollaborationUpdateEnvelope $update): array => [
                'sequence' => $update->sequenceNo,
                'update_key' => $update->updateKey,
                'client_key' => $update->clientKey,
                'engine_name' => $update->engineName,
                'engine_version' => $update->engineVersion,
                'byte_length' => $update->byteLength,
                'sha256' => $update->updateSha256,
                'opaque_payload' => $update->opaquePayload,
                'occurred_at' => $update->occurredAt,
            ], $this->updates),
            'requested_after_sequence' => $this->requestedAfterSequence,
            'next_after_sequence' => $this->nextAfterSequence,
            'has_more' => $this->hasMore,
        ];
    }
}
