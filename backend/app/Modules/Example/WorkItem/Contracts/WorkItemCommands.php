<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Contracts;

use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Auth\TenantContext;

interface WorkItemCommands
{
    public function create(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
        CreateWorkItem $command,
    ): string;

    /** @return array{id: string, revision: int} */
    public function update(
        TenantContext $context,
        string $workItemId,
        int $expectedRevision,
        TypedResourceTargetCollection $targets,
        ?string $title,
        ?string $status,
    ): array;

    public function bulkWrite(): never;
}
