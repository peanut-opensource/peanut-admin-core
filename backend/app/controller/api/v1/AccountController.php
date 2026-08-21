<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\controller\api\AuthHttpRuntime;
use PeanutAdmin\App\middleware\TenantAccountRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Auth\TenantClient;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Http\TenantRefreshCookie;
use PeanutAdmin\Kernel\Identity\SelfService\AccountSelfService;
use think\Request;
use think\Response;

final class AccountController
{
    #[OpenApiHandlerContract]
    public function show(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);

            return ['data' => $this->service()->profile(
                $context->tenantId,
                $context->memberId,
                $context->accountId,
            )];
        });
    }

    #[OpenApiHandlerContract]
    public function update(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);
            $body = MemberAdminRuntime::body($request);
            self::assertDeclaredFields(
                $body,
                ['display_name', 'avatar_uri'],
                'ACCOUNT_PROFILE_INVALID',
                'The account profile request contains undeclared fields.',
            );
            if (!array_key_exists('display_name', $body) || !is_string($body['display_name'])) {
                throw AdminAccessException::invalid(
                    'ACCOUNT_PROFILE_INVALID',
                    'The display name must be a string.',
                );
            }
            if (!array_key_exists('avatar_uri', $body)) {
                throw AdminAccessException::invalid(
                    'AVATAR_URI_INVALID',
                    'The avatar URI field is required.',
                );
            }
            $avatarUri = $body['avatar_uri'];
            if ($avatarUri !== null && !is_string($avatarUri)) {
                throw AdminAccessException::invalid(
                    'AVATAR_URI_INVALID',
                    'The avatar URI must be a string or null.',
                );
            }

            return ['data' => $this->service()->updateProfile(
                $context->tenantId,
                $context->memberId,
                $context->accountId,
                $body['display_name'],
                $avatarUri,
                $context->requestId,
            )];
        });
    }

    #[OpenApiHandlerContract(
        successStatus: 204,
        hasJsonBody: false,
        headers: OpenApiHandlerContract::SESSION_CLEARED_HEADERS,
    )]
    public function changePassword(Request $request): Response
    {
        $context = MemberAdminRuntime::context($request);
        $body = MemberAdminRuntime::body($request);
        self::assertDeclaredFields(
            $body,
            ['current_password', 'new_password'],
            'CURRENT_PASSWORD_INVALID',
            'The password request contains undeclared fields.',
        );
        if (!array_key_exists('current_password', $body) || !is_string($body['current_password'])) {
            throw AdminAccessException::invalid(
                'CURRENT_PASSWORD_INVALID',
                'The current password is invalid.',
            );
        }
        if (!array_key_exists('new_password', $body) || !is_string($body['new_password'])) {
            throw AdminAccessException::invalid(
                'NEW_PASSWORD_INVALID',
                'The new password is invalid.',
            );
        }
        $service = $this->service();
        $clearRefreshCookie = TenantRefreshCookie::clear(new TenantClient($context->clientKey));

        try {
            $service->changePassword(
                $context->tenantId,
                $context->memberId,
                $context->accountId,
                $context->sessionKey,
                $body['current_password'],
                $body['new_password'],
                AuthHttpRuntime::ipAddress($request),
                AuthHttpRuntime::userAgent($request),
                $context->requestId,
            );
        } catch (AdminAccessException $exception) {
            if ($exception->errorCode !== 'PASSWORD_CHANGE_RATE_LIMITED') {
                throw $exception;
            }

            return AuthHttpRuntime::response(429, [
                'type' => '/docs/problems/password-change-rate-limited',
                'title' => 'Request rejected',
                'status' => 429,
                'detail' => $exception->getMessage(),
                'instance' => 'urn:request:' . $context->requestId,
                'code' => $exception->errorCode,
                'request_id' => $context->requestId,
            ], [
                'Content-Type' => 'application/problem+json',
                'X-Request-Id' => $context->requestId,
                'Retry-After' => (string) AccountSelfService::PASSWORD_CHANGE_RETRY_AFTER_SECONDS,
            ]);
        }

        return AuthHttpRuntime::response(204, null, [
            'Set-Cookie' => $clearRefreshCookie,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $declaredFields
     */
    private static function assertDeclaredFields(
        array $body,
        array $declaredFields,
        string $errorCode,
        string $message,
    ): void {
        if (array_diff(array_keys($body), $declaredFields) !== []) {
            throw AdminAccessException::invalid($errorCode, $message);
        }
    }

    private function service(): AccountSelfService
    {
        return TenantAccountRuntimeFactory::create();
    }
}
