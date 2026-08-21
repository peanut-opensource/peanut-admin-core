# Data Permission Package

The Data Permission namespace inside `peanut-admin/core` applies resource and
operation data scopes after the Kernel functional permission check. It never
grants a functional permission and it has no super-user bypass.

## Evaluation Order

1. Resolve the active protected resource and operation catalog entry.
2. Require an installed and currently enabled tenant module.
3. Evaluate every fixed functional permission bound to the operation.
4. Validate typed target cardinality before calling a target resolver.
5. Load active role policies and merge conditions with group `AND`, group `OR`, and role `OR` semantics.
6. Ask the registered resource provider for a structured query constraint or target decision.
7. Apply the tenant hard boundary for tenant-owned and business-target-owned resources.
8. Intersect shared-master access with its registered scope provider.

Any missing catalog entry, provider, resolver, policy, active group, or supported condition fails closed.

## Core Conditions

The P0 engine supports `core.tenant_all`, `core.self`, `core.own_department`, `core.department_tree`, `core.specified_departments`, and `core.specified_objects`. A member without a primary department receives an empty result for department-derived conditions. `core.tenant_all` must be the only condition in its group.

## Query Constraints

Providers return a typed constraint tree: tenant equality, column equality, bounded column membership, JSON-array membership, conjunction, disjunction, fixed EXISTS contracts, or constant true/false. Public APIs do not accept raw SQL or tenant-configured column names.

`ColumnIn` is limited to 500 values. Larger saved policy sets use the fixed `data_permission.target-set` EXISTS contract against the normalized target relation. Larger operation requests use one JSON parameter compiled to a fixed `JSON_TABLE` membership constraint. Repositories compile either form to parameterized SQL and apply it before list, count, aggregate, detail, or write selection queries.

## Provider Contracts

- `ResourceQueryPolicyProvider`: compiles list and aggregate constraints.
- `ResourceTargetPolicyProvider`: checks every known target for detail and write operations.
- `ResourceCreatePolicyProvider`: checks create descriptors before a business row exists.
- `ResourceTargetResolver`: proves target type, existence, and tenant relation.
- `ResourceTargetCatalogProvider`: returns paginated authorized target options.
- `SharedMasterScopeProvider`: intersects shared-record visibility and usage scope.
- `ConditionProvider`: adds a version-controlled module condition without exposing expressions to tenants.

Module providers implement `DataPermissionModuleProvider` and register their resource providers, target resolvers, target catalogs, and shared-master providers by manifest-owned keys. The reference host composes only providers from the compiled Module registry. Missing registrations produce an authorization error and never broaden access.

## Targets And Caching

`TypedResourceTargetSet` contains one target type, one operation role, and normalized string IDs. `TypedResourceTargetCollection` can contain several distinct typed sets. `one_required` rejects zero or multiple primary targets, while P0 rejects `bulk_write` entirely.

Policy cache keys include tenant, member, registry, operation, and aggregated policy revisions. Cache TTL never crosses the nearest future policy transition and is capped at five minutes. A revision change makes an old entry unreachable even when active deletion fails.
