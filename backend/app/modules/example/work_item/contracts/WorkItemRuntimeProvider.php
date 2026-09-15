<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\work_item\contracts;

use PeanutAdmin\App\modules\example\target\contracts\TargetQuery;
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
