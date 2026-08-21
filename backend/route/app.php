<?php

declare(strict_types=1);

use PeanutAdmin\App\controller\api\platform\v1\HealthController as PlatformHealthController;
use PeanutAdmin\App\controller\api\v1\HealthController as TenantHealthController;
use PeanutAdmin\App\middleware\IdempotencyMiddleware;
use PeanutAdmin\App\middleware\ModuleGuard;
use PeanutAdmin\App\middleware\PermissionGuard;
use PeanutAdmin\App\middleware\PlatformGuard;
use PeanutAdmin\App\middleware\TenantGuard;
use think\facade\Route;

$routes = require __DIR__ . '/openapi-generated.php';

Route::get('api/v1/health$', [TenantHealthController::class, 'show'])->name('tenantHealth');
Route::get('api/platform/v1/health$', [PlatformHealthController::class, 'show'])->name('platformHealth');

foreach ($routes as $route => $binding) {
    [$method, $path] = explode(' ', $route, 2);
    [$class, $classMethod, $permission, $operationId, $audience, $requiresAuth, $idempotent, $moduleKey] = $binding;
    $rule = Route::rule(ltrim($path, '/') . '$', [$class, $classMethod], $method)
        ->name($operationId)
        ->append(['operation_id' => $operationId, 'audience' => $audience]);

    if ($requiresAuth) {
        $rule->middleware($audience === 'tenant' ? TenantGuard::class : PlatformGuard::class);
    }
    if ($moduleKey !== null) {
        if (!$requiresAuth || $audience !== 'tenant') {
            throw new \LogicException("Module route must use an authenticated tenant context: {$route}");
        }
        $rule->middleware(ModuleGuard::class, $moduleKey);
    }
    if ($permission !== null) {
        if (!$requiresAuth) {
            throw new \LogicException("Protected permission route cannot be public: {$route}");
        }
        $rule->middleware(PermissionGuard::class, $permission, $audience);
    }
    if ($idempotent) {
        if (!$requiresAuth) {
            throw new \LogicException("Idempotent operation cannot be public: {$route}");
        }
        $rule->middleware(IdempotencyMiddleware::class, $operationId, $audience);
    }
}

return $routes;
