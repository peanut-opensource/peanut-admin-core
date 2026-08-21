<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\WorkItem\Contracts;

final readonly class WorkItemChanged
{
    public const NAME = 'example.work-item.changed.v1';

    public function __construct(
        public string $workItemId,
        public int $tenantId,
        public int $revision,
    ) {}
}
