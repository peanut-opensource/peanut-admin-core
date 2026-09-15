<?php
declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\target\model;

use PeanutAdmin\Kernel\persistence\model\TenantModel;

final class Project extends TenantModel
{
    /** @var string */
    protected $name = 'example_project';
}
