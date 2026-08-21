<?php

declare(strict_types=1);

namespace ExampleHost\App\Modules\Example\Greeting;

use PeanutAdmin\Kernel\Module\ModuleKey;
use PeanutAdmin\Kernel\Module\ModuleProvider;

final class ExampleGreetingModuleProvider implements ModuleProvider
{
    public function moduleKey(): string
    {
        return ModuleKey::fromString('example.greeting')->value();
    }
}
