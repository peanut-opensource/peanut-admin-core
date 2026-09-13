<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\authorization\DataPermissionRuntimeFactory;
use PeanutAdmin\DataPermission\Application\DataPolicyAdminService;
use PeanutAdmin\DataPermission\Application\EffectiveAccessPreviewService;
use PeanutAdmin\DataPermission\Catalog\ResourceOperationStore;
use PeanutAdmin\DataPermission\Policy\PolicyStore;
use PeanutAdmin\DataPermission\Target\TargetCatalogQuery;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\Etag;
use PeanutAdmin\Kernel\Authorization\PdoTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use PeanutAdmin\Kernel\Persistence\ThinkPhp\ThinkPhpTransactionManager;
use RuntimeException;
use think\Request;
use think\Response;
use think\db\PDOConnection;
use think\facade\Db;

final class DataAuthorizationController
{
    #[OpenApiHandlerContract]
    public function effectiveAccess(Request $request, string $memberId): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request, $memberId): array {
            $validatedMemberId = self::memberId($memberId);
            $context = MemberAdminRuntime::context($request);
            $connection = self::connection();
            $pdo = $connection->connect();
            $result = (new EffectiveAccessPreviewService(
                new ThinkPhpTransactionManager($connection),
                new PdoTenantAuthorizationRepository($pdo),
                new ResourceOperationStore($connection),
                new PolicyStore($connection),
                new PdoAuditRepository($pdo),
            ))->preview($context, $validatedMemberId, MemberAdminRuntime::page($request));

            return ['data' => $result['data'], 'meta' => $result['meta']];
        });
    }

    #[OpenApiHandlerContract]
    public function targetCandidates(Request $request): Response
    {
        return MemberAdminRuntime::run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);
            $resourceKey = (string) $request->get('resource_key', '');
            $operation = (string) $request->get('operation', '');
            $targetResourceKey = (string) $request->get('target_resource_key', '');
            $targetRole = (string) $request->get('target_role', 'primary');
            $mode = (string) $request->get('mode', 'runtime');
            $search = (string) $request->get('q', '');
            if (mb_strlen($search) > 100) {
                throw AdminAccessException::invalid('SEARCH_INVALID', 'Search text is limited to 100 characters.');
            }
            if ($resourceKey === '' || $operation === '' || $targetResourceKey === '' || $targetRole === '') {
                throw AdminAccessException::invalid(
                    'TARGET_CATALOG_QUERY_INVALID',
                    'Resource, operation, target type, and target role are required.',
                );
            }
            $page = MemberAdminRuntime::page($request);
            $connection = self::connection();
            $runtime = DataPermissionRuntimeFactory::runtime($connection);
            $result = DataPermissionRuntimeFactory::create($connection, null, $runtime)->searchAllowedTargets(
                $context,
                $resourceKey,
                $operation,
                new TargetCatalogQuery(
                    $targetResourceKey,
                    $search,
                    $page->page,
                    $page->pageSize,
                    $targetRole,
                    $mode,
                ),
            );
            $service = new DataPolicyAdminService(
                $connection,
                new ThinkPhpTransactionManager($connection),
                $runtime->targetResolvers,
            );

            return [
                'data' => array_map(
                    static fn(array $item): array => [
                        'target_resource_key' => $targetResourceKey,
                        'target_role' => $targetRole,
                        'target_id' => $item['id'],
                        'label' => $item['label'],
                    ],
                    $result->items,
                ),
                'meta' => [
                    'page' => $page->page,
                    'page_size' => $page->pageSize,
                    'total' => $result->total,
                    'total_pages' => (int) ceil($result->total / $page->pageSize),
                    'target_cardinality' => $service->targetCardinality(
                        $context->tenantId,
                        $resourceKey,
                        $operation,
                    ),
                    'available_count' => $result->total,
                ],
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function getRolePolicy(
        Request $request,
        string $roleId,
        string $resourceKey,
        string $operation,
    ): Response {
        return MemberAdminRuntime::run($request, function () use (
            $request,
            $roleId,
            $resourceKey,
            $operation,
        ): array {
            $context = MemberAdminRuntime::context($request);
            $policy = $this->service()->get(
                $context->tenantId,
                (int) $roleId,
                $resourceKey,
                $operation,
            );

            return ['data' => $policy, 'etag' => Etag::format((int) $policy['revision'])];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceRolePolicy(
        Request $request,
        string $roleId,
        string $resourceKey,
        string $operation,
    ): Response {
        return MemberAdminRuntime::run($request, function () use (
            $request,
            $roleId,
            $resourceKey,
            $operation,
        ): array {
            $context = MemberAdminRuntime::context($request);
            $ifMatch = MemberAdminRuntime::header($request, 'if-match');
            $policy = $this->service()->replace(
                $context,
                (int) $roleId,
                $resourceKey,
                $operation,
                MemberAdminRuntime::body($request),
                $ifMatch === null || $ifMatch === '' ? null : Etag::parse($ifMatch),
            );

            return ['data' => $policy, 'etag' => Etag::format((int) $policy['revision'])];
        });
    }

    private function service(): DataPolicyAdminService
    {
        $connection = self::connection();

        return new DataPolicyAdminService(
            $connection,
            new ThinkPhpTransactionManager($connection),
            DataPermissionRuntimeFactory::runtime($connection)->targetResolvers,
        );
    }

    private static function connection(): PDOConnection
    {
        $connection = Db::connect();
        if (!$connection instanceof PDOConnection) {
            throw new RuntimeException('DATA_PERMISSION_DATABASE_CONNECTION_UNSUPPORTED');
        }

        return $connection;
    }

    private static function memberId(string $value): int
    {
        $maximum = (string) PHP_INT_MAX;
        if (
            preg_match('/^[1-9][0-9]*$/', $value) !== 1
            || strlen($value) > strlen($maximum)
            || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)
        ) {
            throw AdminAccessException::invalid(
                'MEMBER_ID_INVALID',
                'Member ID must be a canonical positive integer within the supported range.',
            );
        }

        return (int) $value;
    }
}
