<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Reference\Contracts;

use PDO;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;

interface ReferenceRuntimeProvider
{
    public function referenceQuery(PDO $pdo, DataPermissionEngine $authorization): ReferenceQuery;
}
