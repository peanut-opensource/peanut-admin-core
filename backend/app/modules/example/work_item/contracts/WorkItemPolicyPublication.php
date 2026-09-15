<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\work_item\contracts;

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
