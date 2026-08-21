<?php

declare(strict_types=1);

use PeanutAdmin\Kernel\Auth\PlatformAuthService;

return [
    'POST /api/platform/v1/auth/login' => [PlatformAuthService::class, 'login'],
    'POST /api/platform/v1/auth/refresh' => [PlatformAuthService::class, 'refresh'],
    'GET /api/platform/v1/auth/context' => [PlatformAuthService::class, 'context'],
    'POST /api/platform/v1/auth/logout' => [PlatformAuthService::class, 'logout'],
];
