# P1-B04 Minimal Reference Codes Module Contract

## Status

```text
state: starter-v1-integration-authorized
task: PA-SV1-C01-S02-reference-codes
integration_prerequisite_commit: 4a874165a42d33d160cd0dd8cf167cc9b4fbab11
integration_prerequisite_tree: 8e033c5492418f883c59fc19a1ad03a79ce5b6f4
qualified_downstream_lock: 0ab02a9b735ba9f4c23509cb366b9bf04039ebf8
module_key: peanut.reference-codes
schema_owner: peanut.reference-codes
runtime_test_owner: RUNTIME-REFERENCE-CODES-001
dependency_change: no third-party dependency
expected_b03_b04_integrated_operations: 75 P0 + 16 P1 = 91
test_budget: development
qualification: development candidate only
```

This contract is the complete execution input for P1-B04. The aggregate
readiness plan is not an implementation specification and must not be used to
infer behavior that is absent here.

The Starter v1 implementation branch starts from the exact Settings and G02
integration commit above. It reuses only the reviewed Reference Codes source
commits named by the controlling task. Manifests, package locks, OpenAPI source,
generated route and TypeScript artifacts, Runtime coverage, starter output, and
test registration are regenerated or reconciled on this tree. Historical pure
B04 or B03+B04 integration artifacts are not copied over the current baseline.

## Starter v1 Integration Amendment

This amendment replaces the historical pure-branch execution and qualification
sequence for `PA-SV1-C01-S02-reference-codes`. The plural package and Module
model in this document remains authoritative. The older singular
`peanut.reference-code` contract and WIP implementation are superseded and must
not coexist with this Runtime.

The reusable source order is fixed as follows:

1. `08341d1f321e3ebeeb7cfadf96a6c92e041251d6`, whose atomic command guard is
   already inherited through the stronger Settings host hardening and is not
   applied again;
2. `46ddad4624cd8dfdec7b23452b2551724663cc2f` and
   `e45a670f4abd71fb348c298ec7f68be9bc6fcc73` for the PHP package and MySQL
   compatibility correction;
3. `32bef3261f45c70516d280983f918ece7d8d7bb8` and
   `821f35466e823a839b1265d8334f5f56210741df` for the Web package and contract
   hardening;
4. `16ef673f7d58898fc5820bcb0f6bf03e6869116e` and
   `80ecbc9fae4f466d8bf95557900335b87eb02b76` for the Host API Module and
   transaction-local Module guard locking.

Only Reference Codes package, Host, migration, API, focused tests, manifest,
OpenAPI/generated, dependency wiring, starter, and standard Admin Web
integration may change. Historical Q01, aggregate environment, performance,
browser-matrix, recovery, clean-install orchestration, and unrelated inherited
tests or scripts are not implementation inputs for this slice. The fixed
downstream lock, `dev`, downstream application code, tags, releases, and
dependency versions do not move.

Development acceptance requires focused PHP unit/integration, migration,
Tenant-isolation, permission, OpenAPI/generated consistency, Reference Codes
Web unit/type checks, frontend typecheck, and frontend build. The historical
aggregate, complete browser matrix, performance, recovery, clean install, and
fixed-candidate qualification commands described later remain deferred until a
Starter v1 candidate is fixed. Passing this slice does not satisfy those gates.

## Objective And Non-Goals

B04 provides reusable Module-owned code-set definitions and Tenant-owned,
versioned reference-code entries. It supports stable code identity, mutable
label and bounded scalar metadata, active or inactive effective versions,
deterministic ordering, optimistic concurrency, redacted audit evidence, and
historical as-of reads.

B04 does not provide:

- application categories, document states, order states, settlement states,
  inventory states, unit catalogs, identifiers, taxonomies, lifecycle rules,
  approval steps, or another product-domain model;
- a custom status vocabulary, hierarchy, parent-child relation, translation
  system, arbitrary workflow, calculation, entitlement, policy engine, or
  generic CRUD generator;
- deployment-wide entries, cross-Tenant entries, typed-target entries,
  shared-master visibility rows, or precedence between multiple value scopes;
- physical entry deletion, identity reuse, bulk import/export, public package
  publication, a release, or a downstream-consumption decision.

No downstream-consumer-specific status or reference value may be committed to
the Peanut Module, reusable packages, reference host, starter, profiles,
documentation, or non-test fixtures. A downstream application that needs
product categories, states, units, identifiers, or lifecycle semantics owns
those definitions, values, tables, APIs, permissions, pages, and migrations in
its own Module. B04 is infrastructure only.

The existing fictional `example.reference` Module remains a P0 shared-master
authorization fixture. B04 does not import, rename, migrate, replace, or modify
its tables, scope provider, APIs, values, or tests.

## Package, Module, And Declaration Boundary

