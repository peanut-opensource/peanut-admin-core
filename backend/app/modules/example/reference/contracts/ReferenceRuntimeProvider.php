<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\reference\contracts;

use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;

interface ReferenceRuntimeProvider
{
    public function referenceQuery(DataPermissionEngine $authorization): ReferenceQuery;
}
