# P1-CAP06 Private Downstream Adoption Contract

## Status

```text
task: P1-CAP06
state: accepted private downstream adoption contract
prerequisite_core_commit: 14010993e47f5e3082ab8f0b53456f282b71f086
prerequisite_core_tree: 3fa7e79730ec9ed8f0349dc1c0d24fa72cfda54f
composer_candidate: peanut-admin/core@0.1.0-alpha.5
composer_projection_sha256: ca30576ae9f671197c0050fea8a42e7d7e61b5c0f43abebd69aec99cd43e5c0e
npm_candidate: @peanut-admin/admin@0.1.0-alpha.5
npm_projection_sha256: 5d01076276a4599682b65fcfde812f5fe201c3e597f2fab38b8ef23cbabe8c80
downstream_repository: operator-supplied external application repository
downstream_prerequisite_commit: 09eeb747c3fbe4f261da4fa6900d777796ab717f
test_owner: P1-CROSS-PRODUCT-DOWNSTREAM-001
qualification: private exact-commit adoption only
publication_authorized: false
```

CAP05 qualifies the source tree and the two package projections; it does not
authorize a consumer to move a dependency lock. CAP06 is the separate,
one-time proof that the Peanut Admin application can consume those exact
immutable inputs without moving product behavior into Core. A branch, tag,
registry dist-tag, package version found at a registry, or a later Core commit
is not a valid substitute for the fixed inputs above.

The downstream prerequisite is the clean application commit shown above. Its
Composer lock still resolves Core Alpha.2 and its Web lock still resolves
Admin Web Alpha.3. CAP06 may update those two locks only after verifying the
CAP05 source/tree and projection digests from controlled private artifacts.

## Objective And Non-Goals

CAP06 has three outcomes:

1. move the application dependency locks to the CAP05-qualified Alpha.5
   projections using a reproducible private artifact input;
2. add one application-owned adapter that composes the existing Core
   authorities—Tenant context, authorization, ArtifactRevision,
   EntitlementQuota, Collaboration and HumanWorkflow—around one existing
   product resource; and
3. prove the composed path with one real ThinkPHP Host test, including a
   successful business result, deterministic replay, rollback/compensation,
   Tenant isolation and a non-enumerating authorization failure.

The article resource already owned by the application is the fixture target.
The adapter and test may use its existing table and payload fields, but Core
must receive only an opaque typed target and immutable payload reference. The
application remains the sole owner of Article schema, content validation,
routes, OpenAPI, menu permissions, page behavior, publication semantics and
any product workflow definition.

CAP06 does not:

- change Core Runtime, package source, package manifests, generated artifacts,
  migrations, or public package projections;
- publish Composer/npm artifacts, write Packagist/npm/GitHub state, create a
  tag or Release, move a dist-tag, or make a stable/support/production claim;
- copy Tenant, authorization, audit, idempotency, Workflow, revision, quota or
  collaboration tables/services into the application;
- turn the Article table into a Core-owned artifact table or move its payload
  into a reusable package;
- implement a product editor, realtime server, Hocuspocus deployment, AI
  approval, billing, subscription, plan catalog, or a generic SaaS runtime;
- migrate every existing application path to Alpha.5, remove all historical
  application behavior, or start the planned media-resource-management
  repository; or
- nominate a downstream-consumption candidate or start SaaS01. Those decisions require separate
  contracts and evidence.

## Immutable Input And Artifact Contract

The adoption owner must receive the CAP05 review and both projection archives
before touching either dependency manifest. The owner records the following in
the adoption evidence without recording credentials or private absolute paths:

| Input | Required evidence |
| --- | --- |
| Core source | 40-character commit `14010993e47f5e3082ab8f0b53456f282b71f086` and tree `3fa7e79730ec9ed8f0349dc1c0d24fa72cfda54f` |
| Composer projection | `peanut-admin/core@0.1.0-alpha.5`, archive SHA-256 `ca30576ae9f671197c0050fea8a42e7d7e61b5c0f43abebd69aec99cd43e5c0e`, 694 files and 14 PSR-4 roots as recorded by CAP05 |
| npm projection | `@peanut-admin/admin@0.1.0-alpha.5`, retained projection SHA-256 `5d01076276a4599682b65fcfde812f5fe201c3e597f2fab38b8ef23cbabe8c80`, 72 files and 15 exports as recorded by CAP05 |
| downstream base | Application commit `09eeb747c3fbe4f261da4fa6900d777796ab717f` and its exact dependency-lock digests |

Artifacts are supplied through a controlled private file/artifact input. The
input may be a locally mounted immutable archive or an equivalent private
artifact store whose content digest is checked before Composer or pnpm reads
it. The input location is an environment value or test fixture outside Git;
an absolute workstation path, token, cookie or registry credential must not be
written to a manifest, lock, source file, log or adoption record.

The lock update must prove all of the following:

- both package names and versions are exactly `0.1.0-alpha.5`;
- the resolved package content matches the CAP05 digest, not merely the
  version string or a mutable branch;
- Composer and pnpm install with network access disabled after the artifact is
  staged; and
