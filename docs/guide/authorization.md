# Functional And Data Authorization

Peanut Admin separates two questions:

1. Functional RBAC: may this member attempt this operation?
2. Data permission: which rows and typed targets may the operation read or change?

Both must allow. Menus and buttons are user-interface hints, not security decisions.

## Request Order

```text
trusted TenantContext
-> active ModuleInstallation
-> effective TenantModule
-> functional Permission
-> declared ProtectedResource operation
-> typed target resolution and cardinality
-> effective Role data policies
-> Module provider constraint or object decision
-> business rule
-> audit
```

Missing context, permission binding, operation, target type, resolver, policy provider, or shared-master scope provider denies the request.

## Policy Conditions

P0 supports tenant-wide, self, own Department, Department tree, specified Departments, and specified typed objects. A member without a primary Department receives an empty result for Department-derived conditions; it never falls back to the whole tenant.

Effective allow policies from active roles may union. Tenant isolation, requested target restrictions, and independent constraint dimensions still intersect. P0 has no deny override language, super-user flag, or member-level policy table.

## Query And Object Parity

List and aggregate paths obtain a `QueryConstraint` from `DataPermissionEngine::queryConstraint()`. The PDO compiler produces parameterized SQL fragments and parameters. Detail, create, update, delete, and command paths call target or create decisions against the same operation catalog and effective policies.

```php
use PeanutAdmin\DataPermission\Constraint\PdoQueryConstraintCompiler;

$constraint = $engine->queryConstraint(
    $tenantContext,
    'example.work-item',
    'list',
    $requestedTargets,
);
$compiled = (new PdoQueryConstraintCompiler())->compile($constraint);
```

The compiled SQL is appended only to the owning Module repository query. A controller must not accept a client filter as an authorization predicate.

## Policy Administration

Target candidates for runtime use are already restricted to the member's effective scope. Policy-configuration candidates additionally require `core.role.data-policy.manage` and the operation's policy-selection permission. This prevents a role editor from becoming a target enumeration bypass.

## Effective Access Preview

An administrator with `core.member.effective-access.read` can inspect the
current authorization inputs for one member in the active Tenant at
`GET /api/v1/members/{member_id}/effective-access`. The resource-operation list
uses the normal `page` and `page_size` parameters. Tenant identity always comes
from the validated session; the request cannot supply an account, Tenant, role,
Permission, target, hypothetical policy, or evaluation time.

The preview uses the existing RBAC union and effective-policy repositories. It
shows active effective roles, sorted effective Permission keys, functional
Permission matching, and redacted policy conditions with typed target counts.
Inactive members have no effective roles, Permissions, or policy groups.
Unavailable Modules and inactive, future, expired, disabled, or empty policy
inputs do not become effective.

The response kind is `authorization_inputs`, not an authorization decision.
`conditional` means that an effective policy input exists. A real operation
still validates cardinality and typed targets and asks its registered Provider
for the query constraint, create decision, or target decision. The preview does
not forge a session for the inspected member, compile SQL, return raw target
IDs, or widen access when a Runtime contract is missing.

Each successful preview records a redacted
`tenant.member.effective-access.viewed` Tenant audit event. Authentication,
permission, member-not-found, and validation failures do not create a successful
preview event, and the operation does not write an authentication security
event.
