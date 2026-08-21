<?php

declare(strict_types=1);

namespace PeanutAdmin\App\http;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDO;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\DataPermission\Constraint\QueryConstraint;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Api\RequestId;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Authorization\DataPermissionAdapter;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Authorization\PermissionRequirement;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationEvaluator;
use PeanutAdmin\Kernel\Context\AuthorizationDecision;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Host\AtomicOperationAdapter;
use PeanutAdmin\Kernel\Host\AuthorizedExternalOperation;
use PeanutAdmin\Kernel\Host\ExternalHostConfiguration;
use PeanutAdmin\Kernel\Host\ExternalOperationDefinition;
use PeanutAdmin\Kernel\Host\ExternalOperationHost;
use PeanutAdmin\Kernel\Host\ExternalOperationRequest;
use PeanutAdmin\Kernel\Host\ExternalOperationResponse;
use PeanutAdmin\Kernel\Host\ModuleAvailabilityAdapter;
use PeanutAdmin\Kernel\Host\PermissionAdapter;
use PeanutAdmin\Kernel\Host\ProblemDetailsAdapter;
use PeanutAdmin\Kernel\Host\TrustedContextAdapter;
use PeanutAdmin\Kernel\Host\TypedTargetAdapter;
use PeanutAdmin\Kernel\Http\PermissionMiddleware;
use PeanutAdmin\Kernel\Module\CompiledModuleRegistry;
use PeanutAdmin\Kernel\Module\ModuleGuard;
use PeanutAdmin\Kernel\Module\ModuleHostLayout;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use think\Request;
use think\Response;

final class TenantModuleRuntime
{
    public static function operation(
        string $operationId,
        string $method,
        string $path,
        string $moduleKey,
        string $permission,
        bool $command = false,
        bool $idempotencyRequired = false,
    ): ExternalOperationDefinition {
        return new ExternalOperationDefinition(
            $operationId,
            $method,
            $path,
            'tenant',
            $moduleKey,
            new PermissionRequirement('tenant', [$permission]),
            atomicCommand: $command,
            idempotencyRequired: $idempotencyRequired,
        );
    }

    public static function request(Request $request, ExternalOperationDefinition $operation, string $path): ExternalOperationRequest
    {
        $route = $request->route();
        $context = is_array($route) ? ($route['tenant_context'] ?? null) : null;
        $requestId = $context instanceof TenantContext ? $context->requestId : MemberAdminRuntime::requestId($request);
        $now = self::millisecond(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        return new ExternalOperationRequest(
            RequestId::fromHeader($requestId),
            $context,
            $operation->method,
            $path,
            [
                'payload' => MemberAdminRuntime::body($request),
                'query' => is_array($request->get()) ? $request->get() : [],
                'if_match' => MemberAdminRuntime::header($request, 'if-match'),
            ],
            [],
            ($key = MemberAdminRuntime::header($request, 'idempotency-key')) === '' ? null : $key,
            $now,
            $now->modify('+24 hours'),
        );
    }

    public static function host(PDO $pdo, CompiledModuleRegistry $modules): ExternalOperationHost
    {
        $configuration = self::configuration();
        $permissions = new PermissionMiddleware(
            new TenantAuthorizationEvaluator(new PdoTenantAuthorizationRepository($pdo), new RevisionPermissionCache()),
            new PlatformAuthorizationEvaluator(new PdoPlatformAuthorizationRepository($pdo), new RevisionPermissionCache()),
        );
        $noTargets = new DataPermissionAdapter(
            static function (): QueryConstraint {
                throw new LogicException('This operation does not accept query authorization.');
            },
            static function (): never {
                throw new LogicException('This operation does not accept typed targets.');
            },
        );

        return new ExternalOperationHost(
            $configuration,
            new TrustedContextAdapter($configuration),
            new ModuleAvailabilityAdapter($modules, new ModuleGuard(new PdoModuleRuntimeRepository($pdo))),
            new PermissionAdapter($permissions),
            new TypedTargetAdapter($noTargets),
            new AtomicOperationAdapter($pdo),
            new ProblemDetailsAdapter(),
        );
    }

    public static function context(AuthorizedExternalOperation $authorized): TenantContext
    {
        if (!$authorized->context instanceof TenantContext) {
            throw new ApiException('RESOURCE_NOT_FOUND', 404, 'The requested resource was not found.');
        }
        return $authorized->context;
    }

    public static function authorizedContext(
        AuthorizedExternalOperation $authorized,
        string $resourceKey,
        string $operation,
    ): AuthorizedOperationContext {
        $context = self::context($authorized);
        return AuthorizedOperationContext::fromDecision(AuthorizationDecision::allow(
            $context,
            $resourceKey,
            $operation,
            [],
            hash('sha256', $authorized->operation->operationId . "\0" . $context->authorizationRevision),
        ));
    }

    public static function response(ExternalOperationResponse $response, string $requestId): Response
    {
        $body = $response->body;
        if ($response->status >= 200 && $response->status < 300) {
            $meta = ['request_id' => $requestId];
            foreach (['page', 'page_size', 'total'] as $key) {
                if (array_key_exists($key, $body)) {
                    $meta[$key] = $body[$key];
                    unset($body[$key]);
                }
            }
            $body['meta'] = $meta;
        }
        $headers = ['Content-Type' => $response->contentType, 'X-Request-Id' => $requestId, 'Cache-Control' => 'no-store', ...$response->headers];
        $data = $body['data'] ?? null;
        if (is_array($data) && is_int($data['revision'] ?? null)) {
            $headers['ETag'] = '"rev-' . $data['revision'] . '"';
        }
        return Response::create($body, 'json', $response->status)->header($headers);
    }

    public static function expectedRevision(ExternalOperationRequest $request, bool $optional = false): ?int
    {
        $value = $request->body['if_match'] ?? null;
        if ($optional && ($value === null || $value === '')) {
            return null;
        }
        if (!is_string($value) || preg_match('/^"rev-([1-9][0-9]*)"$/D', $value, $matches) !== 1) {
            throw new ApiException('PRECONDITION_REQUIRED', 428, 'A strong revision precondition is required.');
        }
        $revision = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($revision)) {
            throw new ApiException('PRECONDITION_REQUIRED', 428, 'A strong revision precondition is required.');
        }
        return $revision;
    }

