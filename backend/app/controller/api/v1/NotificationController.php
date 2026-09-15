<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\notification\NotificationHttpRuntime;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class NotificationController
{
    public function __construct(private readonly NotificationHttpRuntime $runtime) {}

    #[OpenApiHandlerContract] public function index(Request $request): Response
    {
        return $this->runtime->inbox($request);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function markRead(Request $request, string $messageKey): Response
    {
        return $this->runtime->markRead($request, $messageKey);
    }
    #[OpenApiHandlerContract] public function bulk(Request $request): Response
    {
        return $this->runtime->bulk($request);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function putTemplate(Request $request, string $templateKey): Response
    {
        return $this->runtime->putTemplate($request, $templateKey);
    }
    #[OpenApiHandlerContract(successStatus: 201)] public function publish(Request $request): Response
    {
        return $this->runtime->publish($request);
    }
    #[OpenApiHandlerContract(successStatus: 202)] public function dispatch(Request $request, string $outboxKey): Response
    {
        return $this->runtime->dispatch($request, $outboxKey);
    }
}
