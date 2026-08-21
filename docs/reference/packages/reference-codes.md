# Reference Codes PHP Package

The Reference Codes namespace inside `peanut-admin/core` provides neutral,
reusable reference-code set definitions and Tenant-owned, immutable code
identities with append-only versions. Its Admin contribution is exported as
`@peanut-admin/admin/reference-codes`. It does not define application
categories, workflow states, units, taxonomies, or default values.

## Ownership

- A declaring Module owns each set's `(module_key, set_key)` identity, display
  fields, digest, and availability.
- `TenantContext` is required by every entry read and write. Request data must
  never supply a Tenant or member identifier.
- The package has no deployment, platform, target, global, or shared-master
  value scope.
- The Host must verify both `peanut.reference-codes` and the declaring Module
  before calling the package. The repository independently requires the active
  synchronized set digest on the same PDO connection.

## Definitions

`ReferenceCodeSetLoader::load()` accepts a declaring Module key and its trusted
`reference-code-sets.json` path. The resource is a list of exact objects with
`key`, `name`, and `description`. Unknown fields, duplicate keys, invalid owners,
unsafe slugs, malformed UTF-8, and missing resources fail closed.

Register every compiled Module, including Modules with zero sets, in one
`ReferenceCodeSetRegistry`. `PdoReferenceCodeRepository::synchronize()` treats
that registry as the complete definition snapshot: changed definitions advance
their revision, missing definitions retire, and restoring the same owner/key
reactivates the same database identity.

## Tenant Commands

Construct `ReferenceCodeAdminService` with a `PdoReferenceCodeRepository` that
uses the same PDO instance as the R02 atomic operation. The service exposes:

```php
$created = $admin->create(
    $set,
    $tenantContext,
    'sample-code',
    'Sample label',
    [],
    'active',
    0,
    new DateTimeImmutable('2026-07-20T00:00:00.000Z'),
    null,
    '*',
);

$changed = $admin->replace(
    $set,
    $tenantContext,
    'sample-code',
    'Changed label',
    ['visible' => true],
    'active',
    10,
    new DateTimeImmutable('2026-07-21T00:00:00.000Z'),
    null,
    $created->etag,
);

$retired = $admin->retire(
    $set,
    $tenantContext,
    'sample-code',
    $changed->etag,
);
```

Create requires `If-None-Match: *`. Replace and retire require exactly one
strong `"rev-N"` ETag. A replace appends one version and never changes `code`.
Retirement appends a terminal inactive version and permanently reserves the
identity.

R02 owns idempotency receipts and audit persistence. Use
`EffectiveReferenceCode::auditMetadata()` for the package's bounded audit
projection; it excludes labels, metadata values, request bodies, Tenant/member
IDs, SQL, credentials, and private paths.

## Queries

`ReferenceCodeQuery` resolves one UTC `as_of` instant per call. Version
intervals are start-inclusive and end-exclusive; overlapping intervals use the
greatest revision. Lists sort by effective `sort_order`, then ASCII-binary
`code`, with entries lacking an effective version last.

```php
$detail = $query->get($set, $tenantContext, 'sample-code', $asOf);
$page = $query->list(
    $set,
    $tenantContext,
    $asOf,
    effectiveStatus: 'all',
    includeRetired: false,
    page: 1,
    pageSize: 50,
);
$selectable = $query->resolve($set, $tenantContext, 'sample-code', $asOf);
$candidates = $query->listActiveCandidates($set, $tenantContext, $asOf);
```

Unknown, cross-Tenant, retired-hidden, digest-mismatched, or corrupt state does
not fall through to another Tenant or an older version. Consumers persist the
stable code string and call this query contract instead of joining package
tables directly.

## Schema Boundary

`Database\Schema` exposes the ordered SQL contract for exactly three tables:

1. `pa_reference_code_set`
2. `pa_reference_code_entry`
3. `pa_reference_code_entry_version`

Host-owned migrations call these statements in order and implement
`OwnedMigration` for `peanut.reference-codes`. Operational rollback must not
run destructive down migrations; reverse drops are only for an empty
clean-install test database.
