<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Contracts;

final readonly class TargetOption
{
    public function __construct(
        public string $resourceKey,
        public string $id,
        public string $code,
        public string $name,
    ) {}
}
