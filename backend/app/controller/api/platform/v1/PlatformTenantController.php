<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use DateTimeImmutable;
use Exception;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\module\OpisTenantModuleConfigValidator;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\Etag;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Module\Persistence\PdoModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Platform\Application\PlatformTenantAdminService;
use PeanutAdmin\Kernel\Tenancy\TenantStatus;
use think\Request;
use think\Response;

final class PlatformTenantController
{
    #[OpenApiHandlerContract(
        successStatus: 201,
        headers: OpenApiHandlerContract::CREATED_HEADERS,
    )]
    public function create(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $tenant = $this->service()->createTenant(
                $context->operatorId,
                $context->accountId,
                (string) ($body['code'] ?? ''),
                (string) ($body['name'] ?? ''),
                (string) ($body['display_name'] ?? ''),
                (string) ($body['locale'] ?? 'zh-CN'),
                (string) ($body['timezone'] ?? 'Asia/Shanghai'),
                $context->requestId,
            );

            return [
                'data' => $tenant,
                'status' => 201,
                'etag' => Etag::format((int) $tenant['revision']),
                'location' => '/api/platform/v1/tenants/' . $tenant['id'],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function update(Request $request, string $tenantId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $tenantId): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $tenant = $this->service()->updateTenant(
                $context->operatorId,
                $context->accountId,
                (int) $tenantId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                (string) ($body['name'] ?? ''),
                (string) ($body['display_name'] ?? ''),
                (string) ($body['locale'] ?? ''),
                (string) ($body['timezone'] ?? ''),
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return ['data' => $tenant, 'etag' => Etag::format((int) $tenant['revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function activate(Request $request, string $tenantId): Response
    {
        return $this->transition($request, $tenantId, TenantStatus::Active);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function suspend(Request $request, string $tenantId): Response
    {
        return $this->transition($request, $tenantId, TenantStatus::Suspended);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function close(Request $request, string $tenantId): Response
    {
        return $this->transition($request, $tenantId, TenantStatus::Closed);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function enableModule(Request $request, string $tenantId, string $moduleKey): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $tenantId, $moduleKey): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            if (($body['status'] ?? null) !== 'enabled') {
                throw AdminAccessException::invalid(
                    'MODULE_STATUS_INVALID',
                    'The module enable request must use status=enabled.',
                );
            }
            $config = $body['config'] ?? [];
            if (!is_array($config)) {
                throw AdminAccessException::invalid('MODULE_CONFIG_INVALID', 'Module config must be an object.');
            }
            if (($body['source'] ?? 'manual') !== 'manual') {
                throw AdminAccessException::invalid(
                    'MODULE_SOURCE_INVALID',
                    'Platform API module changes must use source=manual.',
                );
            }
            $module = $this->service()->enableModule(
                $context->operatorId,
                $context->accountId,
                (int) $tenantId,
                $moduleKey,
                $config,
                'manual',
                $this->dateTime($body['effective_at'] ?? null, 'effective_at'),
                $this->dateTime($body['expires_at'] ?? null, 'expires_at'),
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return [
                'data' => $module,
                'etag' => Etag::format((int) $module['authorization_revision']),
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function disableModule(Request $request, string $tenantId, string $moduleKey): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $tenantId, $moduleKey): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $module = $this->service()->disableModule(
                $context->operatorId,
                $context->accountId,
                (int) $tenantId,
                $moduleKey,
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return [
                'data' => $module,
                'etag' => Etag::format((int) $module['authorization_revision']),
            ];
        });
    }

    private function transition(Request $request, string $tenantId, TenantStatus $status): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $tenantId, $status): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $tenant = $this->service()->transitionTenant(
                $context->operatorId,
                $context->accountId,
                (int) $tenantId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                $status,
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return ['data' => $tenant, 'etag' => Etag::format((int) $tenant['revision'])];
        });
    }

    private function context(Request $request): PlatformContext
    {
        $route = $request->route();
        $context = is_array($route) ? ($route['platform_context'] ?? null) : null;
        if (!$context instanceof PlatformContext) {
            throw new AdminAccessException('CONTEXT_PLATFORM_REQUIRED', 403, 'A platform context is required.');
        }

        return $context;
    }

    private function service(): PlatformTenantAdminService
    {
        $pdo = MemberAdminRuntime::pdo();

        return new PlatformTenantAdminService(
            $pdo,
            new TenantModuleManager(
                RuntimeModuleRegistry::compile(),
                new PdoModuleRuntimeRepository($pdo),
                new OpisTenantModuleConfigValidator(),
            ),
        );
    }

    private function dateTime(mixed $value, string $field): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw AdminAccessException::invalid('MODULE_PERIOD_INVALID', "{$field} must be an ISO-8601 timestamp.");
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw AdminAccessException::invalid('MODULE_PERIOD_INVALID', "{$field} must be an ISO-8601 timestamp.");
        }
    }
}
