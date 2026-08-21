<?php

declare(strict_types=1);

use PeanutAdmin\App\http\ApiExceptionHandler;
use think\exception\Handle;

return [
    Handle::class => ApiExceptionHandler::class,
];
