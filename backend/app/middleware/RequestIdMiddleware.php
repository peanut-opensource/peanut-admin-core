<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use PeanutAdmin\Kernel\Api\RequestId;
use think\Request;
use think\Response;

final class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('x-request-id');
        $requestId = RequestId::fromHeader(is_string($header) ? $header : null);
        $route = $request->route();
        $request->withRoute([...(is_array($route) ? $route : []), 'request_id' => $requestId]);

        return $next($request)->header(['X-Request-Id' => $requestId->value]);
    }

    public static function current(Request $request): string
    {
        $route = $request->route();
        $requestId = is_array($route) ? ($route['request_id'] ?? null) : null;
        if ($requestId instanceof RequestId) {
            return $requestId->value;
        }

        $header = $request->header('x-request-id');

        return RequestId::fromHeader(is_string($header) ? $header : null)->value;
    }
}
