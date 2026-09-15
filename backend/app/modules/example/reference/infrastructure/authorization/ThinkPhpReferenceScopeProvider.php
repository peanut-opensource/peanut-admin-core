<?php

declare(strict_types=1);

namespace PeanutAdmin\App\modules\example\reference\infrastructure\authorization;

use PeanutAdmin\App\modules\example\reference\contracts\ReferenceScope;
use PeanutAdmin\App\modules\example\reference\model\ReferenceItem;
use PeanutAdmin\App\modules\example\target\contracts\TargetIdSet;
use PeanutAdmin\DataPermission\Catalog\ResourceOperation;
use PeanutAdmin\DataPermission\Constraint\AlwaysFalse;
use PeanutAdmin\DataPermission\Constraint\ColumnIn;
use PeanutAdmin\DataPermission\Constraint\ColumnReference;
use PeanutAdmin\DataPermission\Constraint\QueryConstraint;
use PeanutAdmin\DataPermission\Context\authorizationContext;
use PeanutAdmin\DataPermission\Decision\authorizationDecision;
use PeanutAdmin\DataPermission\Provider\SharedMasterScopeProvider;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\db\BaseQuery;

final readonly class ThinkPhpReferenceScopeProvider implements SharedMasterScopeProvider, ReferenceScope
{
    public function compileVisiblePredicate(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
    ): QueryConstraint {
        $capability = match ($operation->operation) {
            'list' => 'view',
            'use' => 'use',
            'maintain' => 'maintain',
            default => throw new ModuleException(
                'AUTHZ_OPERATION_UNDECLARED',
                'Reference capability is not declared for this operation.',
            ),
        };
        $ids = $this->allowedIds($context, $targets, $capability);

        return $ids === [] ? new AlwaysFalse() : new ColumnIn(new ColumnReference('id'), $ids);
    }

    public function assertUsageAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        string $resourceId,
        TypedResourceTargetCollection $targets,
    ): AuthorizationDecision {
        return $this->canUse($context, $resourceId, $targets)
            ? AuthorizationDecision::allow()
            : AuthorizationDecision::deny('AUTHZ_SHARED_MASTER_SCOPE_DENIED');
    }

    public function canUse(
        AuthorizationContext $context,
        string $referenceItemId,
        TypedResourceTargetCollection $targets,
    ): bool {
        return in_array($referenceItemId, $this->allowedIds($context, $targets, 'use'), true);
    }

    /** @return list<string> */
    public function allowedIds(
        AuthorizationContext $context,
        TypedResourceTargetCollection $targets,
        string $capability,
    ): array {
        if (!in_array($capability, ['view', 'use', 'maintain'], true)) {
            throw new ModuleException('AUTHZ_OPERATION_UNDECLARED', 'Reference capability is invalid.');
        }
        $typedTargets = [];
        foreach ($targets->sets as $targetSet) {
            if (!in_array($targetSet->targetResourceKey, ['example.project', 'example.queue'], true)) {
                throw new ModuleException('AUTHZ_TARGET_TYPE_MISMATCH', 'Unknown reference target type.');
            }
            foreach (TargetIdSet::fromStrings($targetSet->targetIds)->ids as $targetId) {
                $typedTargets[] = [$targetSet->targetResourceKey, $targetId];
            }
        }

        $query = ReferenceItem::alias('item')
            ->join('example_reference_scope scope', 'scope.reference_item_id = item.id')
            ->where('item.status', 'active')
            ->where('scope.status', 'active')
            ->where('scope.capability', $capability)
            ->where(function (BaseQuery $visibility) use ($context, $typedTargets): void {
                $visibility->where('scope.scope_kind', 'all_tenants')
                    ->whereOr(function (BaseQuery $tenant) use ($context): void {
                        $tenant->where('scope.scope_kind', 'tenant')
                            ->where('scope.target_tenant_id', $context->tenant->tenantId);
                    });
                if ($typedTargets !== []) {
                    $visibility->whereOr(function (BaseQuery $typed) use ($context, $typedTargets): void {
                        $typed->where('scope.scope_kind', 'typed_target')
                            ->where('scope.target_tenant_id', $context->tenant->tenantId)
                            ->where(function (BaseQuery $matches) use ($typedTargets): void {
                                foreach ($typedTargets as $index => [$resourceKey, $targetId]) {
                                    $method = $index === 0 ? 'where' : 'whereOr';
                                    $matches->{$method}(function (BaseQuery $pair) use ($resourceKey, $targetId): void {
                                        $pair->where('scope.target_resource_key', $resourceKey)
                                            ->where('scope.target_id', $targetId);
                                    });
                                }
                            });
                    });
                }
            });

        return array_values(array_map('strval', $query->distinct(true)->order('item.id')->column('item.id')));
    }
}
