# P1-C03 Ops Console Contract

## Status And Input

`PA-SV1-C03-C02-ops-console` is a development-only feature lane based on
`8c0d59b4c972f84423a938b78cfa82b1b0fec058` and tree
`b908bb46992018cfe7a29cb99e9e2d3bd23e4f97`. It owns reusable PHP and Web
package contracts. It does not make the Ops Console reachable from a Host and
does not qualify a release or downstream-consumption commit.

## Objective And Stop Lines

An authenticated platform operator may inspect application health, deployed
version, migration evidence, existing upgrade preflight evidence, approved
backup/restore tasks, the maintenance window, and structured redacted runtime
events. The PHP package composes Kernel platform context and first-party
Task/Job vocabulary. It does not add another worker or queue.

The feature never accepts or exposes a client-supplied command, executable,
SQL statement, DSN, credential, token, filesystem path, database name,
environment variable, stack trace, request body, or raw log line. It does not
download or deploy code, replace the existing upgrade lifecycle, operate a
remote fleet, or restore over the active database. It cannot claim restore
success without restore-to-new-target verification.

This lane adds no database migration, Module, Host controller, OpenAPI
operation, generated artifact, Runtime ledger row, route, menu, shell
registration, starter file, dependency lock, or shared manifest.

## Audience And Permissions

Every service method receives only trusted `PlatformContext`. Tenant context,
account/operator identifiers, and permission overrides are never request
inputs. The I05 Host adapter must bind the existing
`PlatformAuthorizationEvaluator` and fail closed on missing session,
permission, provider, dispatcher, repository, or audit sink.

| Permission | Purpose |
| --- | --- |
| `platform.ops.read` | read health, version, migration, upgrade and task evidence |
| `platform.ops.backup.manage` | submit an approved backup task |
| `platform.ops.restore.manage` | submit restore-to-new-target-and-verify |
| `platform.ops.maintenance.manage` | schedule, replace, or close maintenance |
| `platform.ops.logs.read` | read structured redacted runtime/error events |

No permission implies another.

## Status Evidence

One server-registered `RuntimeStatusProvider` supplies an immutable snapshot:

- health is `healthy`, `degraded`, or `unhealthy`; each bounded check contains
  only key, `up`/`down`, criticality, and latency;
- version contains commit, tree, optional release key, and build instant;
- migration evidence contains applied/target/pending counts, inventory digest,
  and drift boolean;
- upgrade evidence contains state, stable code, optional source/target commit,
  and repository/backup/source-evidence booleans.

The I05 adapter reuses `HealthReport`, `UpgradeStatusService`, release and
backup manifests, and migration inventory. The package never reads configured
manifest paths. Invalid or unavailable evidence is
`503 OPS_STATUS_UNAVAILABLE`; partial data is not returned as healthy.

## Backup, Restore, And Tasks

`BackupRestoreProviderRegistry` is the only provider authority. Provider keys,
trusted handler keys, capabilities, and allowed restore target keys are fixed
at server startup; malformed or duplicate registrations fail startup. The
client may select only a registered provider and registered target key.

Backup has no artifact-path input. Restore accepts only a bounded opaque backup
reference key and a registered target key. The provider mode is permanently
`restore_to_new_target_and_verify`; no overwrite-current mode exists. The I05
adapter must reject an active or existing target and unverified restore.

Only these trusted task types and payloads exist:

| Task type | Payload |
| --- | --- |
| `ops.backup.create` | registered provider key |
| `ops.restore.verify` | provider key, backup reference key, target key |

The provider supplies the handler key. A client cannot supply a handler,
command, arguments, retry count, delay, path, or arbitrary payload. I05 must
reuse Task/Job claim, lease, bounded retry, stable error, and side-effect
idempotency behavior while keeping platform authorization separate from
Tenant Task/Job envelopes.

Public task status uses `queued`, `running`, `succeeded`, `dead`, or
`cancelled`, attempt/max counts, revision, stable last-error code, and UTC
millisecond timestamps. Payload and provider receipt are never public.