The reusable PHP package is `peanut-admin/reference-codes` with namespace
`PeanutAdmin\ReferenceCodes\`. The reusable Web package is
`@peanut-admin/reference-codes`. Neither package is published by this task.

The Host Module key is exactly `peanut.reference-codes`. Its provider remains
in the configured Host namespace and delegates to the PHP package. The
reference host provider is
`PeanutAdmin\App\Modules\Peanut\ReferenceCodes\ModuleProvider`; it owns no
parallel reference-code model.

The Module manifest schema adds one optional backend resource:

```json
{
  "backend": {
    "reference_code_sets": "Resources/reference-code-sets.json"
  }
}
```

An enabled Module may declare zero or more sets in that trusted JSON resource.
Sets cannot be created, renamed, or deleted through HTTP. The loader rejects a
missing resource, invalid JSON, an unknown field, a duplicate key, duplicate
owner, owner mismatch, or an invalid display field.

Each set declaration has exactly these fields:

```text
key: local lower-case slug, maximum 64 characters
name: trimmed non-empty display name, maximum 160 characters
description: trimmed non-empty text, maximum 500 characters
```

The local key matches `^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$`. Stable set identity is
the pair `(declaring Module key, local set key)`. A declaration contains no
reference-code values, custom states, default entries, Tenant identifiers,
permissions, target declarations, or application behavior. Its digest is the
SHA-256 of canonical JSON.

Definition synchronization runs during install and upgrade after manifests
compile and before a Tenant Module can be enabled. A changed digest updates the
trusted name and description and increments definition revision. Removing a
declaration or its owner Module marks the set `retired`; it never deletes
Tenant entries. Restoring the same owner and key reactivates the same set and
increments definition revision. A second upgrade on the same tree applies zero
migrations and makes zero definition changes.

## Owner And Scope Contract

Ownership and scope are fixed and have no implicit fallback:

1. The declaring Module owns the set identity, name, description, and
   availability.
2. The trusted `TenantContext` owns every entry and every version written
   through the Tenant API.
3. The request path selects only an existing active `(module_key, set_key)`;
   request input never creates an owner or Tenant.
4. The current Tenant can read or mutate only rows whose `tenant_id` comes from
   its validated session context.
5. There is no deployment, platform, global, target, or shared-master row and
   therefore no `tenant > deployment` or target precedence.
6. Disabling `peanut.reference-codes` removes the route contribution. Disabling
   or uninstalling the declaring Module makes its sets non-enumerating `404`
   resources and rejects new writes.

An application that needs one canonical cross-Tenant catalog or typed-target
visibility uses its own Module and the existing shared-master/data-authorization
contracts. It must not add another scope to B04 tables.

## Schema And Migration Contract

The `peanut.reference-codes` migration sequence is fixed and ordered:

```text
20260719040101_create_reference_code_sets.php
20260719040102_create_reference_code_entries.php
20260719040103_create_reference_code_entry_versions.php
```

All three migrations implement `OwnedMigration`, report Module key
`peanut.reference-codes`, and are additive. They alter no P0, R01, R02, B03,
or `example.reference` table.

`pa_reference_code_set` contains exactly:

```text
id BIGINT UNSIGNED primary key
module_key VARCHAR(96) ASCII BINARY not null
set_key VARCHAR(64) ASCII BINARY not null
name VARCHAR(160) not null
description VARCHAR(500) not null
definition_digest CHAR(64) ASCII BINARY not null
lifecycle VARCHAR(16) ASCII BINARY not null: active|retired
revision BIGINT UNSIGNED not null default 1
created_at DATETIME(3) not null
updated_at DATETIME(3) not null
unique (module_key, set_key)
index (lifecycle, module_key, set_key)
check revision >= 1
```

`pa_reference_code_entry` contains exactly:

```text
id BIGINT UNSIGNED primary key
tenant_id BIGINT UNSIGNED not null, foreign key pa_tenant(id) RESTRICT
set_id BIGINT UNSIGNED not null, foreign key pa_reference_code_set(id) RESTRICT
code VARCHAR(64) ASCII BINARY not null
lifecycle VARCHAR(16) ASCII BINARY not null: active|retired
revision BIGINT UNSIGNED not null default 1
created_by_member_id BIGINT UNSIGNED not null, foreign key pa_tenant_member(id) RESTRICT
updated_by_member_id BIGINT UNSIGNED not null, foreign key pa_tenant_member(id) RESTRICT
retired_at DATETIME(3) null
created_at DATETIME(3) not null
updated_at DATETIME(3) not null
unique (tenant_id, set_id, code)
index (tenant_id, set_id, lifecycle, code)
check revision >= 1
check lifecycle/retired_at shape:
  active requires retired_at null
  retired requires retired_at not null
