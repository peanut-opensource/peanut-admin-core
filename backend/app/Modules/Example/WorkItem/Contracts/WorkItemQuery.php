<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Contracts;

use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Auth\TenantContext;

interface WorkItemQuery
{
    public function list(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
        int $page = 1,
        int $pageSize = 20,
        ?string $status = null,
        string $sort = '-created_at',
    ): WorkItemPage;

    public function get(TenantContext $context, string $workItemId): WorkItemView;

    /** @return array{total: int, by_status: array<string, int>} */
    public function aggregate(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
    ): array;
}
