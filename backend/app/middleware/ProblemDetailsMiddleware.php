<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use PeanutAdmin\DataPermission\Exception\DataAuthorizationException;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Api\ProblemDetails;
use PeanutAdmin\Kernel\Api\RequestId;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\Request;
use think\Response;
use Throwable;

final class ProblemDetailsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $exception) {
            $route = $request->route();
            $candidate = is_array($route) ? ($route['request_id'] ?? null) : null;
            $requestId = $candidate instanceof RequestId ? $candidate : RequestId::fromHeader(null);
            $apiException = match (true) {
                $exception instanceof ApiException => $exception,
                $exception instanceof AdminAccessException => new ApiException(
                    $exception->errorCode,
                    $exception->httpStatus,
                    $exception->getMessage(),
                ),
                $exception instanceof AuthException => new ApiException(
                    $exception->errorCode,
                    $exception->httpStatus,
                    $exception->getMessage(),
                ),
                $exception instanceof AuthorizationException => new ApiException(
                    $exception->errorCode,
                    403,
                    'The requested operation is not permitted.',
                ),
                $exception instanceof DataAuthorizationException => new ApiException(
                    'AUTHZ_DATA_DENIED',
                    404,
                    'The requested resource does not exist or is not accessible.',
                ),
                $exception instanceof ModuleException => new ApiException(
                    $exception->errorCode,
                    in_array($exception->errorCode, ['MODULE_INSTALLATION_FAILED', 'MODULE_NOT_INSTALLED'], true) ? 503 : 403,
                    $exception->getMessage(),
                ),
                default => (static function () use ($exception): ApiException {
                    error_log(sprintf(
                        '[Peanut] Unhandled exception: %s %s in %s:%d',
                        get_class($exception),
                        $exception->getMessage(),
                        $exception->getFile(),
                        $exception->getLine(),
                    ));
                    return new ApiException('INTERNAL_ERROR', 500, 'The request could not be completed.');
                })(),
            };
            $problem = ProblemDetails::fromException($apiException, $requestId);

            return Response::create($problem->toArray(), 'json', $problem->status)->header([
                'Content-Type' => $problem->contentType(),
                'X-Request-Id' => $requestId->value,
                'Cache-Control' => 'no-store',
            ]);
        }
    }
}