Every submission requires an 8-200 byte printable idempotency key. Only its
SHA-256 digest crosses the package boundary. The package also produces a
canonical request digest. The dispatcher atomically replays the original task
for the same scope/key/request digest, returns
`409 OPS_IDEMPOTENCY_CONFLICT` for a different request, and allows at most one
active operation/provider task (`409 OPS_OPERATION_IN_PROGRESS`).

## Maintenance Window

A window has an opaque `maintenance_` key, `scheduled`, `active`, or `closed`
state, a server-registered reason key, UTC millisecond start/end, and positive
revision. Duration is greater than zero and no more than 24 hours. Creation
uses expected revision zero; replacement/closure requires the exact strong
revision. At most one scheduled/active window exists. The repository owns
atomic revision and idempotency checks; stale or concurrent writes return
`409 OPS_REVISION_CONFLICT`. The client cannot submit free-form maintenance
text, a Tenant, command, or path.

## Structured Redacted Logs

`RuntimeLogProviderRegistry` is the only source authority. A query selects a
registered source key, severity, opaque cursor, and page size 1-100. It cannot
select a filename, stream, command, host, or query expression.

Providers emit only event key, severity, component key, UTC instant, optional
request ID, and occurrence count. A server-owned catalog maps event key to safe
text; unknown keys receive a generic message. Provider text and metadata are
discarded. PHP and Web validators reject extra fields and values resembling
credentials, DSNs, SQL, absolute paths, stacks, userinfo URLs, or control
characters.

## Audit And Problems

Successful writes append `platform.ops.backup.submitted`,
`platform.ops.restore.submitted`, `platform.ops.maintenance.scheduled`, or
`platform.ops.maintenance.closed`. Metadata is limited to provider/task/target/
maintenance keys, revision, and request/idempotency digests. Audit failure
rejects or rolls back the operation; it is never ignored.

| HTTP | Stable code |
| --- | --- |
| 400 | `OPS_REQUEST_INVALID` |
| 403 | `OPS_PERMISSION_DENIED` |
| 404 | `OPS_PROVIDER_NOT_FOUND`, `OPS_TASK_NOT_FOUND` |
| 409 | `OPS_IDEMPOTENCY_CONFLICT`, `OPS_OPERATION_IN_PROGRESS`, `OPS_REVISION_CONFLICT` |
| 422 | `OPS_RESTORE_TARGET_INVALID`, `OPS_MAINTENANCE_INVALID` |
| 503 | `OPS_STATUS_UNAVAILABLE`, `OPS_PROVIDER_UNAVAILABLE`, `OPS_TASK_UNAVAILABLE`, `OPS_LOGS_UNAVAILABLE` |
| 500 | `OPS_INTERNAL_ERROR` |

Problem detail is generic and never contains provider failures or sensitive
runtime evidence.

## Web Contract

The feature-local Web package supplies exact response parsers, a fixed
platform transport interface, isolated runtime state, and responsive overview,
recovery, maintenance, and log views. It derives permissions from the platform
shell adapter, accepts no Tenant input, and never renders server `detail` text.
Only generic local text, stable problem code, status, and request ID may appear.
I05 owns route and shell registration.

## File Whitelist

```text
packages/php/ops-console/**
packages/web/ops-console/**
docs/status/p1-c03-ops-console-contract.md
docs/content-status.json
```

Kernel, Task/Job, Host, Module manifests, migrations, shared manifests/locks,
OpenAPI/generated/Runtime, router, shell, canonical starter, package identity,
and generator baseline must not change.

## I05 Handoff And Deferred Gates

I05 owns platform permission synchronization; Host status/provider/TaskJob/
audit/maintenance/log adapters and schema; HTTP/OpenAPI/generated/Runtime;
route/shell/menu; shared manifests/locks; canonical starter; and generator
reseal. It also owns real migration/upgrade, backup and restore-to-new-target
verification, clean install, recovery, aggregate, browser, performance, and
cross-OS gates.

This lane stops at a clean development commit and runs no real backup, restore,
upgrade, production command, browser matrix, aggregate gate, release, or
downstream lock movement.