```

`pa_reference_code_entry_version` contains exactly:

```text
id BIGINT UNSIGNED primary key
entry_id BIGINT UNSIGNED not null, foreign key pa_reference_code_entry(id) RESTRICT
revision BIGINT UNSIGNED not null
label VARCHAR(160) not null
metadata_json JSON not null
status VARCHAR(16) ASCII BINARY not null: active|inactive
sort_order INT not null default 0
effective_at DATETIME(3) not null
expires_at DATETIME(3) null
changed_by_member_id BIGINT UNSIGNED not null, foreign key pa_tenant_member(id) RESTRICT
created_at DATETIME(3) not null
unique (entry_id, revision)
index (entry_id, effective_at, expires_at, revision)
index (entry_id, status, effective_at, expires_at, revision)
check revision >= 1
check sort_order between -1000000 and 1000000
check expires_at is null or expires_at > effective_at
```

The code identity is immutable, case-sensitive ASCII, and matches
`^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$`. It is never updated after insert. A label is
trimmed UTF-8 text from 1 to 160 characters.

Metadata is a JSON object with at most 32 unique keys. A key uses the same local
slug grammar and is at most 64 characters. A value is only null, boolean, a
finite JSON number, or a string of at most 500 Unicode characters. Arrays,
nested objects, invalid UTF-8, and an encoded object larger
than 8192 bytes are rejected. The fixed scalar contract prevents metadata from
becoming an opaque application document.

All foreign keys use `RESTRICT`; no Tenant row or member row cascades into
reference-code storage. Database, package, and HTTP write paths reject any
timestamp that cannot be represented exactly by `DATETIME(3)`. Accepted RFC
3339 offsets are normalized to UTC milliseconds without rounding or
truncation.

Creation inserts one identity row and version revision `1` in one transaction.
Replace locks the identity `FOR UPDATE`, requires the current strong ETag,
increments revision by exactly one, and inserts one immutable version row.
Retire locks the identity, requires the current strong ETag, increments
revision by one, sets `lifecycle=retired` and `retired_at` to the transaction
UTC millisecond, and inserts a terminal inactive version that copies the last
label, metadata, and sort order. A retired code is permanently reserved and
cannot be recreated, changed, reactivated, or reassigned.

There is no physical-delete Runtime API and no purge task in B04. Set and entry
rows and their versions are retained indefinitely, subject only to a later
explicit retention contract. Migration `down()` may drop the three tables in
reverse order only in an empty clean-install test database. Operational
rollback never runs destructive down migrations.

## Effective And As-Of Resolution

Resolution uses one UTC `as_of` instant. When `as_of` is omitted, the service
captures the transaction comparison time once and returns it in the response.

For an active entry, candidate versions satisfy:

```text
effective_at <= as_of
and (expires_at is null or as_of < expires_at)
```

If intervals overlap, the matching version with the greatest positive
`revision` wins. This is the only version precedence. The winner's `status` is
the effective status. `active` entries are selectable; `inactive` entries are
visible only to the administration query when requested. If no version is
effective, the response carries `effective: null` and the entry is not a
selectable candidate.

An entry with `retired_at <= as_of` is retired regardless of an older active
version. A historical query with `as_of < retired_at` may resolve an older
version. Unknown, corrupt, cross-Tenant, duplicate, or internally inconsistent
rows fail closed; the resolver does not skip corruption and fall through to an
older version.

Lists use fixed ordering:

```text
effective sort_order ASC
code ASC using ASCII binary comparison
```

Entries with `effective: null` sort after effective entries and then by code.
No endpoint accepts an SQL column, direction, expression, or arbitrary sort.

The PHP package exports a query contract for a downstream Module to resolve one
code or list active candidates for the current trusted Tenant and active set.
Consumers persist the stable code string and call the owner package contract;
they do not join, mutate, or infer another Tenant's B04 tables.

## API Contract

B04 adds exactly six P1 Runtime operations, all in the Tenant audience:

| Method and path | Operation ID | Permission | Behavior |
| --- | --- | --- | --- |
| `GET /api/v1/reference-code-sets` | `listReferenceCodeSets` | `peanut.reference-codes.read` | list active declarations from enabled owner Modules |
| `GET /api/v1/reference-code-sets/{module_key}/{set_key}/codes` | `listReferenceCodes` | `peanut.reference-codes.read` | list current-Tenant entries at one as-of instant |
| `GET /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}` | `getReferenceCode` | `peanut.reference-codes.read` | read one current-Tenant identity and effective version |
| `POST /api/v1/reference-code-sets/{module_key}/{set_key}/codes` | `createReferenceCode` | `peanut.reference-codes.manage` | create immutable code identity and version 1 |
| `PUT /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}` | `replaceReferenceCode` | `peanut.reference-codes.manage` | append one version under a strong precondition |
| `DELETE /api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}` | `retireReferenceCode` | `peanut.reference-codes.manage` | permanently retire one identity |

The Host operation owner is `peanut.reference-codes`. Its protected resource is
`peanut.reference-code`, ownership is `tenant_owned`, data-authorization mode
is `none`, and target cardinality is `none`. Functional permission, trusted
Tenant context, the Host Module, and the declaring Module remain mandatory.
No request field, query parameter, route value, or header may supply a Tenant,
member, owner Module result, permission result, or target.

The list query accepts only:

```text
as_of: optional RFC 3339 instant with exact millisecond representation
effective_status: optional active|inactive|all, default all
include_retired: optional true|false, default false
page: optional integer 1..10000, default 1
page_size: optional integer 1..100, default 50
```

The detail query accepts only optional `as_of`. Unknown query parameters and
unknown JSON fields are `422`. The set list has no query parameters and is
ordered by `module_key ASC, set_key ASC` using ASCII binary comparison.
`effective_status=active|inactive` keeps only an effective winner with that
status; `all` also includes entries with `effective: null`. `include_retired`
is evaluated at `as_of`, so an identity retired later remains visible to a
historical query. `total` is the filtered count before pagination.

Create requires exactly:

```json
{
  "code": "sample-code",
  "label": "Sample label",
  "metadata": {},
  "status": "active",
  "sort_order": 0,
  "effective_at": "2026-07-20T00:00:00.000Z",
  "expires_at": null
}
```

Replace accepts the same object without `code`; every listed field remains
required, and null is allowed only for `expires_at`. The fixed `status` values
are infrastructure availability only and must not be extended with
application lifecycle values. DELETE has no request body.

Set summaries contain exactly `module_key`, `set_key`, `name`, `description`,
and positive `definition_revision`. An entry response contains exactly:

```text
module_key
set_key
code
lifecycle: active|retired
revision: positive current identity revision
etag: current strong ETag
effective: null or {
  revision, label, metadata, status, sort_order, effective_at, expires_at
}
created_at
updated_at
retired_at
```

The list response contains `items`, `as_of`, `page`, `page_size`, and
`total`. It does not expose numeric table IDs, Tenant IDs, member IDs, owner
paths, Provider classes, or another Tenant's existence.

Every response includes `X-Request-Id` and `Cache-Control: no-store`. Detail,
create, replace, and retire responses include the strong `ETag`; create also
includes `Location`. JSON success responses use `application/json`. Problem
responses use `application/problem+json`.

Create `Location` is the absolute-path detail route
`/api/v1/reference-code-sets/{module_key}/{set_key}/codes/{code}` with every
path segment encoded once. It contains no origin, query, fragment, Tenant ID,
or numeric database ID.

The ETag body field and header are identical and use the existing strong
format `"rev-N"`, where `N` is the positive current identity revision. Create
requires `If-None-Match: *`; replace and retire require exactly one strong
`If-Match: "rev-N"`. Weak, wildcard, comma-separated, malformed, missing, and
zero revisions are rejected. Create returns `201`; all reads, replace, and
retire return `200`.

All three writes require an `Idempotency-Key` of 16 to 128 valid characters and
run through R02 on one PDO transaction. The canonical request hash includes
operation ID, method, normalized path parameters, canonical JSON body or empty
body, and the exact precondition header. Exact replay returns the stored safe
status, body, ETag, Location, and request-independent headers without another
version or audit row. The same scoped key with another hash is `409`.

Before idempotency chooses execution, conflict, or replay, every command
revalidates the current set declaration and its owner Module on the same PDO
transaction. The post-authorization guard takes a shared definition lock and
runs inside `AtomicOperationAdapter`; first execution repeats the same check in
the domain callback before mutation. The additive R02 guard behavior must be
byte-for-byte or behaviorally identical to the independently reviewed B03
guard when integrated. If B03 already supplies it, B04 introduces no second
Host behavior change.

## Concurrency, Retry, And Failure Atomicity

MySQL 8.4 `REPEATABLE READ` is the required evidence environment.

- Two concurrent creates for one `(tenant_id, set_id, code)` produce one `201`
  and one `412 REFERENCE_CODE_ALREADY_EXISTS`; no duplicate identity, version,
  or audit row remains.
- Two updates using the same strong ETag produce one `200` and one `412
  REFERENCE_CODE_REVISION_MISMATCH`.
- Update racing retire produces exactly one committed revision; the loser is a
  stale-precondition response, and a committed retirement is terminal.
- A concurrent set retirement or owner Module disablement is serialized by the
  shared definition/Module check and cannot commit a new entry afterward.
- Exact idempotent replay creates no state. A live or expired non-owned
  `processing` record remains `409 IDEMPOTENCY_REQUEST_PROCESSING`; B04 does not
  take it over.
- Failure after entry/version mutation, audit append, or idempotency completion
  rolls back identity, version, audit, and idempotency state together.
- Clients may retry a network failure or 5xx only with the same key, body,
  route, and precondition. A stale precondition requires a fresh GET and a new
  idempotency key.

No B04 write spans another database or connection. Cache, queue, outbox, event
delivery, and distributed transactions are outside this slice.

## Audit And Security Contract

Successful writes append exactly one Tenant audit event in the same PDO
transaction:

| Operation | Event action |
| --- | --- |
| create | `reference-code.created` |
| replace | `reference-code.changed` |
| retire | `reference-code.retired` |

The actor comes only from `TenantContext`. The target resource is
`peanut.reference-code`; target identity is the stable
`module_key/set_key/code` tuple. Metadata contains only Module key, set key,
code, changed-field names, effective status, effective interval, sort order,
and positive revision. It never contains labels, metadata values, request
bodies, cookies, tokens, SQL, private paths, numeric Tenant/member IDs, or
cross-Tenant evidence.

Read operations create no audit row. Existing R02 functional denials and
non-enumerating owner/target failures remain authoritative. An audit write
failure fails and rolls back the command; it never returns an unaudited
success. An exact replay returns the prior receipt and creates no second audit.

Stable Problem Details codes are fixed:

| HTTP | Code | Condition |
| --- | --- | --- |
| 401 | `AUTHENTICATION_REQUIRED` | missing or invalid Tenant authentication |
| 403 | `AUTHZ_PERMISSION_DENIED` | missing read or manage permission |
| 404 | `REFERENCE_CODE_SET_NOT_FOUND` | unknown/retired set or unavailable declaring Module |
| 404 | `REFERENCE_CODE_NOT_FOUND` | unknown, cross-Tenant, or hidden code |
| 409 | `IDEMPOTENCY_KEY_REUSED` | same scoped key with a different canonical request |
| 409 | `IDEMPOTENCY_REQUEST_PROCESSING` | live or expired non-owned processing record |
| 409 | `REFERENCE_CODE_RETIRED` | mutation or recreation of a retired identity |
| 412 | `REFERENCE_CODE_ALREADY_EXISTS` | create precondition finds an active identity |
| 412 | `REFERENCE_CODE_REVISION_MISMATCH` | replace/retire precondition is stale |
| 422 | `IDEMPOTENCY_KEY_INVALID` | invalid idempotency header |
| 422 | `REFERENCE_CODE_REQUEST_INVALID` | unknown/missing field, invalid code, label, status, sort, or query |
| 422 | `REFERENCE_CODE_METADATA_INVALID` | metadata shape, key, scalar, UTF-8, or size is invalid |
| 422 | `REFERENCE_CODE_INTERVAL_INVALID` | invalid, sub-millisecond, or empty effective interval |
| 428 | `PRECONDITION_REQUIRED` | required `If-Match` or `If-None-Match` is absent/invalid |
| 503 | `MODULE_UNAVAILABLE` | failed deployment of the Host or declaring Module |
| 500 | `INTERNAL_ERROR` | generic redacted infrastructure failure |

Errors never expose labels, metadata values, SQL, stack traces, private paths,
raw session data, Provider classes, numeric target IDs, or whether the same
code exists in another Tenant.

## Tenant Web Contract

`@peanut-admin/reference-codes` exports a Tenant-audience Module contribution
and page for `/app/reference-codes`. There is no platform route.

The page provides an owner Module/set selector, fixed as-of input, deterministic
entry table, create form, append-version form, and explicit retire confirmation.
It displays code, effective label, generic active/inactive status, sort order,
effective interval, revision, and retired state. It does not offer hierarchy,
custom status configuration, product examples, bulk mutation, or direct table
editing.

The code field is editable only during create. Replace sends every required
field and the current ETag. A `412` keeps user input, marks the record stale,
and offers an explicit reload; it does not silently merge. Retire is disabled
for an already retired identity and requires a confirmation that identity reuse
is impossible.

The page clears records, drafts, ETags, requests, pagination, selected set,
as-of value, and errors on Tenant switch or Module disposal. Permission denial
prevents the page chunk from loading. The route is absent when
`peanut.reference-codes` is disabled. Desktop `1440x900` and mobile `390x844`
tests require no horizontal document overflow, non-overlapping dialogs, usable
labels, keyboard focus order, and visible stale/error/empty/loading states.

## Exact Implementation File Whitelist

After this contract commit, the B04 implementation commit may add or modify
only the files below. A needed file absent from this list requires a separate
contract amendment before editing.

```text
composer.json
composer.lock
backend/composer.json
deptrac.yaml
phpunit.xml
package.json
pnpm-lock.yaml
playwright.config.ts
README.md
packages/php/kernel/resources/schemas/module-manifest.schema.json
packages/php/kernel/src/Host/AtomicOperationAdapter.php
packages/php/kernel/src/Host/ExternalOperationHost.php
packages/php/kernel/tests/Unit/Host/ExternalOperationHostTest.php
packages/php/kernel/tests/Integration/Host/ExternalOperationHostIntegrationTest.php
packages/php/kernel/tests/Unit/Module/ModuleRegistryCompilerTest.php
packages/php/reference-codes/LICENSE
packages/php/reference-codes/composer.json
packages/php/reference-codes/src/Package.php
packages/php/reference-codes/src/Application/EffectiveReferenceCode.php
packages/php/reference-codes/src/Application/ReferenceCodeAdminService.php
packages/php/reference-codes/src/Application/ReferenceCodeException.php
packages/php/reference-codes/src/Application/ReferenceCodeQuery.php
packages/php/reference-codes/src/Database/Schema.php
packages/php/reference-codes/src/Definition/ReferenceCodeSetDefinition.php
packages/php/reference-codes/src/Definition/ReferenceCodeSetLoader.php
packages/php/reference-codes/src/Definition/ReferenceCodeSetRegistry.php
packages/php/reference-codes/src/Persistence/PdoReferenceCodeRepository.php
packages/php/reference-codes/tests/Unit/Definition/ReferenceCodeSetLoaderTest.php
packages/php/reference-codes/tests/Integration/Application/ReferenceCodeAdminServiceTest.php
packages/php/reference-codes/tests/Integration/Application/ReferenceCodeQueryTest.php
packages/php/reference-codes/tests/Integration/Support/ReferenceCodesDatabaseTestCase.php
packages/php/reference-codes/tests/Integration/Schema/ReferenceCodesMigrationRunner.php
packages/php/reference-codes/tests/Integration/Schema/ReferenceCodesMigrationTest.php
packages/php/reference-codes/tests/Security/ReferenceCodesIsolationTest.php
backend/app/Modules/Peanut/ReferenceCodes/module.json
backend/app/Modules/Peanut/ReferenceCodes/ModuleProvider.php
backend/app/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040101_create_reference_code_sets.php
backend/app/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040102_create_reference_code_entries.php
backend/app/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040103_create_reference_code_entry_versions.php
backend/app/Modules/Peanut/ReferenceCodes/Resources/menus.json
backend/app/Modules/Peanut/ReferenceCodes/Resources/permissions.json
backend/app/Modules/Peanut/ReferenceCodes/Resources/protected-resources.json
backend/app/Modules/Peanut/ReferenceCodes/Resources/reference-code-sets.json
backend/app/controller/api/v1/ReferenceCodeController.php
backend/app/referencecode/ReferenceCodeRuntimeFactory.php
backend/app/command/InstallProductProfileApplier.php
backend/app/command/UpgradeWorkflow.php
backend/config/modules.php
backend/tests/Architecture/ModuleManifestValidationTest.php
backend/tests/Contract/OpenApiArtifactTest.php
backend/tests/Http/ReferenceCodeApiTest.php
backend/tests/Integration/ReferenceCodeModuleIntegrationTest.php
backend/tests/Security/ReferenceCodeSecurityTest.php
backend/tests/Upgrade/ReferenceCodeUpgradeTest.php
backend/tests/Install/InstallWorkflowTest.php
backend/tests/Upgrade/UpgradeWorkflowTest.php
backend/tests/Install/ProductProfileTest.php
backend/tests/Install/InstallWorkflowIntegrationTest.php
backend/tests/Upgrade/UpgradeWorkflowIntegrationTest.php
profiles/reference-admin.json
packages/web/reference-codes/LICENSE
packages/web/reference-codes/package.json
packages/web/reference-codes/tsconfig.json
packages/web/reference-codes/src/contracts.ts
packages/web/reference-codes/src/index.ts
packages/web/reference-codes/src/runtime.ts
packages/web/reference-codes/src/ReferenceCodesPage.vue
packages/web/reference-codes/tests/contracts.spec.ts
packages/web/reference-codes/tests/page.spec.ts
frontend/package.json
frontend/src/app/routes.ts
frontend/src/modules/peanut-reference-codes/index.ts
frontend/src/modules/peanut-reference-codes/routes.ts
frontend/tests/reference-codes-page.spec.ts
frontend/tests/e2e/reference-codes.e2e.ts
frontend/tests/e2e/full-stack.e2e.ts
frontend/tests/fixtures/api.ts
frontend/tests/fixtures/full-stack-setup.php
frontend/tests/fixtures/full-stack.ts
frontend/tests/fixtures/full-stack-vite.config.ts
docs/api/openapi.yaml
docs/api/schemas/reference-codes.yaml
backend/route/openapi-generated.php
packages/web/admin-core/src/generated/api.d.ts
docs/status/runtime-operation-coverage.json
docs/api/index.md
docs/decisions/dependencies/index.md
docs/decisions/dependencies/p1-b04-lock-evidence.json
docs/reference/packages/reference-codes.md
docs/reference/third-party-licenses.generated.md
docs/guide/module-development.md
docs/content-status.json
docs/status/index.md
docs/status/p1-b04-minimal-reference-codes-contract.md
scripts/check-openapi
scripts/check
scripts/check-workspace
scripts/create-internal-starter
scripts/require-p1-b04-environment
scripts/install
scripts/backup-mysql
scripts/backup-mysql-metadata
scripts/upgrade
scripts/health-check
scripts/restore-mysql
scripts/verify-clean-install
scripts/verify-doc-examples
scripts/verify-internal-starter
scripts/test-unit
scripts/test-integration
scripts/test-security
scripts/test-browser
scripts/test-performance
scripts/test-recovery
scripts/verify-recovery
starter/backend/composer.json
starter/backend/composer.lock
starter/backend/config/modules.php
starter/backend/src/Module/ModuleRegistryFactory.php
starter/backend/src/Modules/Peanut/ReferenceCodes/module.json
starter/backend/src/Modules/Peanut/ReferenceCodes/ModuleProvider.php
starter/backend/src/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040101_create_reference_code_sets.php
starter/backend/src/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040102_create_reference_code_entries.php
starter/backend/src/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040103_create_reference_code_entry_versions.php
starter/backend/src/Modules/Peanut/ReferenceCodes/Resources/menus.json
starter/backend/src/Modules/Peanut/ReferenceCodes/Resources/permissions.json
starter/backend/src/Modules/Peanut/ReferenceCodes/Resources/protected-resources.json
starter/backend/src/Modules/Peanut/ReferenceCodes/Resources/reference-code-sets.json
starter/backend/tests/auth-clients.php
starter/backend/tests/reference-codes.php
starter/backend/tests/smoke.php
starter/frontend/package.json
starter/frontend/src/app/modules.ts
starter/frontend/src/modules/peanut-reference-codes.ts
starter/frontend/tests/reference-codes.spec.ts
starter/pnpm-lock.yaml
tests/performance/run.php
tests/recovery/RecoveryAcceptanceTest.php
tests/recovery/seed-recovery-fixture.php
tests/security/g07-evidence.json
```

The implementation-time contract amendment adds only the paths required to
close independently reviewed qualification gaps. The `reference-code.*` event
names use the existing Kernel audit grammar; underscore names are invalid and
must not be implemented by weakening the generic audit contract. The added
shell entrypoints must source `scripts/require-p1-b04-environment` before any
Compose, filesystem artifact, or database action. That preflight accepts only
the exact ten-value environment block in this contract. The added PHP direct
runners must perform the equivalent fail-closed checks before opening a
database connection.

The two added starter composition paths are limited to replacing the previous
single-fictional-Module assumption with the exact configured Module roots,
frontend components, and Client keys, and to verifying the resulting package
and Module inventory. They may not add a committed reference-code set or
value, application taxonomy, product behavior, or another starter capability.

The implementation must not modify the qualified downstream lock, R01/R02
behavior beyond the explicit same-PDO declaration guard, B03 package source,
B03 migrations, B03 Web source, `example.reference`, any application product
repository, parent-repository Patch, release file, unlisted generated artifact,
or package publication metadata.

## Aggregate Environment Fail-Closed Amendment

Independent review found that the first implementation amendment closed the
B04-owned shell and direct-runner fallbacks but did not close inherited PHPUnit
database fallbacks or the Compose port defaults reached by the aggregate gate.
Those paths could therefore connect to an unintended namespace when a focused
PHPUnit command bypassed its shell wrapper or when `docker compose` was invoked
directly with an incomplete environment.

This amendment adds only the following qualification-support paths to the
implementation whitelist:

```text
compose.yaml
tests/p1-b04-environment.php
backend/tests/Integration/AccountSelfServiceHttpIntegrationTest.php
backend/tests/Integration/EffectiveAccessPreviewHttpIntegrationTest.php
backend/tests/Integration/ExampleModuleHttpIntegrationTest.php
backend/tests/Integration/ExampleModuleQueryIntegrationTest.php
examples/module-contract/ExampleModuleContractTest.php
packages/php/kernel/tests/Integration/Auth/TenantAuthServiceIntegrationTest.php
packages/php/kernel/tests/Integration/Idempotency/IdempotencyRepositoryTest.php
packages/php/kernel/tests/Integration/Identity/AccountSelfServiceIntegrationTest.php
packages/php/kernel/tests/Integration/Schema/DatabaseTestCase.php
packages/php/data-permission/tests/Integration/Application/DataPolicyAdminServiceTest.php
packages/php/data-permission/tests/Integration/Application/EffectiveAccessPreviewServiceTest.php
packages/php/data-permission/tests/Integration/Engine/DataPermissionEngineTest.php
packages/php/data-permission/tests/Integration/Schema/DataPermissionSchemaTest.php
packages/php/data-permission/tests/Security/AuthorizationPathParityTest.php
```

`phpunit.xml` remains the only allowed PHPUnit configuration change and must
load `tests/p1-b04-environment.php`. That bootstrap must validate the exact ten
qualification variables before loading project autoload code. Inherited PHP
integration and security tests must consume the validated `DB_PORT` directly;
they must not retain a `3306`, R02, or `MYSQL_PORT`-derived fallback. Unit and
aggregate wrapper commands must source the exact B04 environment preflight.

`compose.yaml` must require explicit MySQL, cache, backend, and frontend ports,
database name, and the matching qualification `DB_PORT`; it must not retain a
default host port or database namespace. Default credentials remain local
development inputs and are not qualification namespace fallbacks.

The negative environment test must cover the shared PHPUnit bootstrap and
direct Compose interpolation in addition to the previously listed entrypoints.
This amendment does not authorize changes to P0 Runtime connection defaults,
application behavior, downstream product logic, the qualified lock, or any
product repository.

## Parallel Implementation Ownership

After this amendment is committed and independently reviewed, three worker
slices may run in parallel from exact R02 commit
`3ab9a7ddf7488a9cc941b4c4f8fa9ba25470a9ad`. The write sets below are
mutually exclusive. A worker must not edit a path owned by another worker or by
the single integrator.

The PHP package worker owns only:

```text
packages/php/reference-codes/LICENSE
packages/php/reference-codes/composer.json
packages/php/reference-codes/src/Package.php
packages/php/reference-codes/src/Application/EffectiveReferenceCode.php
packages/php/reference-codes/src/Application/ReferenceCodeAdminService.php
packages/php/reference-codes/src/Application/ReferenceCodeException.php
packages/php/reference-codes/src/Application/ReferenceCodeQuery.php
packages/php/reference-codes/src/Database/Schema.php
packages/php/reference-codes/src/Definition/ReferenceCodeSetDefinition.php
packages/php/reference-codes/src/Definition/ReferenceCodeSetLoader.php
packages/php/reference-codes/src/Definition/ReferenceCodeSetRegistry.php
packages/php/reference-codes/src/Persistence/PdoReferenceCodeRepository.php
packages/php/reference-codes/tests/Unit/Definition/ReferenceCodeSetLoaderTest.php
packages/php/reference-codes/tests/Integration/Application/ReferenceCodeAdminServiceTest.php
packages/php/reference-codes/tests/Integration/Application/ReferenceCodeQueryTest.php
packages/php/reference-codes/tests/Integration/Support/ReferenceCodesDatabaseTestCase.php
packages/php/reference-codes/tests/Integration/Schema/ReferenceCodesMigrationRunner.php
packages/php/reference-codes/tests/Integration/Schema/ReferenceCodesMigrationTest.php
packages/php/reference-codes/tests/Security/ReferenceCodesIsolationTest.php
docs/reference/packages/reference-codes.md
```

The Web package worker owns only:

```text
packages/web/reference-codes/LICENSE
packages/web/reference-codes/package.json
packages/web/reference-codes/tsconfig.json
packages/web/reference-codes/src/contracts.ts
packages/web/reference-codes/src/index.ts
packages/web/reference-codes/src/runtime.ts
packages/web/reference-codes/src/ReferenceCodesPage.vue
packages/web/reference-codes/tests/contracts.spec.ts
packages/web/reference-codes/tests/page.spec.ts
frontend/src/modules/peanut-reference-codes/index.ts
frontend/src/modules/peanut-reference-codes/routes.ts
frontend/tests/reference-codes-page.spec.ts
frontend/tests/e2e/reference-codes.e2e.ts
```

The Host/API worker owns only:

```text
backend/app/Modules/Peanut/ReferenceCodes/module.json
backend/app/Modules/Peanut/ReferenceCodes/ModuleProvider.php
backend/app/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040101_create_reference_code_sets.php
backend/app/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040102_create_reference_code_entries.php
backend/app/Modules/Peanut/ReferenceCodes/Database/Migrations/20260719040103_create_reference_code_entry_versions.php
backend/app/Modules/Peanut/ReferenceCodes/Resources/menus.json
backend/app/Modules/Peanut/ReferenceCodes/Resources/permissions.json
backend/app/Modules/Peanut/ReferenceCodes/Resources/protected-resources.json
backend/app/Modules/Peanut/ReferenceCodes/Resources/reference-code-sets.json
backend/app/controller/api/v1/ReferenceCodeController.php
backend/app/referencecode/ReferenceCodeRuntimeFactory.php
backend/tests/Http/ReferenceCodeApiTest.php
backend/tests/Integration/ReferenceCodeModuleIntegrationTest.php
backend/tests/Security/ReferenceCodeSecurityTest.php
docs/api/schemas/reference-codes.yaml
```

The single integrator owns every remaining path in the exact implementation
whitelist. Only the integrator may modify shared manifests, dependency locks,
Kernel/R02 bindings, install and upgrade workflows, OpenAPI and generated
artifacts, the Runtime ledger, shared frontend fixtures, scripts, starter
composition, evidence, and status documents. The integrator may apply reviewed
worker commits but must not manually rewrite worker-owned source before review.

## Test Counts, Ownership, And Evidence

`RUNTIME-REFERENCE-CODES-001` owns all six operations. Tests are written
failing before source implementation. The focused candidate contains at least
the following PHPUnit/Vitest/Playwright reported test-case counts; parameterized
datasets count as reported test cases, every suite has zero skips, and the
final evidence records actual test and assertion counts rather than replacing
them with these floors.

| Evidence file | Minimum test cases | Required focus |
| --- | ---: | --- |
| `packages/php/reference-codes/tests/Unit/Definition/ReferenceCodeSetLoaderTest.php` | 12 | owner, grammar, duplicates, unknown fields, digest, retirement/reactivation |
| `packages/php/reference-codes/tests/Integration/Schema/ReferenceCodesMigrationTest.php` | 8 | three tables, fields, indexes, checks, FKs, order, repeat, empty down |
| `packages/php/reference-codes/tests/Integration/Application/ReferenceCodeAdminServiceTest.php` | 18 | create, replace, retire, preconditions, immutable code, metadata, intervals, rollback |
| `packages/php/reference-codes/tests/Integration/Application/ReferenceCodeQueryTest.php` | 14 | as-of boundaries, overlap precedence, inactive, retired history, ordering, corruption |
| `packages/php/reference-codes/tests/Security/ReferenceCodesIsolationTest.php` | 10 | two-Tenant isolation, context-only owner, Module state, redaction, enumeration |
| `backend/tests/Integration/ReferenceCodeModuleIntegrationTest.php` | 10 | R02 permission, audit, idempotency, one-PDO failure injection, owner guard |
| `backend/tests/Http/ReferenceCodeApiTest.php` | 12 | six routes, exact shapes/headers, query rejection, Problem Details |
| `backend/tests/Upgrade/ReferenceCodeUpgradeTest.php` | 6 | clean install, exact migration inventory, repeat, rollback restore, old-lock code |
| `packages/web/reference-codes/tests/contracts.spec.ts` | 4 | generated client and contribution contract |
| `packages/web/reference-codes/tests/page.spec.ts` | 4 | guard, edit, stale reload, Tenant cleanup |
| `frontend/tests/reference-codes-page.spec.ts` | 2 | route registration and permission denial |
| `frontend/tests/e2e/reference-codes.e2e.ts` | 2 | real-backend desktop and mobile workflow |

The focused floor is therefore `102` reported test cases with `0` skipped.
The implementation report also records assertion counts, duration, MySQL
version, PHP version, Node/pnpm versions, browser projects, commit, tree, and
the exact environment block below for every command.

The isolated namespace is fixed:

```text
compose_project: peanut-admin-p1-b04
mysql_port: 33404
cache_port: 36404
backend_port: 38104
frontend_port: 35204
browser_backend_port: 38204
browser_frontend_port: 35304
database: peanut_admin_p1_b04_reference_codes_test
```

The required command environment is:

```bash
export COMPOSE_PROJECT_NAME=peanut-admin-p1-b04
export MYSQL_PORT=33404
export CACHE_PORT=36404
export BACKEND_PORT=38104
export FRONTEND_PORT=35204
export PEANUT_BROWSER_BACKEND_PORT=38204
export PEANUT_BROWSER_FRONTEND_PORT=35304
export MYSQL_DATABASE=peanut_admin_p1_b04_reference_codes_test
export DB_HOST=127.0.0.1
export DB_PORT=33404
```

Every focused and aggregate command must receive the complete environment
block. `MYSQL_PORT` and `DB_PORT` must both equal `33404`; omission, mismatch,
an invalid port, or fallback to `3306` is an environment failure and produces
no qualification evidence. `DB_HOST` must be the explicit loopback value above.
`scripts/check` and each integration, security, browser, recovery, performance,
and starter entrypoint and direct runner must validate the required ports before
starting Compose or opening a database connection.

`playwright.config.ts` must read both browser port variables and must not retain
a fixed backend port. With dependencies already installed from the committed
locks, focused verification uses these exact commands:

```bash
docker compose up -d mysql cache

