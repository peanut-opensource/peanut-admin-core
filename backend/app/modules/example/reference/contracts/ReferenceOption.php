<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\reference\contracts;

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
