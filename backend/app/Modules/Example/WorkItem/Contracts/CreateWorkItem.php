<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Contracts;

final readonly class CreateWorkItem
{
    public function __construct(
        public string $projectId,
        public ?string $queueId,
        public string $referenceItemId,
        public string $title,
    ) {}
}
