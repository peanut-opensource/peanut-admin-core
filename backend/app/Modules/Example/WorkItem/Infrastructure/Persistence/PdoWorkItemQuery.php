<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Infrastructure\Persistence;

use PDO;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetQuery;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemPage;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemQuery;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemView;
use PeanutAdmin\DataPermission\Constraint\PdoQueryConstraintCompiler;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class PdoWorkItemQuery implements WorkItemQuery
{
    public function __construct(
        private PDO $pdo,
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
        $offset = ($page - 1) * $pageSize;
        $constraint = (new PdoQueryConstraintCompiler())->compile(
            $this->authorization->queryConstraint($context, 'example.work-item', 'list', $targets),
        );
        if ($status !== null && !in_array($status, ['open', 'active', 'closed'], true)) {
            throw new ModuleException('WORK_ITEM_STATUS_INVALID', 'The work item status filter is invalid.');
        }
        $orderBy = match ($sort) {
            '-created_at' => 'work_item.created_at DESC, work_item.id DESC',
            'created_at' => 'work_item.created_at, work_item.id',
            'title' => 'work_item.title, work_item.id',
            '-title' => 'work_item.title DESC, work_item.id DESC',
            default => throw new ModuleException('WORK_ITEM_SORT_INVALID', 'The work item sort is invalid.'),
        };
        $where = $constraint->sql . ($status === null ? '' : ' AND work_item.status = :status_filter');
        $parameters = $constraint->parameters;
        if ($status !== null) {
            $parameters['status_filter'] = $status;
        }
        $count = $this->pdo->prepare(<<<SQL
SELECT COUNT(*)
FROM pa_example_work_item work_item
WHERE {$where}
SQL);
        $count->execute($parameters);
        $statement = $this->pdo->prepare(<<<SQL
SELECT work_item.id, work_item.tenant_id, work_item.project_id, work_item.queue_id,
       work_item.reference_item_id, work_item.title, work_item.status, work_item.revision
FROM pa_example_work_item work_item
WHERE {$where}
ORDER BY {$orderBy}
LIMIT {$pageSize} OFFSET {$offset}
SQL);
        $statement->execute($parameters);
        $items = $this->views($context->tenantId, $this->rows($statement));

        return new WorkItemPage($items, (int) $count->fetchColumn(), $page, $pageSize);
    }

    public function get(TenantContext $context, string $workItemId): WorkItemView
    {
        $constraint = (new PdoQueryConstraintCompiler())->compile(
            $this->authorization->queryConstraint($context, 'example.work-item', 'list'),
        );
        $statement = $this->pdo->prepare(<<<SQL
SELECT work_item.id, work_item.tenant_id, work_item.project_id, work_item.queue_id,
       work_item.reference_item_id, work_item.title, work_item.status, work_item.revision
FROM pa_example_work_item work_item
WHERE work_item.tenant_id = :detail_tenant_id
  AND work_item.id = :work_item_id
  AND {$constraint->sql}
SQL);
        $statement->execute([
            'detail_tenant_id' => $context->tenantId,
            'work_item_id' => $workItemId,
            ...$constraint->parameters,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ModuleException('AUTHZ_DATA_DENIED', 'The work item does not exist or is not accessible.');
        }

        return $this->views($context->tenantId, [$row])[0];
    }

    /** @return array{total: int, by_status: array<string, int>} */
    public function aggregate(TenantContext $context, TypedResourceTargetCollection $targets): array
    {
        $constraint = (new PdoQueryConstraintCompiler())->compile(
            $this->authorization->queryConstraint($context, 'example.work-item', 'aggregate', $targets),
        );
        $statement = $this->pdo->prepare(<<<SQL
SELECT work_item.status, COUNT(*) AS aggregate
FROM pa_example_work_item work_item
WHERE {$constraint->sql}
GROUP BY work_item.status
ORDER BY work_item.status
SQL);
        $statement->execute($constraint->parameters);
        $byStatus = ['open' => 0, 'active' => 0, 'closed' => 0];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $byStatus[(string) $row['status']] = (int) $row['aggregate'];
        }

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }

    /**
     * @param list<array<string, mixed>> $rows
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

    /** @return list<array<string, mixed>> */
    private function rows(\PDOStatement $statement): array
    {
        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }
}
