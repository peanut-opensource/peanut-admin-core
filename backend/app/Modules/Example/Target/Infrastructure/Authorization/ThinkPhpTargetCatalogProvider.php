<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization;

use PeanutAdmin\App\Modules\Example\Target\Model\Project;
use PeanutAdmin\App\Modules\Example\Target\Model\Queue;
use PeanutAdmin\DataPermission\Catalog\ResourceOperation;
use PeanutAdmin\DataPermission\Context\AuthorizationContext;
use PeanutAdmin\DataPermission\Policy\EffectivePolicySet;
use PeanutAdmin\DataPermission\Target\ResourceTargetCatalogProvider;
use PeanutAdmin\DataPermission\Target\TargetCatalogQuery;
use PeanutAdmin\DataPermission\Target\TargetOptionPage;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use think\facade\Db;

final readonly class ThinkPhpTargetCatalogProvider implements ResourceTargetCatalogProvider
{
    public function searchAllowedTargets(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TargetCatalogQuery $query,
        EffectivePolicySet $policies,
    ): TargetOptionPage {
        $model = match ($query->targetResourceKey) {
            'example.project' => Project::class,
            'example.queue' => Queue::class,
            default => throw new ModuleException('AUTHZ_TARGET_TYPE_MISMATCH', 'Unknown target catalog type.'),
        };
        [$unrestricted, $targetSetIds] = $query->mode === 'policy-config'
            ? [true, []]
            : $this->candidateScope($policies, $query->targetResourceKey);
        if (!$unrestricted && $targetSetIds === []) {
            return new TargetOptionPage([], 0);
        }

        $records = $model::scope(
            'tenant',
            TenantScope::fromTrustedContext($context->tenant->tenantId, 'example-target-catalog'),
        )->where('status', 'active');
        if ($query->search !== '') {
            $records->where(function ($nested) use ($query): void {
                $nested->whereLike('code', '%' . $query->search . '%')
                    ->whereOr('name', 'like', '%' . $query->search . '%');
            });
        }
        if (!$unrestricted) {
            $allowedTargets = Db::name('data_permission_target')
                ->field('target_id')
                ->where('tenant_id', $context->tenant->tenantId)
                ->whereIn('target_set_id', $targetSetIds)
                ->where('status', 'active');
            $records->whereIn('id', $allowedTargets);
        }

        $total = (clone $records)->count();
        $pageSize = min(100, max(1, $query->pageSize));
        $items = [];
        foreach ($records->order('code')->order('id')->page(max(1, $query->page), $pageSize)->select()->toArray() as $record) {
            $items[] = [
                'id' => (string) $record['id'],
                'label' => (string) $record['name'],
            ];
        }

        return new TargetOptionPage($items, (int) $total);
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
                if ($condition->key === 'core.specified_objects'
                    && $condition->targetResourceKey === $targetResourceKey
                    && $condition->targetSetId !== null) {
                    $targetSetIds[] = $condition->targetSetId;
                }
            }
        }
        $targetSetIds = array_values(array_unique($targetSetIds));
        sort($targetSetIds, SORT_NUMERIC);

        return [false, $targetSetIds];
    }
}
