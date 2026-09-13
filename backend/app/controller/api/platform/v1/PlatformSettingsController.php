<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use PeanutAdmin\App\setting\SettingsRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\db\PDOConnection;
use think\Request;
use think\Response;

final class PlatformSettingsController
{
    public function __construct(private readonly PDOConnection $connection) {}

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function listDeploymentSettings(Request $request): Response
    {
        return SettingsRuntimeFactory::listDeployment($request, $this->connection);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceDeploymentSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return SettingsRuntimeFactory::replaceDeployment($request, $moduleKey, $settingKey, $this->connection);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function unsetDeploymentSetting(
        Request $request,
        string $moduleKey,
        string $settingKey,
    ): Response {
        return SettingsRuntimeFactory::unsetDeployment($request, $moduleKey, $settingKey, $this->connection);
    }
}