php vendor/bin/phpunit \
  packages/php/reference-codes/tests/Unit

PEANUT_INTEGRATION=1 php vendor/bin/phpunit \
  packages/php/reference-codes/tests/Integration \
  packages/php/reference-codes/tests/Security \
  backend/tests/Integration/ReferenceCodeModuleIntegrationTest.php \
  backend/tests/Http/ReferenceCodeApiTest.php \
  backend/tests/Security/ReferenceCodeSecurityTest.php

corepack pnpm --filter @peanut-admin/reference-codes test
corepack pnpm exec vitest run frontend/tests/reference-codes-page.spec.ts
./scripts/test-browser frontend/tests/e2e/reference-codes.e2e.ts

./scripts/verify-clean-install
PEANUT_INTEGRATION=1 php vendor/bin/phpunit \
  backend/tests/Upgrade/ReferenceCodeUpgradeTest.php

./scripts/check-openapi
./scripts/check-runtime-coverage
PEANUT_RUNTIME_STAGE=runtime ./scripts/check-architecture
./scripts/check-dependency-decisions
./scripts/check-doc-content-status
./scripts/check-docs
./scripts/check-workspace
git diff --check
./scripts/check
```

`ReferenceCodeUpgradeTest.php` owns setup of the exact old-lock worktree,
backup, candidate upgrade, old-code execution, restore target, and cleanup. It
must call the repository `./scripts/install`, `./scripts/backup-mysql`,
`./scripts/upgrade`, `./scripts/health-check`, and `./scripts/restore-mysql`
entry points rather than reimplementing their behavior in the test.

Focused verification runs first in this order:

```text
1. PHP definition unit tests
2. PHP schema/admin/query integration tests on MySQL 8.4
3. PHP isolation and Host/HTTP security tests
4. Web package and reference-host page unit tests
5. real-backend desktop and mobile reference-code E2E
6. clean install, upgrade, rollback restore, and old-lock compatibility
7. OpenAPI, Runtime coverage, generated artifacts, architecture, dependency,
   license, secret, documentation, formatting, static analysis, and diff checks
