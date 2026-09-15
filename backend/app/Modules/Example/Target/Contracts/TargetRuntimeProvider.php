<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Contracts;

interface TargetRuntimeProvider
{
    public function targetQuery(): TargetQuery;
}
