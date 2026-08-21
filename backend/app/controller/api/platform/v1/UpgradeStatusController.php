<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\upgrade\UpgradeStatusService;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class UpgradeStatusController
{
    #[OpenApiHandlerContract]
    public function show(Request $request): Response
    {
        return MemberAdminRuntime::run(
            $request,
            static fn(): array => ['data' => UpgradeStatusService::fromEnvironment()->status()],
        );
    }
}
