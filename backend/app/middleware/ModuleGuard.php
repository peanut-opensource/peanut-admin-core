<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;
use PeanutAdmin\Kernel\Module\ModuleAvailabilityService;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use think\Request;
use think\Response;

final class ModuleGuard
{
    public function __construct(private readonly ModuleAvailabilityService $modules) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $route = $request->route();
        $routeValues = is_array($route) ? $route : [];
        $context = $routeValues['tenant_context'] ?? null;
        if (!$context instanceof TenantContext) {
            throw new AuthorizationException('CONTEXT_TENANT_REQUIRED');
        }

        $this->modules->assertAvailable(
            TenantScope::fromTrustedContext($context->tenantId, $context->requestId),
            $moduleKey,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );

        return $next($request);
    }
}
