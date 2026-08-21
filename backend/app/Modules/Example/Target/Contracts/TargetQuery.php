<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Contracts;

interface TargetQuery
{
    public function find(int $tenantId, string $resourceKey, string $id): ?TargetOption;

    /**
     * @param list<string> $ids
     * @return list<TargetOption>
     */
    public function findMany(int $tenantId, string $resourceKey, array $ids): array;

    /** @return list<TargetOption> */
    public function list(int $tenantId, string $resourceKey): array;
}
