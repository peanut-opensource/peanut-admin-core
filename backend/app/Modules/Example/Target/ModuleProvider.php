<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target;

use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetQuery;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetRuntimeProvider;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization\ThinkPhpTargetCatalogProvider;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization\ThinkPhpTargetResolver;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization\ProjectPolicyProvider;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization\QueuePolicyProvider;
use PeanutAdmin\App\Modules\Example\Target\Infrastructure\Persistence\ThinkPhpTargetQuery;
use PeanutAdmin\DataPermission\Constraint\ColumnReference;
use PeanutAdmin\DataPermission\Provider\ConditionProviderRegistry;
use PeanutAdmin\DataPermission\Provider\ThinkPhpDepartmentHierarchyProvider;
use PeanutAdmin\DataPermission\Provider\ThinkPhpTargetSetMembershipProvider;
use PeanutAdmin\DataPermission\Provider\ProviderColumnMap;
use PeanutAdmin\DataPermission\Provider\StandardResourcePolicyProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionModuleProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract, DataPermissionModuleProvider, TargetRuntimeProvider
{
    public function moduleKey(): string
    {
        return 'example.target';
    }

    public function bindings(): array
    {
        return [TargetRuntimeProvider::class => self::class];
    }

    public function registerDataPermission(DataPermissionRuntimeRegistry $registry): void
    {
        $departments = new ThinkPhpDepartmentHierarchyProvider();
        $targetSets = new ThinkPhpTargetSetMembershipProvider();
        $project = new ProjectPolicyProvider(new StandardResourcePolicyProvider(
            new ProviderColumnMap(
                new ColumnReference('target.tenant_id'),
                null,
                null,
                ['example.project' => new ColumnReference('target.id')],
            ),
            $departments,
            $targetSets,
            new ConditionProviderRegistry(),
        ));
        $queue = new QueuePolicyProvider(new StandardResourcePolicyProvider(
            new ProviderColumnMap(
                new ColumnReference('target.tenant_id'),
                null,
                null,
                ['example.queue' => new ColumnReference('target.id')],
            ),
            $departments,
            $targetSets,
            new ConditionProviderRegistry(),
        ));
        $registry->registerResourceProvider(ProjectPolicyProvider::class, $project);
        $registry->registerResourceProvider(QueuePolicyProvider::class, $queue);
        $registry->registerTargetResolver(ThinkPhpTargetResolver::class, new ThinkPhpTargetResolver());
        $registry->registerTargetCatalogProvider(
            ThinkPhpTargetCatalogProvider::class,
            new ThinkPhpTargetCatalogProvider(),
        );
    }

    public function targetQuery(): TargetQuery
    {
        return new ThinkPhpTargetQuery();
    }
}
