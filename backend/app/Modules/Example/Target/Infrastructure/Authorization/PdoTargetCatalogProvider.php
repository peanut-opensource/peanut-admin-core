<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization;

use PDO;
use PeanutAdmin\DataPermission\Catalog\ResourceOperation;
use PeanutAdmin\DataPermission\Constraint\ColumnReference;
use PeanutAdmin\DataPermission\Constraint\ExistsByContract;
use PeanutAdmin\DataPermission\Constraint\OrConstraint;
use PeanutAdmin\DataPermission\Constraint\PdoQueryConstraintCompiler;
use PeanutAdmin\DataPermission\Context\AuthorizationContext;
use PeanutAdmin\DataPermission\Policy\EffectivePolicySet;
use PeanutAdmin\DataPermission\Target\ResourceTargetCatalogProvider;
use PeanutAdmin\DataPermission\Target\TargetCatalogQuery;
use PeanutAdmin\DataPermission\Target\TargetOptionPage;
use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class PdoTargetCatalogProvider implements ResourceTargetCatalogProvider
{
    public function __construct(private PDO $pdo) {}

    public function searchAllowedTargets(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TargetCatalogQuery $query,
        EffectivePolicySet $policies,
    ): TargetOptionPage {
        $table = match ($query->targetResourceKey) {
            'example.project' => 'pa_example_project',
            'example.queue' => 'pa_example_queue',
            default => throw new ModuleException('AUTHZ_TARGET_TYPE_MISMATCH', 'Unknown target catalog type.'),
        };
        [$unrestricted, $targetSetIds] = $query->mode === 'policy-config'
            ? [true, []]
            : $this->candidateScope($policies, $query->targetResourceKey);
        if (!$unrestricted && $targetSetIds === []) {
            return new TargetOptionPage([], 0);
        }
        $search = '%' . $query->search . '%';
        $scopeSql = '';
        $parameters = [
            'tenant_id' => $context->tenant->tenantId,
            'search_code' => $search,
            'search_name' => $search,
        ];
        if (!$unrestricted) {
            $constraints = array_map(
                static fn(int $targetSetId): ExistsByContract => new ExistsByContract(
                    'data_permission.target-set',
                    new ColumnReference('target.id'),
                    $context->tenant->tenantId,
                    $targetSetId,
                ),
                $targetSetIds,
            );
            $scope = (new PdoQueryConstraintCompiler())->compile(
                count($constraints) === 1 ? $constraints[0] : new OrConstraint($constraints),
            );
            $scopeSql = '  AND ' . $scope->sql;
            $parameters = [...$parameters, ...$scope->parameters];
        }
        $where = <<<SQL
WHERE target.tenant_id = :tenant_id AND target.status = 'active'
  AND (target.code LIKE :search_code OR target.name LIKE :search_name)
{$scopeSql}
SQL;
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} target\n{$where}");
        $count->execute($parameters);
        $pageSize = min(100, max(1, $query->pageSize));
        $offset = max(0, ($query->page - 1) * $pageSize);
        $list = $this->pdo->prepare(<<<SQL
SELECT target.id, target.name
FROM {$table} target
{$where}
ORDER BY target.code, target.id
LIMIT {$pageSize} OFFSET {$offset}
SQL);
        $list->execute($parameters);

        return new TargetOptionPage(
            array_values(array_map(
                static fn(array $row): array => ['id' => (string) $row['id'], 'label' => (string) $row['name']],
                $list->fetchAll(PDO::FETCH_ASSOC),
            )),
            (int) $count->fetchColumn(),
        );
    }

    /** @return array{bool, list<int>} */
    private function candidateScope(EffectivePolicySet $policies, string $targetResourceKey): array
    {
        $targetSetIds = [];
        foreach ($policies->groups as $group) {
            foreach ($group->conditions as $condition) {
                if ($condition->key === 'core.tenant_all') {
                    return [true, []];
                }
                if (
                    $condition->key === 'core.specified_objects'
                    && $condition->targetResourceKey === $targetResourceKey
                ) {
                    if ($condition->targetSetId !== null) {
                        $targetSetIds[] = $condition->targetSetId;
                    }
                }
            }
        }

        $targetSetIds = array_values(array_unique($targetSetIds));
        sort($targetSetIds, SORT_NUMERIC);

        return [false, $targetSetIds];
    }
}
