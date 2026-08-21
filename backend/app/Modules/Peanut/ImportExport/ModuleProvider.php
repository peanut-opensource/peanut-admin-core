<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Peanut\ImportExport;

use PeanutAdmin\App\module\TenantWideModuleProvider;

final class ModuleProvider extends TenantWideModuleProvider
{
    public function moduleKey(): string
    {
        return 'peanut.import-export';
    }

    protected function tenantColumn(): string
    {
        return 'operation.tenant_id';
    }
}
