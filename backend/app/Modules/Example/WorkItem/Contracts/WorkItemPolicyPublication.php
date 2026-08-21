<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Contracts;

use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Auth\TenantContext;

interface WorkItemPolicyPublication
{
    /** @param array<string, mixed> $config */
    public function publish(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
        string $name,
        array $config,
    ): string;
}
