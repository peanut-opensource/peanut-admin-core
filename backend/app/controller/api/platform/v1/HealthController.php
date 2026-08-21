<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use PeanutAdmin\App\command\HealthCheckService;
use think\Response;

final class HealthController
{
    public function show(): Response
    {
        $report = HealthCheckService::fromEnvironment()->check();

        return Response::create($report->toArray(), 'json', $report->httpStatus())
            ->header(['Cache-Control' => 'no-store']);
    }
}
