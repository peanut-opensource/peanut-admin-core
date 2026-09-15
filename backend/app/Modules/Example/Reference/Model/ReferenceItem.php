<?php
declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Reference\Model;

use think\Model;

final class ReferenceItem extends Model
{
    /** @var string */
    protected $name = 'example_reference_item';
    /** @var bool */
    protected $autoWriteTimestamp = false;
}
