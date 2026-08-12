<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\ArtifactRevision;

use PDO;
use PeanutAdmin\ArtifactRevision\Application\ArtifactRevisionException;
use PeanutAdmin\ArtifactRevision\Application\ArtifactRevisionService;
use PeanutAdmin\ArtifactRevision\Persistence\ArtifactRevisionRepository;
use PeanutAdmin\Collaboration\Application\CollaborationException;
use PeanutAdmin\Collaboration\Contract\CollaborationRevisionPublisher;
use PeanutAdmin\Collaboration\Contract\CollaborationSubmission;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use Throwable;
use UnexpectedValueException;

final readonly class ArtifactRevisionCollaborationPublisher implements CollaborationRevisionPublisher
{
    private ArtifactRevisionService $service;

    public function __construct(private ArtifactRevisionRepository $repository)
    {
        $this->service = new ArtifactRevisionService($repository);
    }

    public function connection(): PDO
    {
        return $this->repository->connection();
    }

    public function assertBaseRevision(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $revisionKey,
        string $canonicalEnvelopeSha256,
    ): void {
        try {
            $artifact = $this->repository->artifact(
                $context->tenantContext->tenantId,
                $artifactType,
                $artifactKey,
                true,
            ) ?? throw CollaborationException::notFound();
            $revision = $this->service->revision($context, $artifactType, $artifactKey, $revisionKey);
            $revision->assertEnvelopeIntegrity();
            if ($artifact->latestFinalizedRevisionId !== $revision->id) {
                throw CollaborationException::conflict();
            }
            if (!$revision->isFinalized()
                || $revision->canonicalEnvelopeSha256 === null
                || !hash_equals($canonicalEnvelopeSha256, $revision->canonicalEnvelopeSha256)) {
                throw CollaborationException::notFound();
            }
        } catch (CollaborationException $exception) {
            throw $exception;
        } catch (ArtifactRevisionException $exception) {
            throw $this->map($exception);
        } catch (UnexpectedValueException) {
            throw CollaborationException::integrityFailure();
        } catch (Throwable) {
            throw CollaborationException::internal();
        }
    }

    public function publish(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $parentRevisionKey,
        CollaborationSubmission $submission,
        string $idempotencyKey,
    ): array {
        $digest = hash('sha256', $idempotencyKey);
        try {
            $artifact = $this->repository->artifact(
                $context->tenantContext->tenantId,
                $artifactType,
                $artifactKey,
                true,
            ) ?? throw CollaborationException::notFound();
            $parent = $this->repository->revision(
                $context->tenantContext->tenantId,
                $artifactType,
                $artifactKey,
                $parentRevisionKey,
                true,
            ) ?? throw CollaborationException::notFound();
            if (!$parent->isFinalized() || $artifact->latestFinalizedRevisionId !== $parent->id) {
                throw CollaborationException::conflict();
            }
            $created = $this->service->createRevision(
                $context,
                $artifactType,
                $artifactKey,
                $parentRevisionKey,
                $artifact->revision,
                'collaboration-create-' . $digest,
            );
            $finalized = $this->service->finalizeRevision(
                $context,
                $artifactType,
                $artifactKey,
                $created->revisionKey,
                $created->artifactRevision,
                $created->revision,
                $submission->payloadSchemaKey,
                $submission->payloadSchemaVersion,
                $submission->payloadRef,
                $submission->payloadSha256,
                $submission->attachmentManifestSha256,
                'collaboration-finalize-' . $digest,
            );
            if ($finalized->canonicalEnvelopeSha256 === null) {
                throw CollaborationException::integrityFailure();
            }

            return [
                'revision_key' => $finalized->revisionKey,
                'revision_sha256' => $finalized->canonicalEnvelopeSha256,
            ];
        } catch (CollaborationException|ApiException $exception) {
            throw $exception;
        } catch (ArtifactRevisionException $exception) {
            throw $this->map($exception);
        } catch (UnexpectedValueException) {
            throw CollaborationException::integrityFailure();
        } catch (Throwable) {
            throw CollaborationException::internal();
        }
    }

    private function map(ArtifactRevisionException $exception): CollaborationException
    {
        return match ($exception->errorCode) {
            'ARTIFACT_REVISION_INVALID' => CollaborationException::invalid(),
            'ARTIFACT_REVISION_NOT_FOUND' => CollaborationException::notFound(),
            'ARTIFACT_REVISION_CONFLICT' => CollaborationException::conflict(),
            'ARTIFACT_REVISION_INTEGRITY_FAILURE' => CollaborationException::integrityFailure(),
            default => CollaborationException::internal(),
        };
    }
}
