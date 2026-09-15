<?php
declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\work_item\model;

use PeanutAdmin\Kernel\persistence\model\TenantModel;

final class WorkItem extends TenantModel
{
    /** @var string */
    protected $name = 'example_work_item';
}
