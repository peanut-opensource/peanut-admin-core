# P1-CAP02 Artifact Revision Contract

## Status

```text
task: P1-CAP02
state: accepted implementation contract
prerequisite_commit: d6e636f10d7c539731963b394221e8eca0997816
prerequisite_tree: 59619a21e1c51a96fc76f04a0e8c636b6ce627f6
public_boundary: peanut-admin/core
target_candidate: peanut-admin/core@0.1.0-alpha.5, Composer-only and unpublished
dependency_change: none
http_api: none; each Host owns routes, OpenAPI and Runtime coverage
test_owner: P1-ARTIFACT-REVISION-001
qualification: deferred to P1-CROSS-PRODUCT-QUALIFICATION-001 / CAP05
downstream_adoption: deferred to CAP06
publication_authorized: false
```

CAP01 closed through Core PR #7 and the mechanical R04 follow-up PR #8. The
six repository checks passed on the R04 tree before PR #8 produced the exact
merge commit above. CAP02 starts only from that merge, not from a branch, the
older Workflow source commit or a moving package projection.

## Objective And Non-Goals

CAP02 adds a product-neutral Tenant authority for immutable artifact revisions.
It owns an artifact identity, a pending revision reservation, one-time
finalization, a canonical immutable envelope, parent lineage and comparison.
It also implements the existing Workflow subject-revision port without making
Workflow depend directly on ArtifactRevision tables or classes.

A Host owns its payload table, payload schema and business validation. It
authorizes a typed artifact target before calling CAP02, persists its payload
through the same caller-owned PDO transaction when atomic composition is
required, and exposes any HTTP, OpenAPI, Web, event or product projection.

CAP02 does not add media Article/editor/body/publishing/review rules; a generic
document/blob/schema/diff engine; collaboration/CRDT/presence/realtime;
Workflow or File/Media source; delete/retention/legal-hold/restore policy; Host
HTTP/Web/npm; dependencies; qualification, adoption, publication or a
downstream-consumption candidate.

## Existing Owners And Required Reuse

| Concern | Existing authority | CAP02 rule |
| --- | --- | --- |
| Tenant and actor | Kernel `TenantContext` | Tenant/member/account/request come only from `AuthorizedOperationContext`. |
| Authorization | Kernel authorized operation and typed targets | Accept exactly one already authorized primary target whose resource key/ID equal the artifact type/key. Mismatch fails as not found. |
| Transactions | Kernel `PdoTransactionManager` | Every write uses the caller PDO and supports an outer transaction through savepoints. |
| Idempotency | Kernel `PdoIdempotencyRepository` | Create/finalize use Tenant/member scope, canonical request hashes and replay stored receipts. |
| Audit | Kernel `PdoAuditRepository` | Successful writes append redacted Tenant-member evidence in the same transaction. |
| Host payload | Host Module | Store only an opaque payload reference, schema identity and SHA-256; no product payload or cross-owner FK. |
| Files | File/Media | Store only a Host-issued attachment-manifest SHA-256. File/Media owns access, snapshots and binaries. |
| Approval | Workflow | Workflow keeps using `WorkflowSubjectRevisionResolver` and stores only the finalized key/envelope digest. |

All CAP02 production adapters expose their PDO. A Host needing payload/revision
atomicity starts one outer transaction on that PDO, writes its validated
immutable payload, calls CAP02 and commits. CAP02 must not open a second
connection or degrade to best-effort composition.

## PHP API Contract

`ArtifactRevisionService` exposes exactly four product-neutral operations:

| Operation | Contract |
| --- | --- |
| `createRevision` | Reserve one pending revision for an authorized Tenant artifact, optionally naming a finalized same-artifact parent; require current artifact optimistic revision when identity already exists. |
| `finalizeRevision` | One-time finalize a pending revision using expected artifact/revision values and Host schema/ref/digests; construct and persist the exact canonical envelope. |
| `revision` | Read one authorized revision by artifact type/key/revision key without enumerating another Tenant or artifact. |
| `compare` | Compare two finalized revisions of one authorized artifact as `same`, `ancestor`, `descendant` or `diverged`; never compare payload bodies. |

