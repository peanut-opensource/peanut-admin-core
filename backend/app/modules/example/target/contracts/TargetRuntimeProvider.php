<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\target\contracts;

interface TargetRuntimeProvider
{
    public function targetQuery(): TargetQuery;
}
