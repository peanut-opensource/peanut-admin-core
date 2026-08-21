# Shared Master Scope

A shared master keeps one canonical record and one identifier space while allowing different tenants or typed targets to view, use, or maintain different subsets.

The reference example uses `example.reference-item`. Deployment-owned and tenant-owned reference records remain in one table. Scope rows determine visibility and capability for all tenants, one tenant, or one typed target.

## Provider Contract

Every `shared_master` protected resource registers a `SharedMasterScopeProvider`:

```php
interface SharedMasterScopeProvider
{
    public function compileVisiblePredicate(
        AuthorizationContext $context,
        ResourceOperation $operation,
        TypedResourceTargetCollection $targets,
    ): QueryConstraint;

    public function assertUsageAllowed(
        AuthorizationContext $context,
        ResourceOperation $operation,
        string $resourceId,
        TypedResourceTargetCollection $targets,
    ): AuthorizationDecision;
}
```

Lists call `compileVisiblePredicate()`. Create, use, maintain, and other object actions call `assertUsageAllowed()`. A missing provider denies the operation.

## Selection Rules

Candidate selection is a scoped query against one source. It is not a SQL `UNION` of platform and tenant catalogs. A public reference and a private tenant reference can appear in the same result only when the provider authorizes both for the requested tenant and target set.

Consumers persist the stable reference identifier and call the owner Module contract. They do not join or mutate the shared-master tables directly.

## Capabilities

Visibility, use, and maintenance are separate capabilities. Being able to select a record does not imply being allowed to edit it. Platform authority does not create an implicit all-scope capability.
