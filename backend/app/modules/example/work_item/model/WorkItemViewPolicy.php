<?php
declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\work_item\model;

use PeanutAdmin\Kernel\persistence\model\TenantModel;

final class WorkItemViewPolicy extends TenantModel
{
    /** @var string */
    protected $name = 'example_work_item_view_policy';
    /** @var list<string> */
    protected $json = ['config_json'];
    /** @var bool */
    protected $jsonAssoc = true;
}
