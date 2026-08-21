<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;
use PeanutAdmin\Kernel\Module\ModuleGuard as KernelModuleGuard;
use PeanutAdmin\Kernel\Module\ModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use think\Request;
use think\Response;

final class ModuleGuard
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?ModuleRuntimeRepository $repository = null,
    ) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $route = $request->route();
        $routeValues = is_array($route) ? $route : [];
        $context = $routeValues['tenant_context'] ?? null;
        if (!$context instanceof TenantContext) {
            throw new AuthorizationException('CONTEXT_TENANT_REQUIRED');
        }

        $guard = new KernelModuleGuard($this->repository ?? new PdoModuleRuntimeRepository($this->pdo));
        $guard->assertDeployment($moduleKey);
        $guard->assertTenant($context->tenantId, $moduleKey, new DateTimeImmutable('now', new DateTimeZone('UTC')));

        return $next($request);
    }
}
