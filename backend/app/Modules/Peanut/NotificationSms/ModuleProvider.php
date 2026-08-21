<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Peanut\NotificationSms;

use PeanutAdmin\App\module\TenantWideModuleProvider;

final class ModuleProvider extends TenantWideModuleProvider
{
    public function moduleKey(): string
    {
        return 'peanut.notification-sms';
    }

    protected function tenantColumn(): string
    {
        return 'notification.tenant_id';
    }
}
