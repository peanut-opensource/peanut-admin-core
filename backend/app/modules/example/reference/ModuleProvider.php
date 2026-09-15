<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\reference;

use PeanutAdmin\App\modules\example\reference\contracts\ReferenceQuery;
use PeanutAdmin\App\modules\example\reference\contracts\ReferenceRuntimeProvider;
use PeanutAdmin\App\modules\example\reference\infrastructure\authorization\ThinkPhpReferenceScopeProvider;
use PeanutAdmin\App\modules\example\reference\infrastructure\authorization\ReferencePolicyProvider;
use PeanutAdmin\App\modules\example\reference\infrastructure\persistence\ThinkPhpReferenceQuery;
use PeanutAdmin\DataPermission\Constraint\ColumnReference;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Provider\ConditionProviderRegistry;
use PeanutAdmin\DataPermission\Provider\ThinkPhpDepartmentHierarchyProvider;
use PeanutAdmin\DataPermission\Provider\ThinkPhpTargetSetMembershipProvider;
use PeanutAdmin\DataPermission\Provider\ProviderColumnMap;
use PeanutAdmin\DataPermission\Provider\StandardResourcePolicyProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionModuleProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract, DataPermissionModuleProvider, ReferenceRuntimeProvider
{
    public function moduleKey(): string
    {
        return 'example.reference';
    }

    public function bindings(): array
    {
        return [ReferenceRuntimeProvider::class => self::class];
    }

    public function registerDataPermission(DataPermissionRuntimeRegistry $registry): void
    {
        $provider = new ReferencePolicyProvider(new StandardResourcePolicyProvider(
            new ProviderColumnMap(
                new ColumnReference('item.owner_tenant_id'),
                null,
                null,
                [],
            ),
            new ThinkPhpDepartmentHierarchyProvider(),
            new ThinkPhpTargetSetMembershipProvider(),
            new ConditionProviderRegistry(),
        ));
        $scope = new ThinkPhpReferenceScopeProvider();
        $registry->registerResourceProvider(ReferencePolicyProvider::class, $provider);
        $registry->registerSharedMasterProvider('example.reference-item', $scope);
    }

    public function referenceQuery(DataPermissionEngine $authorization): ReferenceQuery
    {
        return new ThinkPhpReferenceQuery($authorization);
    }
}