- no transitive dependency, package source, post-install script or generated
  file causes an unreviewed registry or network write.

The application may use a temporary local repository declaration or resolver
override to stage the private input, but the committed manifests must remain
portable and must not contain a developer home directory, machine-specific
socket, credential, or implicit moving `dev`/`main` reference. If the chosen
artifact mechanism cannot meet those requirements, stop for a contract
correction; do not weaken the digest check or fall back to a public registry.

## Existing Authorities And Required Reuse

| Concern | Authority | CAP06 rule |
| --- | --- | --- |
| Tenant and actor | Core `AuthorizedOperationContext` and `TenantContext` | The Host creates trusted context after authenticating the tenant audience. No request body, route segment or Article field may choose the Tenant/member/account. |
| Functional/data authorization | Core operation and typed-target providers, with the application's registered Article permission | The primary target must be exactly `article` plus the canonical Article key. Missing permission, invisible target, wrong Tenant and unknown Article use a safe non-enumerating result. |
| Article schema and payload | Application Article Module | Core receives only an opaque key, payload schema/version, payload reference and digest. Core tables are never joined from the Article query. |
| Immutable revision | Core `ArtifactRevisionService` | The Host adapter creates/finalizes one immutable revision through the public service. It never writes `pa_artifact*` tables directly or stores article content in audit metadata. |
| Quota | Core `EntitlementQuotaService` | The Host declares the product meter and Provider policy. A meter is not inferred from a package plan, route, Tenant name or absent policy. |
| Collaboration | Core `CollaborationService` | The Host supplies authenticated admission, target authorization and an opaque update/snapshot. Presence and transport credentials stay in the Host. |
| Workflow | Core `WorkflowRuntime` | The Host owns one product workflow definition and its permission/assignment adapters. Only a human member may complete the decision; an AI/service actor is rejected. |
| Audit and idempotency | Existing Core PDO repositories | Each command uses the caller's PDO and scoped idempotency. Sensitive payloads, tokens, credentials and cross-Tenant summaries never enter audit. |

The adapter must inject one PDO into every Core service and Provider. It must
fail closed when an adapter reports a different connection, missing policy,
missing membership, unavailable target, stale revision or unavailable
provider. It may not create a second connection, use a privileged bypass, or
swallow a Core Problem into a success response.

## Minimum Product Flow

The test uses two isolated Tenant fixtures and one Article target per Tenant.
The application adapter exposes a narrow method for the test and any future
Host command; it is not a generic domain engine. The flow is ordered as follows:

### 1. Admission and base revision

The Host resolves the authenticated Tenant/member context and checks the
registered Article operation and typed target. It ensures the fixture Article
has one finalized base ArtifactRevision through the Core service. The base
revision is pinned by key and digest; its payload remains in the application.

### 2. Collaborative edit and immutable submission

The adapter opens one Tenant-scoped Collaboration session for the Article,
joins with a short-lived write lease, appends a bounded opaque update and saves
a covering snapshot. It then publishes the session. Publish must finalize
exactly one ArtifactRevision on the same PDO and close/revoke the session. A
missing snapshot, stale lease, digest mismatch, size limit, wrong target or
provider failure must leave no partial Core state.

The minimum fixture may use a deterministic opaque update rather than a browser
editor. It must still exercise the public Collaboration service contract; a
direct insert into a collaboration table is not evidence.

### 3. Quota reservation and settlement

The Host registers one product meter (for example, `article.revision`) whose
target type is `article` and whose unit is an integer revision. It supplies a
bounded immutable policy through the Core Provider, reserves one unit for the
new revision, and retains the reservation key. The Host must not encode a
commercial price, unlimited fallback or plan name in Core.

If the subsequent workflow operation fails, the Host releases the pending
reservation with a derived idempotency key. Only a successful completed
workflow may commit it. Duplicate reserve/release/commit requests return the
original safe receipt and never double-count usage.

### 4. Human workflow decision

The Host provides one product-owned workflow definition whose subject is the
`article` target and whose subject revision is the just-finalized immutable
revision. It starts one instance through `WorkflowRuntime`, then completes the
minimum human decision through `applyTransition` with the current immutable
subject revision key. The test must include a negative service/AI actor case;
automation cannot approve the Article. A stale subject revision or unauthorized
target is rejected before any instance/event/audit side effect.

After the human decision reaches its terminal state, the Host commits the quota
reservation. The test records the final revision key/digest, workflow instance
and event sequence, quota receipt/ledger state, and Collaboration closed state;
it does not record Article payload bytes.

### Failure and replay semantics

Every step has a deterministic idempotency key derived from the authenticated
Tenant, canonical Article target, operation and fixture command—not from a
secret or raw payload. Replaying the complete flow returns the original
receipts and leaves one revision, one workflow terminal decision, one quota
commit and one audit trail.

The adapter must release a pending quota reservation when a later workflow
step fails. If publication has already produced an immutable revision, that
revision remains immutable and unapproved; the Host may retry with a new
workflow instance. A Core service failure rolls back its own same-PDO effects.
The adapter must never report the flow as accepted when compensation fails; it
returns a stable error and records only safe identifiers for operator review.

