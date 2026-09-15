<?php

declare(strict_types=1);

namespace PeanutAdmin\App\setting;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Module\ModuleAvailabilityService;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use PeanutAdmin\Settings\Application\SettingException;
use PeanutAdmin\Settings\Definition\SettingDefinition;
use PeanutAdmin\Settings\Model\SettingDefinitionRecord;
use think\facade\Db;
use think\Request;
use think\Response;

final readonly class SettingsCommandGuard
{
    public function __construct(
        private SettingDefinitionCatalog $catalog,
        private ModuleAvailabilityService $modules,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $moduleKey = $request->route('module_key');
        $settingKey = $request->route('setting_key');
        if (!is_string($moduleKey) || $moduleKey === '' || !is_string($settingKey) || $settingKey === '') {
            throw new ApiException('SETTING_NOT_FOUND', 404, 'The requested setting is unavailable.');
        }
        try {
            $definition = $this->catalog->registry()->require($moduleKey, $settingKey);
        } catch (SettingException $exception) {
            throw new ApiException($exception->errorCode, $exception->httpStatus, $exception->getMessage());
        }

        return Db::transaction(function () use ($request, $next, $definition): Response {
            $route = $request->route();
            $routeValues = is_array($route) ? $route : [];
            $tenant = $routeValues['tenant_context'] ?? null;
            $platform = $routeValues['platform_context'] ?? null;
            if ($tenant instanceof TenantContext) {
                $this->assertTenantAvailability($tenant, $definition);
            } elseif ($platform instanceof PlatformContext) {
                $this->assertPlatformAvailability($definition);
            } else {
                throw new ApiException('SETTING_ACTOR_UNAUTHORIZED', 404, 'The requested setting is unavailable.');
            }
            $this->assertCurrentDefinition($definition);

            return $next($request);
        });
    }

    private function assertTenantAvailability(TenantContext $context, SettingDefinition $definition): void
    {
        $scope = TenantScope::fromTrustedContext($context->tenantId, $context->requestId);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        try {
            $this->modules->assertAvailable($scope, 'peanut.settings', $now, true);
        } catch (ModuleException) {
            throw new ApiException('MODULE_UNAVAILABLE', 404, 'The requested Module is unavailable.');
        }
        try {
            $this->modules->assertAvailable($scope, $definition->moduleKey, $now, true);
        } catch (ModuleException) {
            throw new ApiException('SETTING_NOT_FOUND', 404, 'The requested setting is unavailable.');
        }
    }

    private function assertPlatformAvailability(SettingDefinition $definition): void
    {
        try {
            $this->modules->assertDeployment('peanut.settings', true);
        } catch (ModuleException) {
            throw new ApiException('MODULE_UNAVAILABLE', 404, 'The requested Module is unavailable.');
        }
        try {
            $this->modules->assertDeployment($definition->moduleKey, true);
        } catch (ModuleException) {
            throw new ApiException('SETTING_NOT_FOUND', 404, 'The requested setting is unavailable.');
        }
    }

    private function assertCurrentDefinition(SettingDefinition $definition): void
    {
        $record = SettingDefinitionRecord::where('module_key', $definition->moduleKey)
            ->where('setting_key', $definition->key)
            ->where('status', 'active')
            ->lock('FOR SHARE')
            ->find();
        if (!$record instanceof SettingDefinitionRecord
            || !hash_equals((string) $record->getAttr('definition_digest'), $definition->digest)) {
            throw new ApiException('SETTING_NOT_FOUND', 404, 'The requested setting is unavailable.');
        }
    }
}
