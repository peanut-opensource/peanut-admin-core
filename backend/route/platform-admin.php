<?php

declare(strict_types=1);

use PeanutAdmin\App\controller\api\platform\v1\TenantOwnerController;

return [
    'POST /api/platform/v1/tenants/{tenant_id}/owner-candidates' => [
        TenantOwnerController::class,
        'create',
        'platform.tenant.provision-owner',
    ],
    'POST /api/platform/v1/tenants/{tenant_id}/owner-candidates/{member_id}/activate' => [
        TenantOwnerController::class,
        'activate',
        'platform.tenant.provision-owner',
    ],
];
