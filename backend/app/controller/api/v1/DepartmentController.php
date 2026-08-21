<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Authorization\Application\Etag;
use PeanutAdmin\Kernel\Organization\Application\DepartmentAdminService;
use think\Request;
use think\Response;

final class DepartmentController
{
    #[OpenApiHandlerContract]
    public function index(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);
            $page = MemberAdminRuntime::page($request);
            $result = $this->service()->list($context->tenantId, $page);

            return [
                'data' => $result['items'],
                'meta' => [
                    'page' => $page->page,
                    'page_size' => $page->pageSize,
                    'total' => $result['total'],
                    'total_pages' => (int) ceil($result['total'] / $page->pageSize),
                ],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function show(Request $request, string $departmentId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $departmentId): array {
            $context = MemberAdminRuntime::context($request);
            $department = $this->service()->get($context->tenantId, (int) $departmentId);

            return ['data' => $department, 'etag' => Etag::format((int) $department['revision'])];
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
            $department = $this->service()->create(
                $context->tenantId,
                (string) ($body['code'] ?? ''),
                (string) ($body['name'] ?? ''),
                isset($body['parent_id']) ? (int) $body['parent_id'] : null,
                (int) ($body['sort_order'] ?? 0),
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );

            return [
                'data' => $department,
                'status' => 201,
                'etag' => Etag::format((int) $department['revision']),
                'location' => '/api/v1/departments/' . $department['id'],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function update(Request $request, string $departmentId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $departmentId): array {
            $context = MemberAdminRuntime::context($request);
            $body = MemberAdminRuntime::body($request);
            $department = $this->service()->update(
                $context->tenantId,
                (int) $departmentId,
                (string) ($body['code'] ?? ''),
                (string) ($body['name'] ?? ''),
                (int) ($body['sort_order'] ?? 0),
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );

            return ['data' => $department, 'etag' => Etag::format((int) $department['revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function move(Request $request, string $departmentId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $departmentId): array {
            $context = MemberAdminRuntime::context($request);
            $body = MemberAdminRuntime::body($request);
            $department = $this->service()->move(
                $context->tenantId,
                (int) $departmentId,
                isset($body['parent_id']) ? (int) $body['parent_id'] : null,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );

            return ['data' => $department, 'etag' => Etag::format((int) $department['revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function archive(Request $request, string $departmentId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $departmentId): array {
            $context = MemberAdminRuntime::context($request);
            $department = $this->service()->archive(
                $context->tenantId,
                (int) $departmentId,
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                $context->memberId,
                $context->accountId,
                $context->requestId,
            );

            return ['data' => $department, 'etag' => Etag::format((int) $department['revision'])];
        });
    }

    private function service(): DepartmentAdminService
    {
        return new DepartmentAdminService(MemberAdminRuntime::pdo());
    }
}
