# Typed Targets And Operation Cardinality

A typed target identifies a business boundary object owned by a Module. The Kernel stores the target type registry but not universal target instances.

## Request Shape

```json
{
  "target_sets": [
    {
      "target_resource_key": "example.project",
      "target_role": "primary",
      "target_ids": ["1", "2"]
    }
  ]
}
```

Identifiers are strings at API boundaries. One target set has one `target_resource_key` and one `target_role`. Project IDs and Queue IDs cannot be mixed even when their raw values match. Relation uniqueness is based on both fields, so the same target type can appear separately as `source` and `destination`.

In PHP, use `TypedResourceTargetSet` and `TypedResourceTargetCollection`:

```php
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;

$targets = new TypedResourceTargetCollection([
    new TypedResourceTargetSet('example.project', ['1', '2'], 'primary'),
]);
```

The owning resolver validates target type, tenant ownership, status, and existence. Requested targets can narrow effective authorization; they cannot widen it.

## Cardinalities

| Value | Meaning | P0 rule |
| --- | --- | --- |
| `none` | No primary target | Tenant, Module, permission, and resource checks still apply. |
| `one_required` | Exactly one primary target | Default for ordinary create, update, delete, and commands. |
| `zero_or_one` | Optional single target | Only for operations whose declared semantics allow it. |
| `many_readable` | One or more readable targets | Lists show ownership when several targets are active. |
| `aggregate_read` | Multi-target summary | Read-only with an explicit scope summary. |
| `policy_publish` | Publish one policy to several targets | Stores per-target publication results. |
| `bulk_write` | Change several targets at once | Denied in P0. |

Source and destination targets in a transfer-like command use separate target roles and are all validated. This does not turn the operation into an unrestricted bulk write.

## Member Scope

One TenantMember can hold different sets for different target types and operations. A session does not contain one global selected target. Each page and request derives selection from its declared resource operation.