## Host Adapter Contract

The only new application service is:

```text
server/app/common/service/capability/CrossProductAdoptionHost.php
```

It may depend on the public package contracts and existing application Article
and permission services. It must:

- accept a trusted context and canonical Article key rather than a caller-
  supplied Tenant/member/account ID;
- construct or receive Core Providers for Article authorization, revision
  payload metadata, collaboration policy/submission, quota meter/policy and
  workflow assignment/subject resolution;
- keep all Core calls behind this adapter so controllers, models and pages do
  not deep-import or write Core internals;
- use one explicit transaction/connection boundary per Core operation and
  deterministic compensation across operations;
- map Core exceptions to the application's existing safe error envelope
  without exposing package internals, SQL, payloads or hidden identifiers; and
- return a small evidence DTO containing public keys, digests, states and
  counts only. It must never return opaque update bytes, article body, bearer
  token, refresh cookie, policy snapshot, Provider credentials or another
  Tenant's usage.

No generic `TenantService`, `WorkflowService`, `RevisionService`, `QuotaService`
or `CollaborationService` may be added to the application. Such names would
recreate Core ownership and are outside CAP06.

## Downstream Test Contract

The only new application test is:

```text
server/tests/Productization/CrossProductDownstreamAdoptionTest.php
```

The test owner is `P1-CROSS-PRODUCT-DOWNSTREAM-001`. It runs once against a
clean private fixture after the lock update and must cover:

1. exact Core source/tree and Composer/npm projection digest checks before
   loading application autoload code;
2. isolated offline dependency installation and package-version assertions;
3. one successful flow from trusted Tenant admission through Collaboration,
   immutable Article revision, quota reserve/commit and human Workflow
   completion;
4. exact replay of the successful flow with no duplicate revision, event,
   ledger settlement, session publication or audit event;
5. a failure after reservation that releases capacity and leaves no accepted
   workflow decision;
6. a second Tenant attempting to use the first Tenant's Article, revision,
   session, reservation or workflow instance and receiving the same safe
   not-found/denied shape as an unknown target;
7. a member without the Article operation or typed-target permission receiving
   a safe denial before any Core write; and
8. an AI/service actor attempting the human transition being rejected without
   a terminal workflow event or quota commit.

The test must assert Tenant-first queries and exact Core state through public
read contracts or narrowly scoped evidence helpers; it may not join or mutate
Core tables from an application model. It must clean all fixtures in the same
isolated database and leave the repository, registry, package cache and
production services unchanged.

The test may create Core schemas through their public schema helpers in a
temporary qualification database. It must not add an application migration
for Core tables or modify `server/database/init.sql`. A failure to install the
qualified Core schema is a blocked adoption, not a reason to skip assertions.

## Exact Write Sets

The CAP06 contract and status registration may change only:

- `docs/status/p1-cap06-private-downstream-adoption-contract.md`;
- `docs/content-status.json`;
- `docs/status/index.md`;
- `docs/status/p1-execution-and-post-q01-roadmap.md`; and
- `docs/status/p1-post-q01-cross-product-capability-plan.md`.

After this contract is accepted, the downstream implementation may change
only the following files in the application repository:

- `server/composer.json` — the exact Alpha.5 private artifact declaration;
- `server/composer.lock` — the resulting exact package and dependency lock;
- `web/package.json` — the exact Alpha.5 private artifact declaration;
- `web/pnpm-lock.yaml` — the resulting exact package and dependency lock;
- `server/app/common/service/capability/CrossProductAdoptionHost.php` — the
  application-owned adapter described above; and
- `server/tests/Productization/CrossProductDownstreamAdoptionTest.php` — the
  single acceptance owner described above.

The Core repository may add only the later adoption evidence record
`docs/status/p1-cap06-private-downstream-adoption.md`, after the downstream
commit and test evidence are complete. It may not change `packages/**`, any
manifest or lock, generated artifact, dependency decision, CI workflow,
schema, migration, Host implementation or package projection in CAP06.

If the adapter or test needs any file outside these sets—including a route,
OpenAPI document, application migration, Core schema change, package manifest,
fixture service or generated type—stop and request an independent contract
correction. Do not silently widen the write set.

## Verification And Stop Line

Development tasks use static review, exact-write-set inspection and
`git diff --check`. The downstream owner then performs one clean private
consumer installation and one focused `CrossProductDownstreamAdoptionTest`
run. The test must run with network access disabled after artifacts are
staged; no registry or external deployment is part of the evidence.

If the focused test fails, perform at most one read-only diagnosis under the
repository policy. Do not retry passed groups, weaken isolation or digest
assertions, remove the failing negative case, change package versions, or add a
compatibility fallback. A second failure blocks CAP06 and leaves the Alpha.2/
Alpha.3 locks unchanged.

Passing CAP06 proves only private exact-commit adoption for the recorded
application tree. It does not qualify a later Core commit, publish either
package, move a registry tag/dist-tag, authorize production, or make any
compatibility promise. A separate publication/Release decision must bind new
source and projection evidence before any external write. The planned media
project remains uncreated and its source materials remain read-only.
