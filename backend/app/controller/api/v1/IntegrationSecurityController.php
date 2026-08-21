<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\integrationsecurity\IntegrationSecurityHttpRuntime;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class IntegrationSecurityController
{
    #[OpenApiHandlerContract] public function machines(Request $r): Response
    {
        return IntegrationSecurityHttpRuntime::machines($r);
    }
    #[OpenApiHandlerContract(successStatus: 201)] public function createMachine(Request $r): Response
    {
        return IntegrationSecurityHttpRuntime::createMachine($r);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function rotateMachine(Request $r, string $identityKey): Response
    {
        return IntegrationSecurityHttpRuntime::rotateMachine($r, $identityKey);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function revokeMachine(Request $r, string $identityKey): Response
    {
        return IntegrationSecurityHttpRuntime::revokeMachine($r, $identityKey);
    }
    #[OpenApiHandlerContract] public function webhooks(Request $r): Response
    {
        return IntegrationSecurityHttpRuntime::webhooks($r);
    }
    #[OpenApiHandlerContract(successStatus: 201)] public function createWebhook(Request $r): Response
    {
        return IntegrationSecurityHttpRuntime::createWebhook($r);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function rotateWebhook(Request $r, string $endpointKey): Response
    {
        return IntegrationSecurityHttpRuntime::rotateWebhook($r, $endpointKey);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function disableWebhook(Request $r, string $endpointKey): Response
    {
        return IntegrationSecurityHttpRuntime::disableWebhook($r, $endpointKey);
    }
    #[OpenApiHandlerContract] public function deliveries(Request $r): Response
    {
        return IntegrationSecurityHttpRuntime::deliveries($r);
    }
    #[OpenApiHandlerContract] public function attempts(Request $r, string $deliveryKey): Response
    {
        return IntegrationSecurityHttpRuntime::attempts($r, $deliveryKey);
    }
    #[OpenApiHandlerContract] public function sessions(Request $r): Response
    {
        return IntegrationSecurityHttpRuntime::sessions($r);
    }
    #[OpenApiHandlerContract] public function revokeSession(Request $r, string $sessionKey): Response
    {
        return IntegrationSecurityHttpRuntime::revokeSession($r, $sessionKey);
    }
}
