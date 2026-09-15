<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\target\contracts;

final readonly class TargetOption
{
    public function __construct(
        public string $resourceKey,
        public string $id,
        public string $code,
        public string $name,
    ) {}
}
