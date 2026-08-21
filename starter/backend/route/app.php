<?php

declare(strict_types=1);

use PeanutAdmin\DataPermission\Package as DataPermissionPackage;
use ExampleHost\App\Modules\Example\Greeting\ExampleGreetingModuleProvider;
use PeanutAdmin\Kernel\Package as KernelPackage;
use think\facade\Route;

$module = new ExampleGreetingModuleProvider();

Route::get('health$', static fn() => json([
    'status' => 'ok',
    'packages' => [
        'kernel' => KernelPackage::VERSION,
        'data_permission' => DataPermissionPackage::VERSION,
    ],
]));

Route::get('api/example/greeting$', static fn() => json([
    'module_key' => $module->moduleKey(),
    'message' => 'Hello from the fictional module.',
]));
