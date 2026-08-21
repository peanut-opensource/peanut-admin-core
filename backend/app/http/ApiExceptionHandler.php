<?php

declare(strict_types=1);

namespace PeanutAdmin\App\http;

use PeanutAdmin\App\middleware\RequestIdMiddleware;
use PeanutAdmin\DataPermission\Exception\DataAuthorizationException;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Api\ProblemDetails;
use PeanutAdmin\Kernel\Api\RequestId;
use PeanutAdmin\Kernel\Auth\AuthException;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\AuthorizationException;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\exception\Handle;
use think\exception\HttpException;
use think\Request;
use think\Response;
use Throwable;

final class ApiExceptionHandler extends Handle
{
    public function render(Request $request, Throwable $exception): Response
    {
        $requestId = RequestIdMiddleware::current($request);
        $apiException = self::normalize($exception);
        $problem = ProblemDetails::fromException(
            $apiException,
            RequestId::fromHeader($requestId),
        );

        return Response::create($problem->toArray(), 'json', $problem->status)->header([
            'Content-Type' => $problem->contentType(),
            'X-Request-Id' => $requestId,
            'Cache-Control' => 'no-store',
        ]);
    }

    private static function normalize(Throwable $exception): ApiException
    {
        return match (true) {
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
                in_array($exception->errorCode, ['MODULE_INSTALLATION_FAILED', 'MODULE_NOT_INSTALLED'], true)
                    ? 503
                    : 403,
                $exception->getMessage(),
            ),
            $exception instanceof HttpException => self::httpException($exception),
            default => new ApiException('INTERNAL_ERROR', 500, 'The request could not be completed.'),
        };
    }

    private static function httpException(HttpException $exception): ApiException
    {
        $status = $exception->getStatusCode();

        return new ApiException(
            match ($status) {
                404 => 'ROUTE_NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                default => 'HTTP_REQUEST_REJECTED',
            },
            $status,
            match ($status) {
                404 => 'The requested route does not exist.',
                405 => 'The request method is not allowed for this route.',
                default => 'The HTTP request was rejected.',
            },
        );
    }
}
