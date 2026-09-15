<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\work_item\contracts;

final readonly class WorkItemView
{
    public function __construct(
        public string $id,
        public int $tenantId,
        public string $projectId,
        public string $projectLabel,
        public ?string $queueId,
        public string $referenceItemId,
        public string $title,
        public string $status,
        public int $revision,
    ) {}
}
