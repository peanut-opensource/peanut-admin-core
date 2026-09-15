<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use DateTimeImmutable;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Idempotency\CanonicalRequestHasher;
use PeanutAdmin\Kernel\Idempotency\IdempotencyKey;
use PeanutAdmin\Kernel\Idempotency\IdempotencyRecord;
use PeanutAdmin\Kernel\Idempotency\IdempotencyService;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use think\facade\Db;
use think\Request;
use think\Response;

final class IdempotencyMiddleware
{
    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function handle(
        Request $request,
        Closure $next,
        string $operationId,
        string $audience = 'tenant',
    ): Response {
        $header = $request->header('idempotency-key');
        $key = IdempotencyKey::fromString(is_string($header) ? $header : null);
        $body = $request->post();
        $requestHash = (new CanonicalRequestHasher())->hash(
            $request->method(),
            $request->url(),
            is_array($body) ? $body : [],
        );
        $route = $request->route();
        $routeValues = is_array($route) ? $route : [];
        $expires = new DateTimeImmutable('+24 hours');
        return Db::transaction(function () use (
            $audience,
            $routeValues,
            $operationId,
            $key,
            $requestHash,
            $expires,
            $next,
            $request,
        ): Response {
            $scope = $audience === 'tenant'
                ? $this->tenantScope($routeValues['tenant_context'] ?? null)
                : null;
            $record = $scope instanceof TenantScope
                ? $this->idempotency->beginTenant(
                    $scope,
                    $routeValues['tenant_context']->memberId,
                    $operationId,
                    $key,
                    $requestHash,
                    $expires,
                )
                : $this->beginPlatform($routeValues['platform_context'] ?? null, $operationId, $key, $requestHash, $expires);
            if (!$record->acquiredForExecution()) {
                $responseStatus = $record->responseStatus;
                $responseBody = $record->responseBody;
                if ($record->replayable() && $responseStatus !== null && $responseBody !== null) {
                    return Response::create($responseBody, 'json', $responseStatus)->header(['X-Idempotent-Replay' => 'true']);
                }
                if ($record->status !== 'processing') {
                    throw new ApiException('IDEMPOTENCY_STATE_CONFLICT', 409, 'Idempotency record has no replayable outcome.');
                }
                throw new ApiException('IDEMPOTENCY_REQUEST_PROCESSING', 409, 'The original request is still processing.');
            }
            $response = $next($request);
            $responseBody = $response->getData();
            if ($response->getCode() >= 200 && $response->getCode() < 300 && is_array($responseBody)) {
                if ($audience === 'tenant') {
                    if (!$scope instanceof TenantScope) {
                        throw new ApiException('CONTEXT_TENANT_REQUIRED', 403, 'A tenant context is required.');
                    }
                    $this->idempotency->completeTenant($scope, $record->id, $response->getCode(), $responseBody);
                } else {
                    $this->idempotency->completePlatform($record->id, $response->getCode(), $responseBody);
                }
            }

            return $response;
        });
    }

    private function tenantScope(mixed $context): TenantScope
    {
        if (!$context instanceof TenantContext) {
            throw new ApiException('CONTEXT_TENANT_REQUIRED', 403, 'A tenant context is required.');
        }

        return TenantScope::fromTrustedContext($context->tenantId, $context->requestId);
    }

    private function beginPlatform(
        mixed $context,
        string $operationId,
        IdempotencyKey $key,
        string $requestHash,
        DateTimeImmutable $expires,
    ): IdempotencyRecord {
        if (!$context instanceof PlatformContext) {
            throw new ApiException('CONTEXT_PLATFORM_REQUIRED', 403, 'A platform context is required.');
        }

        return $this->idempotency->beginPlatform($context->operatorId, $operationId, $key, $requestHash, $expires);
    }
}
