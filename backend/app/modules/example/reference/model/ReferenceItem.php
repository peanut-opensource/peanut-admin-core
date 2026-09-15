<?php
declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\reference\model;

use think\model;

final class ReferenceItem extends Model
{
    /** @var string */
    protected $name = 'example_reference_item';
    /** @var bool */
    protected $autoWriteTimestamp = false;
}
