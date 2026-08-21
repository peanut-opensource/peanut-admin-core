<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Contracts;

use PDO;

interface TargetRuntimeProvider
{
    public function targetQuery(PDO $pdo): TargetQuery;
}
