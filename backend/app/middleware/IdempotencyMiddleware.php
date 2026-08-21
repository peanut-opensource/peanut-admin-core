<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use DateTimeImmutable;
use PDO;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Idempotency\CanonicalRequestHasher;
use PeanutAdmin\Kernel\Idempotency\IdempotencyKey;
use PeanutAdmin\Kernel\Idempotency\IdempotencyRecord;
use PeanutAdmin\Kernel\Idempotency\PdoIdempotencyRepository;
use think\Request;
use think\Response;

final class IdempotencyMiddleware
{
    public function __construct(private readonly PDO $pdo) {}

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
        $repository = new PdoIdempotencyRepository($this->pdo);
        $expires = new DateTimeImmutable('+24 hours');
        $record = $audience === 'tenant'
            ? $this->beginTenant($repository, $routeValues['tenant_context'] ?? null, $operationId, $key, $requestHash, $expires)
            : $this->beginPlatform($repository, $routeValues['platform_context'] ?? null, $operationId, $key, $requestHash, $expires);
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
            $audience === 'tenant'
                ? $repository->completeTenant($record->id, $response->getCode(), $responseBody)
                : $repository->completePlatform($record->id, $response->getCode(), $responseBody);
        }

        return $response;
    }

    private function beginTenant(
        PdoIdempotencyRepository $repository,
        mixed $context,
        string $operationId,
        IdempotencyKey $key,
        string $requestHash,
        DateTimeImmutable $expires,
    ): IdempotencyRecord {
        if (!$context instanceof TenantContext) {
            throw new ApiException('CONTEXT_TENANT_REQUIRED', 403, 'A tenant context is required.');
        }

        return $repository->beginTenant($context->tenantId, $context->memberId, $operationId, $key, $requestHash, $expires);
    }

    private function beginPlatform(
        PdoIdempotencyRepository $repository,
        mixed $context,
        string $operationId,
        IdempotencyKey $key,
        string $requestHash,
        DateTimeImmutable $expires,
    ): IdempotencyRecord {
        if (!$context instanceof PlatformContext) {
            throw new ApiException('CONTEXT_PLATFORM_REQUIRED', 403, 'A platform context is required.');
        }

        return $repository->beginPlatform($context->operatorId, $operationId, $key, $requestHash, $expires);
    }
}
