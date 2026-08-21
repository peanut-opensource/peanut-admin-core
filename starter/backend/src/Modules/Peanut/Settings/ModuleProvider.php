<?php

declare(strict_types=1);

namespace ExampleHost\App\Modules\Peanut\Settings;

use PeanutAdmin\Kernel\Module\ModuleKey;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract
{
    public function moduleKey(): string
    {
        return ModuleKey::fromString('peanut.settings')->value();
    }
}