Create/finalize require an idempotency key; queries do not. Every call requires
positive trusted Tenant/member/account IDs, non-empty request/resource/
operation, and exactly one primary target whose resource key equals
`artifact_type` and sole ID equals `artifact_key`.

Artifact-type and schema identifiers match
`[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*` and are at most 64 characters. Artifact
keys and payload references are bounded to 128/512 ASCII characters; schema
versions to 32 ASCII characters; digests are lowercase 64-hex. The service
generates `revision_<32 lowercase hex>` keys.

The first revision may omit a parent. A named parent must be finalized, belong
to the same Tenant/artifact and have a smaller revision number. Locked rows and
optimistic values permit one concurrent winner. A finalized row never returns
to pending or accepts a second envelope, even if byte-identical.

The canonical envelope contains exactly these sorted semantic fields:

```text
artifact_type, artifact_key, revision_key, revision_number,
parent_revision_key, payload_schema_key, payload_schema_version,
payload_ref, payload_sha256, attachment_manifest_sha256
```

Null parent/manifest values remain JSON null. CAP02 stores canonical JSON and
its lowercase SHA-256. The repository parses persisted JSON and recomputes its
digest before returning a finalized model; mismatch is an internal integrity
failure, never silently repaired.

`ArtifactWorkflowSubjectRevisionResolver` implements the existing Workflow
interface. It accepts only a finalized revision belonging to the supplied
Tenant/type/key, requires the expected revision key and returns exactly
`{revision_key, sha256}`, using the canonical envelope digest. Pending,
missing, mismatched and cross-Tenant values fail closed. Workflow source and
schema do not change.

## Data And Ownership Contract

ArtifactRevision owns exactly two MySQL 8 tables. Identifiers use ASCII binary
collation, timestamps are UTC `DATETIME(3)`, and Kernel Tenant/member foreign
keys use delete restriction. There is no Host payload or File foreign key.

`pa_artifact` contains `id`, `tenant_id`, `artifact_type`, `artifact_key`,
optimistic `revision`, `next_revision_number`, nullable
`latest_finalized_revision_id`, creator/updater member IDs and timestamps. It
has unique `(tenant_id,id)` and `(tenant_id,artifact_type,artifact_key)` keys,
a Tenant/type index, Kernel FKs and checks for identifiers and positive
counters.

`pa_artifact_revision` contains `id`, `tenant_id`, `artifact_id`,
`revision_key VARCHAR(41)`, `revision_number`, nullable `parent_revision_id`,
`state pending|finalized`, optimistic `revision`, nullable payload schema key/
version/ref/SHA-256, nullable attachment-manifest SHA-256, nullable canonical
envelope JSON/SHA-256, creator/finalizer member IDs and create/finalize
timestamps.

It has unique `(tenant_id,id)`, `(tenant_id,revision_key)`,
`(tenant_id,artifact_id,id)` and `(tenant_id,artifact_id,revision_number)` keys.
Artifact and parent FKs are Tenant/artifact scoped. Checks enforce key/digest
syntax, positive values, `parent_revision_id <> id`, and exact null coherence:
pending has no payload/envelope/finalizer fields; finalized has every required
field, with only parent and attachment manifest optional.

The repository locks and proves every parent has a smaller revision number. It
updates a revision only with `state='pending'` plus the expected optimistic
value, increments artifact and revision values, and moves the latest-finalized
pointer only to a higher revision number. Every read scopes Tenant and artifact.

## Errors, Audit And Security Results

Stable errors are `ARTIFACT_REVISION_INVALID` (422),
`ARTIFACT_REVISION_NOT_FOUND` (404), `ARTIFACT_REVISION_CONFLICT` (409),
`ARTIFACT_REVISION_INTEGRITY_FAILURE` (500) and
`ARTIFACT_REVISION_INTERNAL_ERROR` (500). Kernel idempotency errors retain their
existing codes. Unknown, invisible, mismatched and cross-Tenant lookups share
the same not-found result.

