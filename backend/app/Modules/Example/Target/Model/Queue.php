<?php
declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Model;

use PeanutAdmin\Kernel\Persistence\Model\TenantModel;

final class Queue extends TenantModel
{
    /** @var string */
    protected $name = 'example_queue';
}
