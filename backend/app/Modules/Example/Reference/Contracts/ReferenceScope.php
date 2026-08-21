<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Reference\Contracts;

use PeanutAdmin\DataPermission\Context\AuthorizationContext;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;

interface ReferenceScope
{
    public function canUse(
        AuthorizationContext $context,
        string $referenceItemId,
        TypedResourceTargetCollection $targets,
    ): bool;
}
