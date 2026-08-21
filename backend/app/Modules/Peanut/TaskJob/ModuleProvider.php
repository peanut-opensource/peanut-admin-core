<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Peanut\TaskJob;

use PeanutAdmin\App\module\TenantWideModuleProvider;

final class ModuleProvider extends TenantWideModuleProvider
{
    public function moduleKey(): string
    {
        return 'peanut.task-job';
    }

    protected function tenantColumn(): string
    {
        return 'task.tenant_id';
    }
}
