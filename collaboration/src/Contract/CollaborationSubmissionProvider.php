<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Contract;

use DateTimeImmutable;
use PDO;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

interface CollaborationSubmissionProvider
{
    public function connection(): PDO;

    /**
     * Validate Host-owned content and return only the immutable envelope
     * fields. Payload bytes must not cross this boundary.
     */
    public function submission(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $sessionKey,
        string $snapshotKey,
        string $snapshotSha256,
        int $latestSequence,
        DateTimeImmutable $evaluatedAt,
    ): ?CollaborationSubmission;
}
