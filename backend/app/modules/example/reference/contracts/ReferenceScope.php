<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\reference\contracts;

use PeanutAdmin\DataPermission\Context\authorizationContext;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;

interface ReferenceScope
{
    public function canUse(
        AuthorizationContext $context,
        string $referenceItemId,
        TypedResourceTargetCollection $targets,
    ): bool;
}
