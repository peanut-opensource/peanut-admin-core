<?php

declare(strict_types=1);

use PeanutAdmin\InternalStarter\StarterExceptionHandler;
use think\exception\Handle;

return [
    Handle::class => StarterExceptionHandler::class,
];
