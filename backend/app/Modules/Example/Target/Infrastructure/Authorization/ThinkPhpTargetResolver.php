<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization;

use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetIdSet;
use PeanutAdmin\App\Modules\Example\Target\Model\Project;
use PeanutAdmin\App\Modules\Example\Target\Model\Queue;
use PeanutAdmin\DataPermission\Target\ResolvedResourceTargets;
use PeanutAdmin\DataPermission\Target\ResourceTargetResolver;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Module\ModuleException;
use PeanutAdmin\Kernel\Tenancy\TenantScope;

final readonly class ThinkPhpTargetResolver implements ResourceTargetResolver
{
    public function resolveAndValidate(TenantContext $context, TypedResourceTargetSet $targets): ResolvedResourceTargets
    {
        if ($targets->targetIds === []) {
            return new ResolvedResourceTargets(new TypedResourceTargetCollection([$targets]));
        }
        $model = match ($targets->targetResourceKey) {
            'example.project' => Project::class,
            'example.queue' => Queue::class,
            default => throw new ModuleException('AUTHZ_TARGET_TYPE_MISMATCH', 'Unknown example target type.'),
        };
        $ids = TargetIdSet::fromStrings($targets->targetIds)->ids;
        $count = $model::scope('tenant', TenantScope::fromTrustedContext($context->tenantId, 'example-target-resolver'))
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->count();
        if ((int) $count !== count($ids)) {
            throw new ModuleException('AUTHZ_TARGET_NOT_FOUND', 'Target does not exist in the trusted tenant context.');
        }

        return new ResolvedResourceTargets(new TypedResourceTargetCollection([$targets]));
    }
}
