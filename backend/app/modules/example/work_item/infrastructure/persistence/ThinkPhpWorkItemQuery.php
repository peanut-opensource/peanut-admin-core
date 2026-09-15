<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\work_item\infrastructure\persistence;

use PeanutAdmin\App\modules\example\target\contracts\TargetQuery;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemPage;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemQuery;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemView;
use PeanutAdmin\App\modules\example\work_item\model\WorkItem;
use PeanutAdmin\DataPermission\Constraint\ThinkPhpQueryConstraintApplier;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use think\db\Query;

final readonly class ThinkPhpWorkItemQuery implements WorkItemQuery
{
    public function __construct(
        private DataPermissionEngine $authorization,
        private TargetQuery $targetQuery,
    ) {}

    public function list(
        TenantContext $context,
        TypedResourceTargetCollection $targets,
        int $page = 1,
        int $pageSize = 20,
        ?string $status = null,
        string $sort = '-created_at',
    ): WorkItemPage {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        if ($status !== null && !in_array($status, ['open', 'active', 'closed'], true)) {
            throw new ModuleException('WORK_ITEM_STATUS_INVALID', 'The work item status filter is invalid.');
        }
        [$field, $direction] = match ($sort) {
            '-created_at' => ['created_at', 'desc'],
            'created_at' => ['created_at', 'asc'],
            'title' => ['title', 'asc'],
            '-title' => ['title', 'desc'],
            default => throw new ModuleException('WORK_ITEM_SORT_INVALID', 'The work item sort is invalid.'),
        };
        $query = $this->authorizedQuery($context, 'list', $targets);
        if ($status !== null) {
            $query->where('status', $status);
        }
        $total = (clone $query)->count();
        $rows = $query->order($field, $direction)->order('id', $direction)->page($page, $pageSize)->select();

        return new WorkItemPage($this->views($context->tenantId, array_values($rows->toArray())), (int) $total, $page, $pageSize);
    }

    public function get(TenantContext $context, string $workItemId): WorkItemView
    {
        $record = $this->authorizedQuery($context, 'list')
            ->where('id', $workItemId)
            ->find();
        if (!$record instanceof WorkItem) {
            throw new ModuleException('AUTHZ_DATA_DENIED', 'The work item does not exist or is not accessible.');
        }

        return $this->views($context->tenantId, [$record->toArray()])[0];
    }

    public function aggregate(TenantContext $context, TypedResourceTargetCollection $targets): array
    {
        $rows = $this->authorizedQuery($context, 'aggregate', $targets)
            ->field('status, COUNT(*) AS aggregate')
            ->group('status')
            ->order('status')
            ->select()
            ->toArray();
        $byStatus = ['open' => 0, 'active' => 0, 'closed' => 0];
        foreach ($rows as $row) {
            $byStatus[(string) $row['status']] = (int) $row['aggregate'];
        }

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }

    private function authorizedQuery(
        TenantContext $context,
        string $operation,
        ?TypedResourceTargetCollection $targets = null,
    ): Query {
        $query = WorkItem::scope(
            'tenant',
            TenantScope::fromTrustedContext($context->tenantId, $context->requestId),
        );
        (new ThinkPhpQueryConstraintApplier())->apply(
            $query,
            $this->authorization->queryConstraint(
                $context,
                'example.work-item',
                $operation,
                $targets ?? new TypedResourceTargetCollection([]),
            ),
        );

        return $query;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<WorkItemView>
     */
    private function views(int $tenantId, array $rows): array
    {
        $labels = [];
        foreach ($this->targetQuery->findMany(
            $tenantId,
            'example.project',
            array_values(array_unique(array_map(static fn(array $row): string => (string) $row['project_id'], $rows))),
        ) as $target) {
            $labels[$target->id] = $target->name;
        }

        $items = [];
        foreach ($rows as $row) {
            $projectId = (string) $row['project_id'];
            if (!isset($labels[$projectId])) {
                throw new ModuleException('AUTHZ_TARGET_NOT_FOUND', 'The WorkItem Project is unavailable.');
            }
            $items[] = new WorkItemView(
                (string) $row['id'],
                (int) $row['tenant_id'],
                $projectId,
                $labels[$projectId],
                $row['queue_id'] === null ? null : (string) $row['queue_id'],
                (string) $row['reference_item_id'],
                (string) $row['title'],
                (string) $row['status'],
                (int) $row['revision'],
            );
        }

        return $items;
    }
}
