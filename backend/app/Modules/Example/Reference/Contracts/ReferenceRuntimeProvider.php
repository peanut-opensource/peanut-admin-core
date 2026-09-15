<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Reference\Contracts;

use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;

interface ReferenceRuntimeProvider
{
    public function referenceQuery(DataPermissionEngine $authorization): ReferenceQuery;
}
