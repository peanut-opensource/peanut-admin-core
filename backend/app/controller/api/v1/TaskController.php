<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\task\TaskHttpRuntime;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class TaskController
{
    public function __construct(private readonly TaskHttpRuntime $runtime) {}

    #[OpenApiHandlerContract] public function index(Request $request): Response
    {
        return $this->runtime->list($request);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function show(Request $request, string $jobKey): Response
    {
        return $this->runtime->detail($request, $jobKey);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function cancel(Request $request, string $jobKey): Response
    {
        return $this->runtime->cancel($request, $jobKey);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function retry(Request $request, string $jobKey): Response
    {
        return $this->runtime->retry($request, $jobKey);
    }
}
