<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use PeanutAdmin\App\controller\api\AuthHttpRuntime;
use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\App\controller\api\WorkspaceContextRuntime;
use PeanutAdmin\App\middleware\PlatformAuthRuntimeFactory;
use PeanutAdmin\Kernel\Api\ApiException;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Auth\PlatformAuthentication;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\PlatformRefreshCookie;
use think\Request;
use think\Response;

final class PlatformAuthController
{
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::AUTHENTICATED_HEADERS)]
    public function login(Request $request): Response
    {
        $body = AuthHttpRuntime::body($request);
        $email = AuthHttpRuntime::requiredString($body, 'email');
        $password = AuthHttpRuntime::requiredString($body, 'password');
        $authentication = $this->service()->login(
            $email,
            $password,
            AuthHttpRuntime::ipAddress($request),
            AuthHttpRuntime::userAgent($request),
            AuthHttpRuntime::requestId($request),
        );

        return $this->authenticated($authentication, AuthHttpRuntime::requestId($request));
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::AUTHENTICATED_HEADERS)]
    public function refresh(Request $request): Response
    {
        if (!AuthHttpRuntime::trustedOrigin($request)) {
            throw new ApiException('AUTH_TOKEN_INVALID', 401, 'The refresh request origin is not trusted.');
        }
        $refreshToken = AuthHttpRuntime::requiredCookie($request, PlatformRefreshCookie::NAME);
        $authentication = $this->service()->refresh(
            $refreshToken,
            AuthHttpRuntime::ipAddress($request),
            AuthHttpRuntime::userAgent($request),
            AuthHttpRuntime::requestId($request),
        );

        return $this->authenticated($authentication, AuthHttpRuntime::requestId($request));
    }

    #[OpenApiHandlerContract]
    public function context(Request $request): Response
    {
        $requestId = AuthHttpRuntime::requestId($request);
        $context = $this->service()->context(
            AuthHttpRuntime::bearerToken($request),
            $requestId,
        );

        return AuthHttpRuntime::response(200, [
            'data' => WorkspaceContextRuntime::platform(MemberAdminRuntime::pdo(), $context),
            'meta' => ['request_id' => $requestId],
        ]);
    }

    #[OpenApiHandlerContract(
        successStatus: 204,
        hasJsonBody: false,
        headers: OpenApiHandlerContract::SESSION_CLEARED_HEADERS,
    )]
    public function logout(Request $request): Response
    {
        $this->service()->logout(AuthHttpRuntime::bearerToken($request));

        return AuthHttpRuntime::response(204, null, [
            'Set-Cookie' => PlatformRefreshCookie::clear(),
        ]);
    }

    private function authenticated(PlatformAuthentication $authentication, string $requestId): Response
    {
        return AuthHttpRuntime::response(200, [
            'data' => $authentication->responseData(),
            'meta' => ['request_id' => $requestId],
        ], [
            'Set-Cookie' => PlatformRefreshCookie::issue($authentication->tokens->refresh),
        ]);
    }

    private function service(): PlatformAuthService
    {
        return PlatformAuthRuntimeFactory::create();
    }
}
