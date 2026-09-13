<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\setting\SettingsRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\db\PDOConnection;
use think\Request;
use think\Response;

final class SettingsController
{
    public function __construct(private readonly PDOConnection $connection) {}

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function listTenantSettings(Request $request): Response
    {
        return SettingsRuntimeFactory::listTenant($request, $this->connection);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceTenantSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return SettingsRuntimeFactory::replaceTenant($request, $moduleKey, $settingKey, $this->connection);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function unsetTenantSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return SettingsRuntimeFactory::unsetTenant($request, $moduleKey, $settingKey, $this->connection);
    }
}
