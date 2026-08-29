# P1-ED01 Edition persistence scope contract

Status: approved application prerequisite; implementation candidate, not published

Prerequisite: `8608dafe30467c442000ce408b106d8750ffd766`

## Objective and non-goals

Provide a product-neutral persistence mode for the existing Idempotency, Task/Job and Import/Export
repositories and schema exporters. `tenant-scoped` preserves every current SQL predicate, field,
foreign key and DTO behavior. `instance-scoped` keeps the trusted logical Tenant context used by
commands and audit envelopes, but omits the Tenant ownership column from storage, SQL predicates,
indexes and foreign keys.

This task does not add Peanut Admin Edition or deployment-mode names to Core, change identity,
authorization, Module, audit or business workflows, convert data between modes, add a fallback, or
publish a package. The default constructor and schema API remain `tenant-scoped`.

## Data and behavior contract

- `pa_tenant_idempotency_record`: instance scope omits `tenant_id`; uniqueness becomes
  `(tenant_member_id, operation_key, idempotency_key_hash)` and the member foreign key references
  globally unique `pa_tenant_member.id`.
- `pa_task_job`: instance scope omits `tenant_id`; idempotency and claim indexes begin with their
  remaining business columns; member ownership references `pa_tenant_member.id`.
- `pa_task_job_attempt` and `pa_task_job_event`: instance scope omits `tenant_id`; uniqueness,
  lookup indexes and job/member foreign keys use globally unique IDs.
- `pa_import_export_operation`: instance scope omits `tenant_id`; idempotency, status and task indexes
  use the remaining columns; member ownership references `pa_tenant_member.id`.
- `pa_import_export_row_error`: instance scope omits `tenant_id`; uniqueness, lookup and operation
  foreign keys use globally unique IDs.
- Repository public operations keep their current trusted logical Tenant input. In instance scope it
  must equal the fixed logical Tenant ID supplied at repository construction, is returned in existing
  records/envelopes, and is never bound to SQL or read from a storage column. A missing fixed ID or a
  different context fails closed; a caller cannot select or relabel the single physical partition.
- Transactions, locking, lease fences, idempotency replay, retries, checksums, retention and state
  transitions are unchanged. Mode mismatch, invalid logical context or missing expected columns fails
  closed before a repository operation can modify data. Repositories validate their owned tables against
  the explicitly selected mode; they do not auto-detect, switch modes or issue a compatibility query.

There is no migration in this repository. The consuming application owns Edition-specific fresh
Schema and upgrade migrations. Downgrade and cross-mode conversion are outside this task.

## Exact file whitelist

- `packages/php/kernel/src/Persistence/Tenancy/TenantPersistenceMode.php`
- `packages/php/kernel/src/Persistence/Tenancy/TenantColumnScope.php`
- `packages/php/kernel/src/Idempotency/IdempotencySchema.php`
- `packages/php/kernel/src/Idempotency/PdoIdempotencyRepository.php`
- `packages/php/kernel/tests/Integration/Idempotency/IdempotencyRepositoryTest.php`
- `packages/php/task-job/src/Database/Schema.php`
- `packages/php/task-job/src/Persistence/PdoTaskJobRepository.php`
- `packages/php/task-job/tests/feature-harness.php`
- `packages/php/import-export/src/Database/Schema.php`
- `packages/php/import-export/src/Persistence/PdoImportExportRepository.php`
- `packages/php/import-export/tests/feature-harness.php`
- `docs/architecture/edition-persistence-scope.md`
- `docs/architecture/index.md`
- `docs/content-status.json`
- `docs/reference/document-catalog.generated.md`
- `docs/status/p1-ed01-edition-persistence-scope-contract.md`
- `docs/status/index.md`

Package versions, dependency locks, Kernel identity/RBAC Schema, HTTP/OpenAPI, Web, starter, Module
manifests and unrelated status files must not change.

## Acceptance and verification owner

The Core integration owner adds instance-scope coverage to the three existing repository test owners.
The same public operations must pass once against tenant-scoped Schema and once against
instance-scoped Schema, with explicit information-schema assertions that the latter six tables contain
no `tenant_id` field, index component or foreign-key component. Cross-Tenant isolation assertions stay
on tenant scope; instance scope proves there is no Tenant SQL binding and preserves state-machine,
locking, replay and recovery behavior.

Run one consolidated focused group for the three package owners, then `git diff --check` and Core docs
governance. Full `./scripts/check`, package publication and downstream qualification are deferred to the
fixed release candidate. A second failure of the consolidated group blocks this task.

The implementation is one reviewable PR to `dev`. Its merge is not a downstream dependency lock.
Peanut Admin may consume it formally only after a separately approved fixed Core version is published.
