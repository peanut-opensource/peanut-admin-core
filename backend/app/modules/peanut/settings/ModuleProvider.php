<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\peanut\settings;

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
