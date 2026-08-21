<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PDO;
use PeanutAdmin\App\authorization\DataPermissionRuntimeFactory;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\App\Modules\Example\Reference\Contracts\ReferenceRuntimeProvider;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetRuntimeProvider;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\CreateWorkItem;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemCommands;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemQuery;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemRuntimeProvider;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemView;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Exception\DataAuthorizationException;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Authorization\Application\Etag;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Persistence\Pdo\PdoAuditRepository;
use think\Request;
use think\Response;

final class ExampleController
{
    #[OpenApiHandlerContract]
    public function listWorkItems(Request $request): Response
    {
        return $this->run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);
            $page = MemberAdminRuntime::page($request);
            $targets = ExampleHttpRuntime::queryTargets($request);
            $status = $request->get('status');
            $sort = $request->get('sort', '-created_at');
            if ($status !== null && !is_string($status)) {
                throw AdminAccessException::invalid(
                    'WORK_ITEM_STATUS_INVALID',
                    'Work item status is invalid.',
                );
            }
            if (!is_string($sort)) {
                throw AdminAccessException::invalid(
                    'WORK_ITEM_SORT_INVALID',
                    'Work item sort is invalid.',
                );
            }
            $result = $this->workItems()->list(
                $context,
                $targets,
                $page->page,
                $page->pageSize,
                $status,
                $sort,
            );

