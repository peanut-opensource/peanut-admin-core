<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Contracts;

use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetQuery;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;

interface WorkItemRuntimeProvider
{
    public function workItemQuery(
        DataPermissionEngine $authorization,
        TargetQuery $targets,
    ): WorkItemQuery;

    public function workItemCommands(
        DataPermissionEngine $authorization,
        AuditService $audit,
        MemberAdminService $members,
    ): WorkItemCommands;

    public function workItemPolicyPublication(
        DataPermissionEngine $authorization,
        AuditService $audit,
    ): WorkItemPolicyPublication;
}