Writes audit `tenant.artifact_revision.created` or
`tenant.artifact_revision.finalized` with authorized action, artifact type/key,
revision key/number, parent, state and envelope digest only. Payload refs and
bodies, attachment names, secrets and full envelopes are excluded. Comparison
walks only one Tenant/artifact parent chain with a visited set and stored-row
bound, revealing no foreign lineage.

## Exact Write Sets

This contract commit may change only this file, `docs/content-status.json`,
`docs/status/index.md`, `docs/status/p1-execution-and-post-q01-roadmap.md` and
`docs/status/p1-post-q01-cross-product-capability-plan.md`.

After it commits, one combined `CAP02-I` candidate may change only:

### CAP02-A — schema, models and persistence

- `packages/php/artifact-revision/src/Database/Schema.php`;
- `packages/php/artifact-revision/src/Model/Artifact.php`;
- `packages/php/artifact-revision/src/Model/ArtifactRevision.php`;
- `packages/php/artifact-revision/src/Persistence/ArtifactRevisionRepository.php`;
- `packages/php/artifact-revision/src/Persistence/PdoArtifactRevisionRepository.php`;
- `packages/php/artifact-revision/tests/Unit/Database/SchemaTest.php`;
- `packages/php/artifact-revision/tests/Integration/Persistence/PdoArtifactRevisionRepositoryTest.php`.

### CAP02-B — application and Workflow adapter

- `packages/php/artifact-revision/src/Package.php`;
- `packages/php/artifact-revision/src/Application/ArtifactRevisionException.php`;
- `packages/php/artifact-revision/src/Application/ArtifactRevisionReceipt.php`;
- `packages/php/artifact-revision/src/Application/ArtifactRevisionComparison.php`;
- `packages/php/artifact-revision/src/Application/ArtifactRevisionService.php`;
- `packages/php/artifact-revision/src/Workflow/ArtifactWorkflowSubjectRevisionResolver.php`;
- `packages/php/artifact-revision/tests/Integration/Application/ArtifactRevisionServiceTest.php`;
- `packages/php/artifact-revision/tests/Integration/Workflow/ArtifactWorkflowSubjectRevisionResolverTest.php`.

### CAP02-C — existing projection wiring only

- root `composer.json` for ArtifactRevision source/test dev autoload;
- `packages/php/composer.json` for the twelfth Alpha.5 PSR-4 root;
- `deptrac.yaml` for an ArtifactRevision layer depending on Kernel/Workflow;
- `phpunit.xml` for ArtifactRevision source/tests;
- `scripts/check-workspace` for the internal directory/twelfth root;
- `scripts/check-alpha5-package-projection` for the twelfth root, isolated
  ArtifactRevision autoload, product-term guard and `psr4_roots=12`.

No Workflow, Kernel, File/Media, backend/frontend/npm, lockfile, migration,
OpenAPI, generated artifact, dependency decision, CI workflow or application
file may change. An insufficient whitelist requires an independent correction.

## Test Ownership And Stop Line

`P1-ARTIFACT-REVISION-001` owns schema/install/upgrade/rerun, Tenant/target
isolation, optimistic races, idempotent replay, parent lineage, immutable
finalization, tamper detection, rollback, redacted audit, comparison, Workflow
mapping and isolated Alpha.5 consumer evidence.

Implementation tasks run static review, exact-write-set inspection and
`git diff --check` only; repository PR automation remains required, while the
complete fixed-candidate evidence stays deferred to CAP05. CAP02 does not
qualify/publish Alpha.5, change npm/application locks, nominate a
downstream-consumption candidate or start consumption. CAP03 begins only from
the final CAP02 merge commit; CAP03/CAP04
are not implemented in parallel with CAP02.
