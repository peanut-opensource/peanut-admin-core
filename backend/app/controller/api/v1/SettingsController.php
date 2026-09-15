<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\setting\SettingsHttpService;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class SettingsController
{
    public function __construct(private readonly SettingsHttpService $settings) {}

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function listTenantSettings(Request $request): Response
    {
        return $this->settings->listTenant($request);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceTenantSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return $this->settings->replaceTenant($request, $moduleKey, $settingKey);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function unsetTenantSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return $this->settings->unsetTenant($request, $moduleKey, $settingKey);
    }
}
