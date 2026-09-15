<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\peanut\integration_security;

use PeanutAdmin\App\module\TenantWideModuleProvider;

final class ModuleProvider extends TenantWideModuleProvider
{
    public function moduleKey(): string
    {
        return 'peanut.integration-security';
    }

    protected function tenantColumn(): string
    {
        return 'identity.tenant_id';
    }
}
