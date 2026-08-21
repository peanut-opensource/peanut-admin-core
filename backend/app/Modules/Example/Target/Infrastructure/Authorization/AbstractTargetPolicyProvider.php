<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Infrastructure\Authorization;

use PeanutAdmin\DataPermission\Catalog\ResourceOperation;
use PeanutAdmin\DataPermission\Constraint\QueryConstraint;
use PeanutAdmin\DataPermission\Context\AuthorizationContext;
use PeanutAdmin\DataPermission\Decision\AuthorizationDecision;
use PeanutAdmin\DataPermission\Policy\EffectivePolicySet;
use PeanutAdmin\DataPermission\Provider\ResourceCreatePolicyProvider;
use PeanutAdmin\DataPermission\Provider\ResourceQueryPolicyProvider;
use PeanutAdmin\DataPermission\Provider\ResourceTargetPolicyProvider;
use PeanutAdmin\DataPermission\Provider\StandardResourcePolicyProvider;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;

abstract readonly class AbstractTargetPolicyProvider implements
    ResourceQueryPolicyProvider,
    ResourceTargetPolicyProvider,
    ResourceCreatePolicyProvider
{
    public function __construct(private StandardResourcePolicyProvider $delegate) {}

    public function tenantConstraint(AuthorizationContext $context, ResourceOperation $operation): QueryConstraint
    {
        return $this->delegate->tenantConstraint($context, $operation);
    }

    public function requestedTargetConstraint(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
    ): QueryConstraint {
        return $this->delegate->requestedTargetConstraint($context, $operation, $targets);
    }

    public function compilePredicate(
        AuthorizationContext $context,
        ResourceOperation $operation,
        EffectivePolicySet $policies,
    ): QueryConstraint {
        return $this->delegate->compilePredicate($context, $operation, $policies);
    }

    public function assertTargetsAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
        EffectivePolicySet $policies,
    ): AuthorizationDecision {
        return $this->delegate->assertTargetsAllowed($context, $operation, $targets, $policies);
    }

    public function assertCreateAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
        EffectivePolicySet $policies,
    ): AuthorizationDecision {
        return $this->delegate->assertCreateAllowed($context, $operation, $targets, $policies);
    }
}
