<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Contract;

use PDO;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

interface CollaborationRevisionPublisher
{
    public function connection(): PDO;

    public function assertBaseRevision(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $revisionKey,
        string $canonicalEnvelopeSha256,
    ): void;

    /** @return array{revision_key: string, revision_sha256: string} */
    public function publish(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $parentRevisionKey,
        CollaborationSubmission $submission,
        string $idempotencyKey,
    ): array;
}
