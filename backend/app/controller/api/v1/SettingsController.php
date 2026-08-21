<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\setting\SettingsRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class SettingsController
{
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function listTenantSettings(Request $request): Response
    {
        return SettingsRuntimeFactory::listTenant($request);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceTenantSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return SettingsRuntimeFactory::replaceTenant($request, $moduleKey, $settingKey);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function unsetTenantSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return SettingsRuntimeFactory::unsetTenant($request, $moduleKey, $settingKey);
    }
}
