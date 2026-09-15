<?php
declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Model;

use PeanutAdmin\Kernel\Persistence\Model\TenantModel;

final class WorkItemPolicyPublication extends TenantModel
{
    /** @var string */
    protected $name = 'example_work_item_policy_publication';
}
