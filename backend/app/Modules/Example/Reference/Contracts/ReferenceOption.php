<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Reference\Contracts;

final readonly class ReferenceOption
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $ownerType,
        public ?int $ownerTenantId,
    ) {}
}
