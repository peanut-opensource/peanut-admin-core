<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use PDO;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Authorization\PermissionRequirement;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationEvaluator;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Http\PermissionMiddleware;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use think\Request;
use think\Response;

final class PermissionGuard
{
    public function __construct(private readonly PDO $pdo) {}

    public function handle(
        Request $request,
        Closure $next,
        string $permissionKey,
        string $audience = 'tenant',
    ): Response {
        $route = $request->route();
        $routeValues = is_array($route) ? $route : [];
        $requirement = new PermissionRequirement($audience, [$permissionKey]);
        $cache = new RevisionPermissionCache();
        $middleware = new PermissionMiddleware(
            new TenantAuthorizationEvaluator(new PdoTenantAuthorizationRepository($this->pdo), $cache),
            new PlatformAuthorizationEvaluator(new PdoPlatformAuthorizationRepository($this->pdo), $cache),
        );

        if ($audience === 'tenant') {
            $context = $routeValues['tenant_context'] ?? null;
            if (!$context instanceof TenantContext) {
                throw new AuthorizationException('CONTEXT_TENANT_REQUIRED');
            }
            $middleware->authorizeTenant($context, $requirement);
        } else {
            $context = $routeValues['platform_context'] ?? null;
            if (!$context instanceof PlatformContext) {
                throw new AuthorizationException('CONTEXT_PLATFORM_REQUIRED');
            }
            $middleware->authorizePlatform($context, $requirement);
        }

        return $next($request);
    }
}
