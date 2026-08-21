<?php

declare(strict_types=1);

use PeanutAdmin\Kernel\Http\TenantAuthEndpoint;

return [
    'POST /api/v1/auth/login' => [TenantAuthEndpoint::class, 'login'],
    'POST /api/v1/auth/tenants/select' => [TenantAuthEndpoint::class, 'selectTenant'],
    'POST /api/v1/auth/refresh' => [TenantAuthEndpoint::class, 'refresh'],
    'GET /api/v1/auth/context' => [TenantAuthEndpoint::class, 'context'],
    'POST /api/v1/auth/tenant-switch/challenge' => [TenantAuthEndpoint::class, 'switchChallenge'],
    'POST /api/v1/auth/logout' => [TenantAuthEndpoint::class, 'logout'],
    'POST /api/v1/auth/logout-all' => [TenantAuthEndpoint::class, 'logoutAll'],
];
