<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Authorization\Application\Etag;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;
use think\Request;
use think\Response;

final class MemberController
{
    #[OpenApiHandlerContract]
    public function index(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);
            $page = MemberAdminRuntime::page($request);
            $result = $this->service()->list($context->tenantId, $page);
            $totalPages = (int) ceil($result['total'] / $page->pageSize);

            return [
                'data' => $result['items'],
                'meta' => [
                    'page' => $page->page,
                    'page_size' => $page->pageSize,
                    'total' => $result['total'],
                    'total_pages' => $totalPages,
                ],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function show(Request $request, string $memberId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $memberId): array {
            $context = MemberAdminRuntime::context($request);
            $member = $this->service()->get($context->tenantId, (int) $memberId);

            return ['data' => $member, 'etag' => Etag::format((int) $member['revision'])];
        });
    }

    #[OpenApiHandlerContract(
        successStatus: 201,
        headers: OpenApiHandlerContract::CREATED_HEADERS,
    )]
    public function create(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);
            $body = MemberAdminRuntime::body($request);
            $member = $this->service()->createPending(
                $context->tenantId,
                (string) ($body['email'] ?? ''),
                (string) ($body['display_name'] ?? ''),
                isset($body['initial_password']) ? (string) $body['initial_password'] : null,
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );

            return [
                'data' => $member,
                'status' => 201,
                'etag' => Etag::format((int) $member['revision']),
                'location' => '/api/v1/members/' . $member['id'],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function update(Request $request, string $memberId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $memberId): array {
            $context = MemberAdminRuntime::context($request);
            $body = MemberAdminRuntime::body($request);
            $service = $this->service();
            $current = $service->get($context->tenantId, (int) $memberId);
            $member = $service->update(
                $context->tenantId,
                (int) $memberId,
                array_key_exists('display_name', $body)
                    ? ($body['display_name'] === null ? null : (string) $body['display_name'])
                    : (is_string($current['display_name']) ? $current['display_name'] : null),
                array_key_exists('primary_department_id', $body)
                    ? ($body['primary_department_id'] === null ? null : (int) $body['primary_department_id'])
                    : ($current['primary_department_id'] === null ? null : (int) $current['primary_department_id']),
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );

            return ['data' => $member, 'etag' => Etag::format((int) $member['revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceRoles(Request $request, string $memberId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $memberId): array {
            $context = MemberAdminRuntime::context($request);
            $body = MemberAdminRuntime::body($request);
            $rawRoleIds = is_array($body['role_ids'] ?? null) ? $body['role_ids'] : [];
            $member = $this->service()->replaceRoles(
                $context->tenantId,
                (int) $memberId,
                array_values(array_map(static fn(mixed $id): int => (int) $id, $rawRoleIds)),
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );

            return ['data' => $member, 'etag' => Etag::format((int) $member['revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function activate(Request $request, string $memberId): Response
    {
        return $this->transition($request, $memberId, 'activate');
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function suspend(Request $request, string $memberId): Response
    {
        return $this->transition($request, $memberId, 'suspend');
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function leave(Request $request, string $memberId): Response
    {
        return $this->transition($request, $memberId, 'leave');
    }

    private function transition(Request $request, string $memberId, string $operation): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $memberId, $operation): array {
            $context = MemberAdminRuntime::context($request);
            $arguments = [
                $context->tenantId,
                (int) $memberId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                $context->memberId,
                $context->accountId,
                $context->requestId,
            ];
            $service = $this->service();
            $member = match ($operation) {
                'activate' => $service->activate(...$arguments),
                'suspend' => $service->suspend(...$arguments),
                default => $service->leave(...$arguments),
            };

            return ['data' => $member, 'etag' => Etag::format((int) $member['revision'])];
        });
    }

    private function service(): MemberAdminService
    {
        return new MemberAdminService(MemberAdminRuntime::pdo());
    }
}