            return [
                'data' => array_map($this->workItemView(...), $result->items),
                'meta' => [
                    'page' => $result->page,
                    'page_size' => $result->pageSize,
                    'total' => $result->total,
                    'total_pages' => (int) ceil($result->total / $result->pageSize),
                    'target_scope' => $this->targetScope($targets, 'read'),
                ],
            ];
        });
    }

    #[OpenApiHandlerContract(
        successStatus: 201,
        headers: OpenApiHandlerContract::CREATED_HEADERS,
    )]
    public function createWorkItem(Request $request): Response
    {
        return $this->run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);
            $body = MemberAdminRuntime::body($request);
            ExampleHttpRuntime::onlyKeys($body, ['target', 'related_target', 'reference_item_id', 'title']);
            $targets = ExampleHttpRuntime::bodyTargets($body);
            $primary = $targets->sets[0];
            $related = $targets->sets[1] ?? null;
            $id = $this->commands()->create(
                $context,
                $targets,
                new CreateWorkItem(
                    $primary->targetIds[0],
                    $related?->targetIds[0],
                    ExampleHttpRuntime::bigint(
                        ExampleHttpRuntime::requiredString($body, 'reference_item_id'),
                        'reference_item_id',
                    ),
                    ExampleHttpRuntime::requiredString($body, 'title'),
                ),
            );

            return [
                'data' => ['id' => $id, 'status' => 'open', 'revision' => '1'],
                'status' => 201,
                'etag' => Etag::format(1),
                'location' => '/api/v1/example/work-items/' . $id,
            ];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function getWorkItem(Request $request, string $workItemId): Response
    {
        return $this->run($request, function () use ($request, $workItemId): array {
            $item = $this->workItems()->get(
                MemberAdminRuntime::context($request),
                ExampleHttpRuntime::bigint($workItemId, 'work_item_id'),
            );

            return ['data' => $this->workItemView($item), 'etag' => Etag::format($item->revision)];
        });
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function updateWorkItem(Request $request, string $workItemId): Response
    {
        return $this->run($request, function () use ($request, $workItemId): array {
            $body = MemberAdminRuntime::body($request);
            ExampleHttpRuntime::onlyKeys($body, ['target', 'related_target', 'title', 'status']);
            $result = $this->commands()->update(
                MemberAdminRuntime::context($request),
                ExampleHttpRuntime::bigint($workItemId, 'work_item_id'),
                Etag::parse(MemberAdminRuntime::header($request, 'if-match')),
                ExampleHttpRuntime::bodyTargets($body),
                ExampleHttpRuntime::optionalString($body, 'title'),
                ExampleHttpRuntime::optionalString($body, 'status'),
            );

            return [
                'data' => ['id' => $result['id'], 'revision' => (string) $result['revision']],
                'etag' => Etag::format($result['revision']),
            ];
        });
    }

    #[OpenApiHandlerContract]
    public function aggregateWorkItems(Request $request): Response
    {
        return $this->run($request, function () use ($request): array {
            $targets = ExampleHttpRuntime::queryTargets($request);
            $result = $this->workItems()->aggregate(MemberAdminRuntime::context($request), $targets);

            return [
                'data' => $result,
                'meta' => ['target_scope' => $this->targetScope($targets, 'aggregate')],
            ];
        });
    }

    #[OpenApiHandlerContract]
    public function referenceCandidates(Request $request): Response
    {
        return $this->run($request, function () use ($request): array {
            $search = $request->get('q', '');
            if (!is_string($search)) {
                throw AdminAccessException::invalid(
                    'REFERENCE_SEARCH_INVALID',
                    'Reference search is invalid.',
                );
            }
            $pdo = MemberAdminRuntime::pdo();
            $items = $this->referenceProvider()->referenceQuery($pdo, $this->authorization($pdo))->candidates(
                MemberAdminRuntime::context($request),
                ExampleHttpRuntime::queryTargets($request, 1),
                'use',
                $search,
            );

            return [
                'data' => array_map(static fn($item): array => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'owner_type' => $item->ownerType,
                    'owner_tenant_id' => $item->ownerTenantId === null ? null : (string) $item->ownerTenantId,
                    'status' => 'active',
                ], $items),
                'meta' => ['total' => count($items)],
            ];
        });
    }

    #[OpenApiHandlerContract(
        successStatus: 201,
        headers: OpenApiHandlerContract::CREATED_HEADERS,
    )]
    public function publishWorkItemPolicy(Request $request): Response
    {
        return $this->run($request, function () use ($request): array {
            $context = MemberAdminRuntime::context($request);
            $body = MemberAdminRuntime::body($request);
            ExampleHttpRuntime::onlyKeys($body, ['name', 'config', 'targets']);
            $config = $body['config'] ?? null;
            if (!is_array($config)) {
                throw AdminAccessException::invalid(
                    'WORK_ITEM_POLICY_CONFIG_INVALID',
                    'Policy config must be an object.',
                );
            }
            $targets = ExampleHttpRuntime::policyTargets($body);
            $pdo = MemberAdminRuntime::pdo();
            $policyId = $this->workItemProvider()->workItemPolicyPublication(
                $pdo,
                $this->authorization($pdo),
                new PdoAuditRepository($pdo),
            )->publish(
                $context,
                $targets,
                ExampleHttpRuntime::requiredString($body, 'name'),
                $config,
            );
            $targetQuery = $this->targetProvider()->targetQuery($pdo);
            $labels = [];
            foreach ($targetQuery->findMany($context->tenantId, 'example.project', $targets->sets[0]->targetIds) as $target) {
                $labels[$target->id] = $target->name;
            }
            $publications = array_map(static fn(string $targetId): array => [
                'policy_id' => $policyId,
                'target_id' => $targetId,
                'target_label' => $labels[$targetId] ?? $targetId,
                'status' => 'published',
                'message' => 'Applied',
            ], $targets->sets[0]->targetIds);

            return [
                'data' => $publications,
                'meta' => ['policy_id' => $policyId, 'revision' => '1'],
                'status' => 201,
                'etag' => Etag::format(1),
                'location' => '/api/v1/example/work-item-view-policies/' . $policyId,
            ];
        });
    }

    /**
     * @param callable(): array{
     *   data: mixed,
     *   status?: int,
     *   etag?: string,
     *   location?: string,
     *   meta?: array<string, mixed>
     * } $operation
     */
    private function run(Request $request, callable $operation): Response
    {
        return MemberAdminRuntime::run($request, static function () use ($operation): array {
            try {
                return $operation();
            } catch (ModuleException|DataAuthorizationException $exception) {
                ExampleHttpRuntime::translate($exception);
            }
        });
    }

    private function workItems(): WorkItemQuery
    {
        $pdo = MemberAdminRuntime::pdo();

        return $this->workItemProvider()->workItemQuery(
            $pdo,
            $this->authorization($pdo),
            $this->targetProvider()->targetQuery($pdo),
        );
    }

    private function commands(): WorkItemCommands
    {
        $pdo = MemberAdminRuntime::pdo();

        return $this->workItemProvider()->workItemCommands(
            $pdo,
            $this->authorization($pdo),
            new PdoAuditRepository($pdo),
            new MemberAdminService($pdo),
        );
    }

    private function targetProvider(): TargetRuntimeProvider
    {
        /** @var TargetRuntimeProvider */
        return $this->moduleProvider('example.target', TargetRuntimeProvider::class);
    }

    private function referenceProvider(): ReferenceRuntimeProvider
    {
        /** @var ReferenceRuntimeProvider */
        return $this->moduleProvider('example.reference', ReferenceRuntimeProvider::class);
    }

    private function workItemProvider(): WorkItemRuntimeProvider
    {
        /** @var WorkItemRuntimeProvider */
        return $this->moduleProvider('example.work-item', WorkItemRuntimeProvider::class);
    }

    /** @param class-string $contract */
    private function moduleProvider(string $moduleKey, string $contract): object
    {
        /** @var array<string, object>|null $providers */
        static $providers = null;
        if ($providers === null) {
            $providers = [];
            foreach (RuntimeModuleRegistry::compile(dirname(__DIR__, 5))->modules as $module) {
                $key = $module->data['key'] ?? null;
                $backend = $module->data['backend'] ?? null;
                $providerClass = is_array($backend) ? ($backend['provider'] ?? null) : null;
                if (!is_string($key) || !is_string($providerClass) || !class_exists($providerClass)) {
                    continue;
                }
                $providers[$key] = new $providerClass();
            }
        }
        $provider = $providers[$moduleKey] ?? null;
        if ($provider instanceof $contract) {
            return $provider;
        }

        throw new ModuleException('MODULE_CONTRACT_MISSING', "Module {$moduleKey} runtime contract is unavailable.");
    }

    private function authorization(PDO $pdo): DataPermissionEngine
    {
        return DataPermissionRuntimeFactory::create($pdo);
    }

    /** @return array<string, mixed> */
    private function workItemView(WorkItemView $item): array
    {
        return [
            'id' => $item->id,
            'project_id' => $item->projectId,
            'queue_id' => $item->queueId,
            'reference_item_id' => $item->referenceItemId,
            'title' => $item->title,
            'status' => $item->status,
            'revision' => (string) $item->revision,
            'boundary_target' => [
                'target_resource_key' => 'example.project',
                'target_role' => 'primary',
                'target_id' => $item->projectId,
                'label' => $item->projectLabel,
            ],
        ];
    }

    /** @return array{mode: string, target_count: int, digest: string} */
    private function targetScope(TypedResourceTargetCollection $targets, string $mode): array
    {
        $values = [];
        foreach ($targets->sets as $set) {
            foreach ($set->targetIds as $id) {
                $values[] = $set->targetRole . ':' . $set->targetResourceKey . ':' . $id;
            }
        }
        sort($values, SORT_STRING);
        $count = count($values);

        return [
            'mode' => $mode === 'aggregate' ? 'aggregate' : ($count === 1 ? 'single' : 'multiple'),
            'target_count' => $count,
            'digest' => hash('sha256', implode("\n", $values)),
        ];
    }
}
