<?php

declare(strict_types=1);

namespace PeanutAdmin\App\module;

use PDO;
use PeanutAdmin\DataPermission\Catalog\ResourceOperation;
use PeanutAdmin\DataPermission\Constraint\AlwaysFalse;
use PeanutAdmin\DataPermission\Constraint\AlwaysTrue;
use PeanutAdmin\DataPermission\Constraint\ColumnReference;
use PeanutAdmin\DataPermission\Constraint\QueryConstraint;
use PeanutAdmin\DataPermission\Constraint\TenantEquals;
use PeanutAdmin\DataPermission\Context\AuthorizationContext;
use PeanutAdmin\DataPermission\Decision\AuthorizationDecision;
use PeanutAdmin\DataPermission\Policy\EffectivePolicySet;
use PeanutAdmin\DataPermission\Provider\ResourceCreatePolicyProvider;
use PeanutAdmin\DataPermission\Provider\ResourceQueryPolicyProvider;
use PeanutAdmin\DataPermission\Provider\ResourceTargetPolicyProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionModuleProvider;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\Kernel\Module\ModuleProvider;

abstract class TenantWideModuleProvider implements
    ModuleProvider,
    DataPermissionModuleProvider,
    ResourceQueryPolicyProvider,
    ResourceTargetPolicyProvider,
    ResourceCreatePolicyProvider
{
    abstract protected function tenantColumn(): string;

    public function bindings(): array
    {
        return [];
    }

    public function registerDataPermission(DataPermissionRuntimeRegistry $registry, PDO $pdo): void
    {
        $registry->registerResourceProvider(static::class, $this);
    }

    public function tenantConstraint(AuthorizationContext $context, ResourceOperation $operation): QueryConstraint
    {
        return new TenantEquals(new ColumnReference($this->tenantColumn()), $context->tenant->tenantId);
    }

    public function requestedTargetConstraint(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
    ): QueryConstraint {
        return $targets->sets === [] ? new AlwaysTrue() : new AlwaysFalse();
    }

    public function compilePredicate(
        AuthorizationContext $context,
        ResourceOperation $operation,
        EffectivePolicySet $policies,
    ): QueryConstraint {
        return $this->tenantConstraint($context, $operation);
    }

    public function assertTargetsAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
        EffectivePolicySet $policies,
    ): AuthorizationDecision {
        return $targets->sets === [] ? AuthorizationDecision::allow() : AuthorizationDecision::deny();
    }

    public function assertCreateAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
        EffectivePolicySet $policies,
    ): AuthorizationDecision {
        return $this->assertTargetsAllowed($context, $operation, $targets, $policies);
    }
}
