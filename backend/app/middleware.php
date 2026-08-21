<?php

declare(strict_types=1);

use PeanutAdmin\App\middleware\ProblemDetailsMiddleware;
use PeanutAdmin\App\middleware\RequestIdMiddleware;
use PeanutAdmin\App\middleware\SecurityHeadersMiddleware;

return [
    RequestIdMiddleware::class,
    SecurityHeadersMiddleware::class,
    ProblemDetailsMiddleware::class,
];
