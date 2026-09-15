<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\reference\contracts;

use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Auth\TenantContext;

interface ReferenceQuery
{
    /** @return list<ReferenceOption> */
    public function candidates(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
        string $capability,
        string $search = '',
    ): array;
}
