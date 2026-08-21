<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\platform\v1;

use PeanutAdmin\App\controller\api\v1\MemberAdminRuntime;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\Etag;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Platform\Application\TenantOwnerAdminService;
use think\Request;
use think\Response;

final class TenantOwnerController
{
    #[OpenApiHandlerContract(
        successStatus: 201,
        headers: OpenApiHandlerContract::CREATED_HEADERS,
    )]
    public function create(Request $request, string $tenantId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $tenantId): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $result = $this->service()->createCandidate(
                $context->operatorId,
                $context->accountId,
                (int) $tenantId,
                (string) ($body['email'] ?? ''),
                (string) ($body['display_name'] ?? ''),
                isset($body['initial_password']) ? (string) $body['initial_password'] : null,
                $context->requestId,
            );
            $member = $result['member'];

            return [
                'data' => $result,
                'status' => 201,
                'etag' => Etag::format((int) $member['revision']),
                'location' => '/api/platform/v1/tenants/' . $tenantId
                    . '/owner-candidates/' . $member['id'],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function activate(Request $request, string $tenantId, string $memberId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $tenantId, $memberId): array {
            $context = $this->context($request);
            $body = MemberAdminRuntime::body($request);
            $idempotencyKey = $request->header('idempotency-key');
            $result = $this->service()->activateCandidate(
                $context->operatorId,
                $context->accountId,
                (int) $tenantId,
                (int) $memberId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                is_string($idempotencyKey) ? $idempotencyKey : '',
                (string) ($body['change_reason'] ?? ''),
                $context->requestId,
            );
            $member = $result['member'];

            return ['data' => $result, 'etag' => Etag::format((int) $member['revision'])];
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

    private function service(): TenantOwnerAdminService
    {
        return new TenantOwnerAdminService(MemberAdminRuntime::pdo());
    }
}
