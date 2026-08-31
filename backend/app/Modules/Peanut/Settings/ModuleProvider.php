<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Peanut\Settings;

use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return 'peanut.settings';
    }

    public function bindings(): array
    {
        return [];
    }
}
