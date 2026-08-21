<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Contracts;

use PDO;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetQuery;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\Kernel\Audit\AuditRepository;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;

interface WorkItemRuntimeProvider
{
    public function workItemQuery(
        PDO $pdo,
        DataPermissionEngine $authorization,
        TargetQuery $targets,
    ): WorkItemQuery;

    public function workItemCommands(
        PDO $pdo,
        DataPermissionEngine $authorization,
        AuditRepository $audit,
        MemberAdminService $members,
    ): WorkItemCommands;

    public function workItemPolicyPublication(
        PDO $pdo,
        DataPermissionEngine $authorization,
        AuditRepository $audit,
    ): WorkItemPolicyPublication;
}
