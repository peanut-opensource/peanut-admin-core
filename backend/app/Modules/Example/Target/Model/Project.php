<?php
declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Model;

use PeanutAdmin\Kernel\Persistence\Model\TenantModel;

final class Project extends TenantModel
{
    /** @var string */
    protected $name = 'example_project';
}
