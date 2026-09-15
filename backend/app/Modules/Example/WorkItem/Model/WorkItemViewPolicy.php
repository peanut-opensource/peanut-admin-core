<?php
declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Model;

use PeanutAdmin\Kernel\Persistence\Model\TenantModel;

final class WorkItemViewPolicy extends TenantModel
{
    /** @var string */
    protected $name = 'example_work_item_view_policy';
    /** @var list<string> */
    protected $json = ['config_json'];
    /** @var bool */
    protected $jsonAssoc = true;
}
