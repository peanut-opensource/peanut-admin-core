<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\integrationsecurity\IntegrationSecurityHttpRuntime;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class IntegrationSecurityController
{
    public function __construct(private readonly IntegrationSecurityHttpRuntime $runtime) {}

    #[OpenApiHandlerContract] public function machines(Request $r): Response
    {
        return $this->runtime->machines($r);
    }
    #[OpenApiHandlerContract(successStatus: 201)] public function createMachine(Request $r): Response
    {
        return $this->runtime->createMachine($r);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function rotateMachine(Request $r, string $identityKey): Response
    {
        return $this->runtime->rotateMachine($r, $identityKey);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function revokeMachine(Request $r, string $identityKey): Response
    {
        return $this->runtime->revokeMachine($r, $identityKey);
    }
    #[OpenApiHandlerContract] public function webhooks(Request $r): Response
    {
        return $this->runtime->webhooks($r);
    }
    #[OpenApiHandlerContract(successStatus: 201)] public function createWebhook(Request $r): Response
    {
        return $this->runtime->createWebhook($r);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function rotateWebhook(Request $r, string $endpointKey): Response
    {
        return $this->runtime->rotateWebhook($r, $endpointKey);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function disableWebhook(Request $r, string $endpointKey): Response
    {
        return $this->runtime->disableWebhook($r, $endpointKey);
    }
    #[OpenApiHandlerContract] public function deliveries(Request $r): Response
    {
        return $this->runtime->deliveries($r);
    }
    #[OpenApiHandlerContract] public function attempts(Request $r, string $deliveryKey): Response
    {
        return $this->runtime->attempts($r, $deliveryKey);
    }
    #[OpenApiHandlerContract] public function sessions(Request $r): Response
    {
        return $this->runtime->sessions($r);
    }
    #[OpenApiHandlerContract] public function revokeSession(Request $r, string $sessionKey): Response
    {
        return $this->runtime->revokeSession($r, $sessionKey);
    }
}
