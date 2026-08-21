<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem;

use PDO;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetQuery;
use PeanutAdmin\App\Modules\Example\WorkItem\Application\WorkItemCommandService;
use PeanutAdmin\App\Modules\Example\WorkItem\Application\WorkItemPolicyPublisher;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemCommands;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemPolicyPublication;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemQuery;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemRuntimeProvider;
use PeanutAdmin\App\Modules\Example\WorkItem\Infrastructure\Authorization\WorkItemPolicyProvider;
use PeanutAdmin\App\Modules\Example\WorkItem\Infrastructure\Persistence\PdoWorkItemQuery;
use PeanutAdmin\DataPermission\Constraint\ColumnReference;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Provider\ConditionProviderRegistry;
use PeanutAdmin\DataPermission\Provider\PdoDepartmentHierarchyProvider;
use PeanutAdmin\DataPermission\Provider\PdoTargetSetMembershipProvider;
use PeanutAdmin\DataPermission\Provider\ProviderColumnMap;
use PeanutAdmin\DataPermission\Provider\StandardResourcePolicyProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionModuleProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;
use PeanutAdmin\Kernel\Audit\AuditRepository;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;
use PeanutAdmin\Kernel\Module\ModuleProvider as ModuleProviderContract;

final class ModuleProvider implements ModuleProviderContract, DataPermissionModuleProvider, WorkItemRuntimeProvider
{
    public function moduleKey(): string
    {
        return 'example.work-item';
    }

    public function registerDataPermission(DataPermissionRuntimeRegistry $registry, PDO $pdo): void
    {
        $provider = new WorkItemPolicyProvider(new StandardResourcePolicyProvider(
            new ProviderColumnMap(
                new ColumnReference('work_item.tenant_id'),
                new ColumnReference('work_item.owner_member_id'),
                new ColumnReference('work_item.department_id'),
                [
                    'example.project' => new ColumnReference('work_item.project_id'),
                    'example.queue' => new ColumnReference('work_item.queue_id'),
                ],
            ),
            new PdoDepartmentHierarchyProvider($pdo),
            new PdoTargetSetMembershipProvider($pdo),
            new ConditionProviderRegistry(),
        ));
        $registry->registerResourceProvider(WorkItemPolicyProvider::class, $provider);
    }

    public function workItemQuery(
        PDO $pdo,
        DataPermissionEngine $authorization,
        TargetQuery $targets,
    ): WorkItemQuery {
        return new PdoWorkItemQuery($pdo, $authorization, $targets);
    }

    public function workItemCommands(
        PDO $pdo,
        DataPermissionEngine $authorization,
        AuditRepository $audit,
        MemberAdminService $members,
    ): WorkItemCommands {
        return new WorkItemCommandService($pdo, $authorization, $audit, $members);
    }

    public function workItemPolicyPublication(
        PDO $pdo,
        DataPermissionEngine $authorization,
        AuditRepository $audit,
    ): WorkItemPolicyPublication {
        return new WorkItemPolicyPublisher($pdo, $authorization, $audit);
    }
}
