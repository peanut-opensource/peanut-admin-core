<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration\Contract;

use DateTimeImmutable;
use PDO;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;

interface CollaborationPolicyProvider
{
    public function connection(): PDO;

    /**
     * Return null to deny the exact Tenant, target and capability. The provider
     * must revalidate membership and target access on every invocation.
     */
    public function policy(
        AuthorizedOperationContext $context,
        string $artifactType,
        string $artifactKey,
        string $capability,
        DateTimeImmutable $evaluatedAt,
    ): ?CollaborationPolicy;
}
