<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\controller\api\AuthHttpRuntime;
use PeanutAdmin\App\controller\api\WorkspaceContextRuntime;
use PeanutAdmin\App\middleware\TenantAuthRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Http\TenantAuthEndpoint;
use think\Request;
use think\Response;

final class TenantAuthController
{
    #[OpenApiHandlerContract]
    public function login(Request $request): Response
    {
        $body = AuthHttpRuntime::body($request);
        $email = AuthHttpRuntime::requiredString($body, 'email');
        $password = AuthHttpRuntime::requiredString($body, 'password');
        $tenantCode = AuthHttpRuntime::optionalString($body, 'tenant_code');

        return AuthHttpRuntime::tenantResponse($this->endpoint()->login(
            $email,
            $password,
            $tenantCode,
            AuthHttpRuntime::ipAddress($request),
            AuthHttpRuntime::userAgent($request),
            AuthHttpRuntime::requestId($request),
        ));
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::AUTHENTICATED_HEADERS)]
    public function selectTenant(Request $request): Response
    {
        $body = AuthHttpRuntime::body($request);
        $challengeToken = AuthHttpRuntime::requiredString($body, 'challenge_token');
        $tenantId = AuthHttpRuntime::positiveInteger($body, 'tenant_id');

        return AuthHttpRuntime::tenantResponse($this->endpoint()->selectTenant(
            $challengeToken,
            $tenantId,
            AuthHttpRuntime::ipAddress($request),
            AuthHttpRuntime::userAgent($request),
            AuthHttpRuntime::requestId($request),
        ));
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::AUTHENTICATED_HEADERS)]
    public function refresh(Request $request): Response
    {
        $endpoint = $this->endpoint();
        $refreshToken = AuthHttpRuntime::requiredCookie($request, $endpoint->refreshCookieName());

        return AuthHttpRuntime::tenantResponse($endpoint->refresh(
            $refreshToken,
            AuthHttpRuntime::trustedOrigin($request),
            AuthHttpRuntime::ipAddress($request),
            AuthHttpRuntime::userAgent($request),
            AuthHttpRuntime::requestId($request),
        ));
    }

    #[OpenApiHandlerContract]
    public function context(Request $request): Response
    {
        $requestId = AuthHttpRuntime::requestId($request);
        $context = TenantAuthRuntimeFactory::create()->context(
            AuthHttpRuntime::bearerToken($request),
            $requestId,
        );

        return AuthHttpRuntime::response(200, [
            'data' => WorkspaceContextRuntime::tenant(MemberAdminRuntime::pdo(), $context),
            'meta' => ['request_id' => $requestId],
        ]);
    }

    #[OpenApiHandlerContract]
    public function switchChallenge(Request $request): Response
    {
        return AuthHttpRuntime::tenantResponse($this->endpoint()->switchChallenge(
            AuthHttpRuntime::bearerToken($request),
            AuthHttpRuntime::ipAddress($request),
            AuthHttpRuntime::userAgent($request),
            AuthHttpRuntime::requestId($request),
        ));
    }

    #[OpenApiHandlerContract(
        successStatus: 204,
        hasJsonBody: false,
        headers: OpenApiHandlerContract::SESSION_CLEARED_HEADERS,
    )]
    public function logout(Request $request): Response
    {
        return AuthHttpRuntime::tenantResponse($this->endpoint()->logout(
            AuthHttpRuntime::bearerToken($request),
            AuthHttpRuntime::requestId($request),
        ));
    }

    #[OpenApiHandlerContract(
        successStatus: 204,
        hasJsonBody: false,
        headers: OpenApiHandlerContract::SESSION_CLEARED_HEADERS,
    )]
    public function logoutAll(Request $request): Response
    {
        return AuthHttpRuntime::tenantResponse($this->endpoint()->logoutAll(
            AuthHttpRuntime::bearerToken($request),
            AuthHttpRuntime::requestId($request),
        ));
    }

    private function endpoint(): TenantAuthEndpoint
    {
        return new TenantAuthEndpoint(TenantAuthRuntimeFactory::create());
    }
}
