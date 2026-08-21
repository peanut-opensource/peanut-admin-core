<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\Etag;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Platform\Application\PlatformAccessAdminService;
use PeanutAdmin\Kernel\Platform\PlatformOperatorStatus;
use think\Request;
use think\Response;

final class PlatformAccessController
{
    #[OpenApiHandlerContract(
        successStatus: 201,
        headers: OpenApiHandlerContract::CREATED_HEADERS,
    )]
    public function createOperator(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $operator = $this->service()->createOperator(
                $context->operatorId,
                $context->accountId,
                (string) ($body['email'] ?? ''),
                (string) ($body['display_name'] ?? ''),
                isset($body['initial_password']) ? (string) $body['initial_password'] : null,
                $context->requestId,
            );

            return [
                'data' => $operator,
                'status' => 201,
                'etag' => Etag::format((int) $operator['security_revision']),
                'location' => '/api/platform/v1/operators/' . $operator['id'],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function updateOperator(Request $request, string $operatorId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $operatorId): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $operator = $this->service()->updateOperator(
                $context->operatorId,
                $context->accountId,
                (int) $operatorId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                (string) ($body['display_name'] ?? ''),
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return ['data' => $operator, 'etag' => Etag::format((int) $operator['security_revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceOperatorRoles(Request $request, string $operatorId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $operatorId): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $rawRoleIds = is_array($body['role_ids'] ?? null) ? $body['role_ids'] : [];
            $operator = $this->service()->replaceOperatorRoles(
                $context->operatorId,
                $context->accountId,
                (int) $operatorId,
                array_values(array_map(static fn(mixed $id): int => (int) $id, $rawRoleIds)),
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return ['data' => $operator, 'etag' => Etag::format((int) $operator['security_revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function suspendOperator(Request $request, string $operatorId): Response
    {
        return $this->transitionOperator($request, $operatorId, PlatformOperatorStatus::Suspended);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function activateOperator(Request $request, string $operatorId): Response
    {
        return $this->transitionOperator($request, $operatorId, PlatformOperatorStatus::Active);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function closeOperator(Request $request, string $operatorId): Response
    {
        return $this->transitionOperator($request, $operatorId, PlatformOperatorStatus::Closed);
    }

    #[OpenApiHandlerContract(
        successStatus: 201,
        headers: OpenApiHandlerContract::CREATED_HEADERS,
    )]
    public function createRole(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $role = $this->service()->createRole(
                $context->operatorId,
                $context->accountId,
                (string) ($body['key'] ?? ''),
                (string) ($body['name'] ?? ''),
                isset($body['description']) ? (string) $body['description'] : null,
                $context->requestId,
            );

            return [
                'data' => $role,
                'status' => 201,
                'etag' => Etag::format((int) $role['revision']),
                'location' => '/api/platform/v1/roles/' . $role['id'],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function updateRole(Request $request, string $roleId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $roleId): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $role = $this->service()->updateRole(
                $context->operatorId,
                $context->accountId,
                (int) $roleId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                (string) ($body['name'] ?? ''),
                isset($body['description']) ? (string) $body['description'] : null,
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return ['data' => $role, 'etag' => Etag::format((int) $role['revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function archiveRole(Request $request, string $roleId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $roleId): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $role = $this->service()->archiveRole(
                $context->operatorId,
                $context->accountId,
                (int) $roleId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return ['data' => $role, 'etag' => Etag::format((int) $role['revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceRolePermissions(Request $request, string $roleId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $roleId): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $rawKeys = is_array($body['permission_keys'] ?? null) ? $body['permission_keys'] : [];
            $role = $this->service()->replaceRolePermissions(
                $context->operatorId,
                $context->accountId,
                (int) $roleId,
                array_values(array_map(static fn(mixed $key): string => (string) $key, $rawKeys)),
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return ['data' => $role, 'etag' => Etag::format((int) $role['revision'])];
        });
    }

    private function transitionOperator(
        Request $request,
        string $operatorId,
        PlatformOperatorStatus $status,
    ): Response {
        return MemberAdminRuntime::run($request, function () use ($request, $operatorId, $status): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $operator = $this->service()->transitionOperator(
                $context->operatorId,
                $context->accountId,
                (int) $operatorId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                $status,
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );

            return ['data' => $operator, 'etag' => Etag::format((int) $operator['security_revision'])];
        });
    }

    private function context(Request $request): PlatformContext
    {
        $route = $request->route();
        $context = is_array($route) ? ($route['platform_context'] ?? null) : null;
        if (!$context instanceof PlatformContext) {
            throw new AdminAccessException('CONTEXT_PLATFORM_REQUIRED', 403, 'A platform context is required.');
        }

        return $context;
    }

    private function service(): PlatformAccessAdminService
    {
        return new PlatformAccessAdminService(MemberAdminRuntime::pdo());
    }
}