    /** @return callable(AuthorizedExternalOperation, ExternalOperationRequest, PDO): void */
    public static function commandGuard(string $moduleKey): callable
    {
        return static function (AuthorizedExternalOperation $authorized, ExternalOperationRequest $request, PDO $pdo) use ($moduleKey): void {
            $context = self::context($authorized);
            if (!$pdo->inTransaction()) {
                throw new LogicException('Module command guard requires an active transaction.');
            }
            $deployment = $pdo->prepare('SELECT module_key FROM pa_module_installation WHERE module_key = :module_key FOR SHARE');
            $deployment->execute(['module_key' => $moduleKey]);
            $tenant = $pdo->prepare('SELECT tenant_id FROM pa_tenant_module WHERE tenant_id = :tenant_id AND module_key = :module_key FOR SHARE');
            $tenant->execute(['tenant_id' => $context->tenantId, 'module_key' => $moduleKey]);
            $guard = new ModuleGuard(new PdoModuleRuntimeRepository($pdo));
            $guard->assertDeployment($moduleKey);
            $guard->assertTenant($context->tenantId, $moduleKey, $request->comparisonTime);
        };
    }

    public static function positiveInt(mixed $value, int $maximum): int
    {
        $integer = is_int($value) ? $value : (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false);
        if (!is_int($integer) || $integer > $maximum) {
            throw new ApiException('VALIDATION_FAILED', 422, 'A positive integer is required.');
        }
        return $integer;
    }

    private static function configuration(): ExternalHostConfiguration
    {
        $root = dirname(__DIR__, 3);
        $moduleConfig = require $root . '/backend/config/modules.php';
        $authConfig = require $root . '/backend/config/auth.php';
        return new ExternalHostConfiguration(
            new ModuleHostLayout('backend/app/Modules', 'PeanutAdmin\\App\\Modules', 'frontend/src/modules'),
            $moduleConfig['roots'],
            '/api/v1',
            '/api/platform/v1',
            'docs/api/openapi.yaml',
            'backend/route/openapi-generated.php',
            'packages/web/admin-core/src/generated/api.d.ts',
            $authConfig['tenant']['clients'],
            'X-Request-Id',
        );
    }

    private static function millisecond(DateTimeImmutable $date): DateTimeImmutable
    {
        $date = $date->setTimezone(new DateTimeZone('UTC'));
        return $date->setTime((int) $date->format('H'), (int) $date->format('i'), (int) $date->format('s'), (int) $date->format('v') * 1000);
    }
}
