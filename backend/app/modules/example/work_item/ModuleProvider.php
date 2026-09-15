<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\work_item;

use PeanutAdmin\App\modules\example\target\contracts\TargetQuery;
use PeanutAdmin\App\modules\example\work_item\services\WorkItemCommandService;
use PeanutAdmin\App\modules\example\work_item\services\WorkItemPolicyPublisher;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemCommands;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemPolicyPublication;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemQuery;
use PeanutAdmin\App\modules\example\work_item\contracts\WorkItemRuntimeProvider;
use PeanutAdmin\App\modules\example\work_item\infrastructure\authorization\WorkItemPolicyProvider;
use PeanutAdmin\App\modules\example\work_item\infrastructure\persistence\ThinkPhpWorkItemQuery;
use PeanutAdmin\DataPermission\Constraint\ColumnReference;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Provider\ConditionProviderRegistry;
use PeanutAdmin\DataPermission\Provider\ThinkPhpDepartmentHierarchyProvider;
use PeanutAdmin\DataPermission\Provider\ThinkPhpTargetSetMembershipProvider;
use PeanutAdmin\DataPermission\Provider\ProviderColumnMap;
use PeanutAdmin\DataPermission\Provider\StandardResourcePolicyProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionModuleProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;
use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract, DataPermissionModuleProvider, WorkItemRuntimeProvider
{
    public function moduleKey(): string
    {
        return 'example.work-item';
    }

    public function bindings(): array
    {
        return [WorkItemRuntimeProvider::class => self::class];
    }

    public function registerDataPermission(DataPermissionRuntimeRegistry $registry): void
    {
        $provider = new WorkItemPolicyProvider(new StandardResourcePolicyProvider(
            new ProviderColumnMap(
                new ColumnReference('tenant_id'),
                new ColumnReference('owner_member_id'),
                new ColumnReference('department_id'),
                [
                    'example.project' => new ColumnReference('project_id'),
                    'example.queue' => new ColumnReference('queue_id'),
                ],
            ),
            new ThinkPhpDepartmentHierarchyProvider(),
            new ThinkPhpTargetSetMembershipProvider(),
            new ConditionProviderRegistry(),
        ));
        $registry->registerResourceProvider(WorkItemPolicyProvider::class, $provider);
    }

    public function workItemQuery(
        DataPermissionEngine $authorization,
        TargetQuery $targets,
    ): WorkItemQuery {
        return new ThinkPhpWorkItemQuery($authorization, $targets);
    }

    public function workItemCommands(
        DataPermissionEngine $authorization,
        AuditService $audit,
        MemberAdminService $members,
    ): WorkItemCommands {
        return new WorkItemCommandService($authorization, $audit, $members);
    }

    public function workItemPolicyPublication(
        DataPermissionEngine $authorization,
        AuditService $audit,
    ): WorkItemPolicyPublication {
        return new WorkItemPolicyPublisher($authorization, $audit);
    }
}
