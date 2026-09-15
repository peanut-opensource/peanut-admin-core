<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use PeanutAdmin\App\setting\SettingsHttpService;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class PlatformSettingsController
{
    public function __construct(private readonly SettingsHttpService $settings) {}

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function listDeploymentSettings(Request $request): Response
    {
        return $this->settings->listDeployment($request);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceDeploymentSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return $this->settings->replaceDeployment($request, $moduleKey, $settingKey);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function unsetDeploymentSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return $this->settings->unsetDeployment($request, $moduleKey, $settingKey);
    }
}
