<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use PeanutAdmin\Kernel\Auth\AuthException;
use think\Request;
use think\Response;

final class TenantGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('authorization');
        if (!is_string($authorization) || !str_starts_with($authorization, 'Bearer ')) {
            throw new AuthException('AUTH_TOKEN_INVALID', 401);
        }

        $context = TenantAuthRuntimeFactory::create()->context(
            substr($authorization, 7),
            RequestIdMiddleware::current($request),
        );
        $route = $request->route();
        $request->withRoute([
            ...(is_array($route) ? $route : []),
            'tenant_context' => $context,
        ]);

        return $next($request);
    }
}