8. isolated ./scripts/check after the fixed candidate tree is clean
```

Final pure-branch acceptance requires exactly `75 P0 + 10 P1 = 85` OpenAPI
operations and six B04 ledger entries owned by `RUNTIME-REFERENCE-CODES-001`.
After serial B03+B04 integration, counts are regenerated from the integrated
tree and expected to be exactly `75 P0 + 16 P1 = 91`; a mismatch stops work.
All four security JUnit groups report zero skips. Desktop and mobile E2E use a
real backend with no `/api/**` interception and no page or console error.

## Install, Upgrade, Rollback, And Old-Lock Compatibility

Clean-install evidence must prove:

- the three B04 migrations run after Kernel/data-permission and in compiled
  Module dependency order;
- the ProductProfile enables `peanut.reference-codes` without creating a code
  set or entry value;
- the starter installs with zero committed reference values and can create one
  synthetic test-only set declaration and Tenant entry through package APIs;
- immediate `./scripts/upgrade` applies zero additional migrations and changes
  zero definitions;
- health, login, Tenant selection, all P0 routes, and the qualified external
  host fixture remain operational, and the four inherited P1 Runtime routes
  remain unchanged.

Upgrade evidence starts from a database installed by exact commit
`0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`, takes and verifies a backup, then
runs the candidate `./scripts/upgrade`. It must record the pre/post table and
migration inventory, apply exactly the three B04 migrations once, preserve all
pre-existing hashes and Tenant rows, create no B04 entry values, and make a
second upgrade a zero-change operation.

Old-lock compatibility then runs the exact old code commit
`0ab02a9b735ba9f4c23509cb366b9bf04039ebf8` against the upgraded database. It
must pass health, Tenant/platform login, all 75 P0 routes, Tenant isolation,
and the qualified external-host starter path. The old lock contains no P1
Runtime operation and is not required to know or expose B04 routes; additional
B04 tables and Module migration records must not make old code fail.

Rollback evidence restores the verified pre-upgrade backup into a new database
name, points the old code commit at that restored database, and reruns health,
login, isolation, P0 routes, and external-host checks. It never drops the
active database, edits migration history, deletes B04 rows to simulate success,
or invokes B04 `down()` against operational data.

The implementation evidence records the exact commands and environment, backup
manifest/checksum paths, source and restored database names, old/new commits
and trees, migration counts, test/assertion/skip counts, durations, and exit
codes. Credentials, cookies, tokens, and local private paths are redacted.

## Integration Sequence And Candidate Stop Line

The single integrator performs this exact sequence:

1. Review and fix this documentation-only contract without implementation.
2. Build the B04 candidate from exact R02 commit
   `3ab9a7ddf7488a9cc941b4c4f8fa9ba25470a9ad` in the isolated namespace.
3. Run focused B04 tests and inspect the independent implementation commit.
4. Integrate the already-reviewed B03 implementation before B04 shared files.
5. Apply B04 package, Module, migration, and test files, then resolve only the
   declared shared manifests, locks, OpenAPI, generated artifacts, Runtime
   ledger, starter, scripts, and status documents.
6. Regenerate all shared artifacts from the integrated source tree and run the
   integrated `91`-operation checks; never retain pure-branch generated output.
7. Record the resulting fixed commit/tree and stop for P1-Q01 qualification.

B04 implementation is one independently reviewable candidate commit after the
canonical contract commit. Serial integration may create a separate explicit
integration commit; it must not squash away B03 or B04 evidence.

Completion makes B04 and the integrated P1 tree only unqualified candidates.
It does not move
`0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`, authorize a downstream consumer,
approve consumption, publish a package, create a tag or release, claim
production readiness, or treat Q01 qualification as consumption approval.

P1-Q01 begins only from the single integrator's clean fixed commit after B03
and B04 are integrated. Q01 fixed-commit qualification, independent review,
and any later downstream-consumption approval are three separate decisions.
