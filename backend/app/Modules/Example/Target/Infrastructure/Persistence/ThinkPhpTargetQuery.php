<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Infrastructure\Persistence;

use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetIdSet;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetOption;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetQuery;
use PeanutAdmin\App\Modules\Example\Target\Model\Project;
use PeanutAdmin\App\Modules\Example\Target\Model\Queue;
use PeanutAdmin\Kernel\Tenancy\TenantScope;
use RuntimeException;
use think\Model;

final readonly class ThinkPhpTargetQuery implements TargetQuery
{
    public function find(int $tenantId, string $resourceKey, string $id): ?TargetOption
    {
        $model = $this->model($resourceKey);
        $record = $model::scope('tenant', $this->scope($tenantId))
            ->where('id', $id)
            ->where('status', 'active')
            ->find();

        return $record instanceof Model ? $this->option($resourceKey, $record) : null;
    }

    public function findMany(int $tenantId, string $resourceKey, array $ids): array
    {
        $ids = TargetIdSet::fromStrings($ids)->ids;
        if ($ids === []) {
            return [];
        }
        $model = $this->model($resourceKey);
        $records = $model::scope('tenant', $this->scope($tenantId))
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->order('code')
            ->order('id')
            ->select()
            ->toArray();

        return array_values(array_map(fn(array $record): TargetOption => $this->optionFromRow($resourceKey, $record), $records));
    }

    public function list(int $tenantId, string $resourceKey): array
    {
        $model = $this->model($resourceKey);
        $records = $model::scope('tenant', $this->scope($tenantId))
            ->where('status', 'active')
            ->order('code')
            ->order('id')
            ->select()
            ->toArray();

        return array_values(array_map(fn(array $record): TargetOption => $this->optionFromRow($resourceKey, $record), $records));
    }

    /** @return class-string<Project|Queue> */
    private function model(string $resourceKey): string
    {
        return match ($resourceKey) {
            'example.project' => Project::class,
            'example.queue' => Queue::class,
            default => throw new RuntimeException('Unknown example target resource key.'),
        };
    }

    private function option(string $resourceKey, Model $record): TargetOption
    {
        return new TargetOption(
            $resourceKey,
            (string) $record->getAttr('id'),
            (string) $record->getAttr('code'),
            (string) $record->getAttr('name'),
        );
    }

    /** @param array<string, mixed> $row */
    private function optionFromRow(string $resourceKey, array $row): TargetOption
    {
        return new TargetOption(
            $resourceKey,
            (string) $row['id'],
            (string) $row['code'],
            (string) $row['name'],
        );
    }

    private function scope(int $tenantId): TenantScope
    {
        return TenantScope::fromTrustedContext($tenantId, 'example-target-query');
    }
}
