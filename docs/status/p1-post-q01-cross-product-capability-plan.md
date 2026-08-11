# P1 Post-Q01 Cross-Product Capability Plan

## Status

```text
task: P1-CAP00
state: accepted planning repair
prerequisite_commit: a2a13b633cfdfdaa14aca5f1d917e4f6865597c2
result_commit: e911406909710480b59d7332de9bc18a365794fa
scope: ArtifactRevision, HumanWorkflow, EntitlementQuota, Collaboration
runtime_change: none
dependency_change: none
automated_verification: deferred to the owning integration or qualification stage
qualification: not started
downstream_adoption: not started
publication: not authorized
cap01_source_result: 3972c9aefcd55ac71d07a47739a99d23bb0ae30c
cap01_result_tree: d6dbde37907d1dd43b00057fc16fbd1a8d6dd052
```

This document restores the missing cross-product plan after Q01. It is an
ordering and ownership decision, not an executable Runtime task. PB09 and the
Peanut Admin v1.0.0 publication are completed inputs. The forty-path WF01
candidate based on the accepted historical contracts and reconciliation commit
`faa126ebcdb4169ef3f0b623ca959fa742808aa7` is fixed at
`3972c9aefcd55ac71d07a47739a99d23bb0ae30c`; it is not qualified, published or
approved for application consumption.

## Boundary And Reuse Decision

The four capabilities are reusable administration infrastructure. They must
not contain a product's document types, approval templates, pricing, billing,
editor schema, content, routes or business rules.

All stages reuse the existing authorities instead of rebuilding them:

| Concern | Existing owner | Cross-product rule |
| --- | --- | --- |
| Identity and Tenant | Kernel trusted Tenant context, accounts, members, Roles and Departments | Actor, audience and Tenant come only from trusted context. No request-supplied Tenant or account override exists. |
| Functional and data authorization | Kernel RBAC and Data Permission typed targets | Every Host operation declares Permission and target behavior. Missing declarations, Providers or target decisions fail closed. |
| Files | File/Media | Capabilities store approved opaque file keys or immutable snapshots only; they do not create another file authority. |
| Background work | Task/Job | Deferred processing uses registered task types and trusted worker context; it does not add an execution lease system. |
| Notifications | Notification/SMS | Capabilities emit typed intents through a separately authorized Host adapter; they do not own delivery channels or recipient addresses. |
| Audit and atomicity | Kernel audit plus R01/R02 transaction and idempotency primitives | Each write is Tenant-scoped, auditable and atomic on one caller-owned PDO. No partial effect or unaudited success is allowed. |

## Dependency And Stage Order

```text
CAP00 planning repair at e911406909710480b59d7332de9bc18a365794fa
  -> CAP01 HumanWorkflow contract reconciliation and candidate integration
  -> CAP02 ArtifactRevision
  -> CAP03 EntitlementQuota
  -> CAP04 Collaboration
  -> CAP05 fixed-commit cross-product qualification
  -> CAP06 exact-commit private downstream adoption
  -> separate publication decision and external publication action, if approved
```

The order is serial. A stage starts only from the exact 40-character result of
its predecessor. `CAP00_RESULT` means the commit created by this planning task;
the CAP01 contract must replace that label with the reported exact hash. Every
later stage contract does the same for its predecessor. A branch, tag name,
working tree or commit subject is not a fixed input.

The Peanut Admin application `v1.0.0` source and the exact core package
artifacts locked by that release are the downstream compatibility baseline;
they do not share one version number. CAP01 resolves them in the Workflow
contract: application commit `0d3c848b8e2bb622a868924145ce810a8946f173`,
Composer `peanut-admin/core@0.1.0-alpha.2`, Admin Web/PC npm Alpha.3 and
UniApp/H5 npm Alpha.4. A missing or ambiguous identity stops source acceptance.

## WF01 And ArtifactRevision Arbitration

The existing WF01 candidate **can remain decoupled from ArtifactRevision**.
`WorkflowSubjectRevisionResolver` is a Host port that returns an opaque
`revision_key` and SHA-256 digest. Workflow stores only that immutable pin on
its instance and event rows. It does not read or own a Host content table,
revision payload, editor state or ArtifactRevision schema. This is the correct
dependency direction:

```text
HumanWorkflow -> immutable subject-revision port <- Host adapter / ArtifactRevision
```

Consequently CAP01 may land its package behavior with a conforming test
resolver before CAP02 exists. CAP02 later supplies the reusable revision
authority and an adapter without changing HumanWorkflow's state machine or
schema. Collaboration depends on ArtifactRevision and publishes immutable
revisions into it; it never makes mutable collaboration state an approval
subject. Qualification remains deferred to CAP05.

This decision is conditional on preserving the current port boundary. If
CAP01 static review finds a direct ArtifactRevision table/class dependency, a
requirement that Workflow own revision payloads, or any need to change subject
revision state outside the port, CAP01 stops. The candidate is then frozen and
CAP02 moves ahead of CAP01 through a separate CAP00 correction. Existing code
must not be used as a reason to reverse the dependency.

CAP01 contract reconciliation records that application and package releases
have independent version lines. `peanut-admin/core@0.1.0-alpha.5` remains a
valid unpublished Composer candidate number after the application v1.0.0
release. Static source acceptance completed at `3972c9a`; CAP05 qualification,
CAP06 adoption and public publication remain separate authorities. The current
documentation closure does not run tests or publish Alpha.5; CAP02 begins only
from the exact final CAP01 closure result.

## CAP01 To CAP06 Stage Contracts

The write sets below are conceptual boundaries. They do not authorize a file
until the stage's independent contract names the exact whitelist.

### CAP01 — HumanWorkflow

- **Fixed input:** `e911406909710480b59d7332de9bc18a365794fa`, the resolved
  application/package baseline in the reconciled WF01 contract and the
  frozen WF01 contract commits `abeb5afa32dee353b13debe08b23575173979d90`,
  `f2f4a21d942f6a24e1ed673c67dfb6a72c531c3d` and
  `a2a13b633cfdfdaa14aca5f1d917e4f6865597c2`. Static source acceptance
  produced `3972c9aefcd55ac71d07a47739a99d23bb0ae30c` with tree
  `d6dbde37907d1dd43b00057fc16fbd1a8d6dd052`.
- **Conceptual write set:** the existing `packages/php/workflow` source and
  tests, Workflow testing harness, Composer projection/wiring and narrowly
  required status or qualification records. Existing forty candidate files
  are reviewed rather than regenerated. No Web Runtime or Host business API is
  implied.
- **Schema owner:** HumanWorkflow alone owns definition, immutable version,
  instance, work-item and append-only event tables. It stores opaque subject
  revision pins and approved file snapshots, never ArtifactRevision payloads.
- **API boundary:** framework-neutral PHP commands and queries inside
  `peanut-admin/core`. A Host owns HTTP routes, OpenAPI, Problem Details mapping
  and Runtime coverage for every real operation.
- **Security boundary:** Tenant audience, trusted actor, declared functional
  Permission plus one typed subject target, optimistic revisions, idempotency,
  human-only decisions and one-PDO audit/side-effect rollback. Cross-Tenant,
  invisible subject, assignee, attachment or Provider failures do not enumerate
  protected state.
- **Test owner:** existing `P1-WORKFLOW-RUNTIME-001`.
- **Stop line:** the version and fixed-base contract was reconciled before the
  CAP01 integration owner statically accepted and committed the exact
  candidate. Automated tests belong only to the fixed CAP05 qualification
  contract. No publication, downstream adoption or product Host implementation
  follows automatically.

### CAP02 — ArtifactRevision

- **Fixed input:** exact CAP01 candidate result; qualification remains
  deliberately deferred to CAP05.
- **Conceptual write set:** a product-neutral PHP revision namespace, schema,
  repository, immutable revision service, Workflow adapter, tests and Composer
  projection. Host routes, Web UI and product revision payload schemas are
  excluded unless separately contracted.
- **Schema owner:** ArtifactRevision owns artifact identity, immutable revision
  envelope, parent relation, digest, author, state and timestamps. Hosts own
  the typed payload and business validation. HumanWorkflow keeps only opaque
  key/digest references and adds no foreign key across owners.
- **API boundary:** create/finalize/read/compare immutable revisions through
  authorized PHP contracts. A Host HTTP API, if required, is a separate
  operation contract with explicit audience, OpenAPI and Runtime ledger owner.
- **Security boundary:** Tenant-bound typed artifact targets, append-only
  finalized revisions, canonical digest verification, optimistic preconditions,
  idempotency and redacted audit. Unknown, invisible and cross-Tenant artifacts
  share the not-found shape. Mutable payload, secret data and collaboration
  presence never enter audit metadata.
- **Test owner:** `P1-ARTIFACT-REVISION-001`.
- **Stop line:** no product schema, editor implementation, Workflow redesign,
  public package publication or application migration. The resulting commit is
  a candidate until CAP05.

### CAP03 — EntitlementQuota

- **Fixed input:** exact CAP02 candidate result.
- **Conceptual write set:** a PHP entitlement/quota namespace, schema,
  policy/provider contracts, atomic usage/reservation service, tests and
  Composer projection. Billing, invoices, payments, prices, commercial license
  enforcement and product-specific meters are excluded.
- **Schema owner:** EntitlementQuota owns Tenant entitlement grants, immutable
  policy revisions, meter keys, period windows, reservations and usage ledger.
  A Host owns plan/catalog presentation and the semantic unit for each declared
  meter.
- **API boundary:** framework-neutral check/reserve/commit/release/read
  contracts in `peanut-admin/core`. A Host registers typed meters and maps any
  HTTP operation explicitly; no hidden middleware-wide quota bypass is allowed.
- **Security boundary:** trusted Tenant and actor, declared meter and target,
  atomic compare-and-reserve, idempotent settlement, bounded time source and
  fail-closed missing policy/provider behavior. Denials expose only safe limit
  summaries and never another Tenant's plan, usage or commercial metadata.
- **Test owner:** `P1-ENTITLEMENT-QUOTA-001`.
- **Stop line:** no SaaS billing claim, super-user override, silent unlimited
  fallback, product meter or application consumption. The candidate waits for
  CAP05.

### CAP04 — Collaboration

- **Fixed input:** exact CAP03 candidate result and accepted dependency
  decisions for every selected collaboration Runtime library.
- **Conceptual write set:** collaboration PHP contracts and persistence,
  Admin Web collaboration subpaths/components, a Host-neutral transport
  adapter, tests and the existing Composer/npm projections. Product editors,
  document schemas and deployed realtime infrastructure are excluded.
- **Schema owner:** Collaboration owns mutable sessions, participant leases,
  update/snapshot envelopes and retention markers. ArtifactRevision owns the
  immutable submitted revision produced at the collaboration boundary.
  HumanWorkflow consumes only that finalized revision through its port.
- **API boundary:** `peanut-admin/core` owns server-side session/persistence
  contracts and `@peanut-admin/admin` owns UI-neutral client and optional Admin
  Web integration. A real Host owns authenticated HTTP/WebSocket endpoints,
  origin policy, deployment topology and product editor binding.
- **Security boundary:** short-lived Tenant-scoped admission, real functional
  and typed-target authorization at join and publish, membership revalidation,
  bounded message/update sizes, replay protection, backpressure, retention and
  audit redaction. Presence never grants approval authority; disconnect or
  missing Provider fails closed.
- **Test owner:** `P1-COLLABORATION-001`.
- **Stop line:** no home-grown CRDT/OT algorithm, anonymous/cross-Tenant
  editing, production realtime service, approval bypass, product editor or
  publication. The candidate waits for CAP05.

### CAP05 — Fixed-Commit Cross-Product Qualification

- **Fixed input:** exact CAP04 result, which contains the serial CAP01-CAP04
  candidates and their accepted contracts.
- **Conceptual write set:** qualification record, content-status registration
  and narrowly required current-status links only. Missing Runtime behavior or
  tests are not repaired inside qualification.
- **Schema owner:** none; CAP05 verifies each owner's clean install, v1.0.0
  upgrade, idempotent re-entry, exact schema and forward-recovery contract.
- **API boundary:** verify package PHP APIs, Web exports and any separately
  accepted Host operation/OpenAPI mappings without inventing new operations.
- **Security boundary:** fixed-tree Tenant isolation, authorization, audit,
  failure atomicity, dependency/supply-chain, recovery and performance groups.
- **Test owner:** `P1-CROSS-PRODUCT-QUALIFICATION-001`, aggregating the four
  capability owners without replacing them.
- **Stop line:** one consolidated run per contracted group under repository
  policy. A failure receives at most the authorized repair/resume path. Passing
  CAP05 qualifies only the exact source and projections; it does not publish,
  move a downstream lock or authorize production.

### CAP06 — Exact-Commit Private Downstream Adoption

- **Fixed input:** exact CAP05-qualified source commit and exact Composer/npm
  projection digests.
- **Conceptual write set:** downstream-owned dependency lock, integration
  adapters, one product-owned minimum acceptance and a core adoption record.
  Core Runtime changes require a new capability task and cannot hide in CAP06.
- **Schema owner:** each package schema remains owned by its CAP stage; the
  application owns only product schema and adapter migrations.
- **API boundary:** the application provides its own Host routes, OpenAPI,
  typed targets, Workflow definitions, artifact payloads, meters and editor
  binding. It deletes rather than preserves duplicate generic Runtime.
- **Security boundary:** pinned artifacts, real Tenant/permission/data-policy
  evaluation, non-enumerating failures and no local bypass. The application
  proves one minimum cross-capability flow without moving product logic into
  core.
- **Test owner:** a named downstream owner plus
  `P1-CROSS-PRODUCT-DOWNSTREAM-001` for the recorded core-facing consumer
  evidence.
- **Stop line:** private exact-commit validation only. It does not mutate a
  registry, tag, Release, dist-tag, stable support promise or production claim.

The executable CAP06 contract is
[`P1-CAP06 Private Downstream Adoption`](./p1-cap06-private-downstream-adoption-contract.md).
It binds CAP05 source commit `14010993e47f5e3082ab8f0b53456f282b71f086`,
source tree `3fa7e79730ec9ed8f0349dc1c0d24fa72cfda54f`, Composer projection
digest `ca30576ae9f671197c0050fea8a42e7d7e61b5c0f43abebd69aec99cd43e5c0e`
and npm projection digest
`5d01076276a4599682b65fcfde812f5fe201c3e597f2fab38b8ef23cbabe8c80`.
The downstream implementation owns only its locks, one Article adapter and
one focused test. A missing route, migration, Core change or generic duplicate
Runtime requires a separate contract correction.

## Mature Dependency Decision Gate

No dependency is installed, locked or imported merely because this plan names
a capability. Before the first manifest or lockfile change, a separate accepted
decision must record the exact use case, maintained alternatives, version and
license, official source, security status, adapter boundary, upgrade/removal
plan and which public package carries it.

In particular:

- CAP01-CAP03 default to existing primitives and add no dependency unless their
  task proves one unavoidable gap.
- CAP04 must select mature CRDT/OT and transport/persistence components; it may
  not implement a collaboration algorithm from scratch.
- A browser-only dependency cannot silently become a PHP/server authority, and
  a server dependency cannot leak product/editor semantics into a public API.
- A rejected, abandoned, incompatible or unreviewed candidate blocks only the
  dependent stage; it does not justify a placeholder package or weaker local
  implementation.

## Two-Package Compatibility Contract

Exactly two public installable boundaries remain:

- Composer `peanut-admin/core` for PHP Runtime and server contracts;
- npm `@peanut-admin/admin` for UI-neutral clients and Admin Web integration.

Internal capability directories are ownership boundaries, not new public
packages. Peanut Admin application v1.0.0 and its exact Alpha.2/Alpha.3/Alpha.4
dependency locks are the downstream compatibility baseline. The independent
core package line may advance to Alpha.5, but no stage may replace an existing
public export, remove a public subpath, weaken a peer range, or require an
application to deep-import an internal namespace.
Additive PHP namespaces and npm subpaths must preserve supported Runtime and
type-resolution behavior. Candidate version numbers and publication channels
are selected only by a later release contract.

CAP05 must qualify both exact projections even when a capability changes only
one of them. It records file lists, package metadata, dependency/peer surfaces,
autoload/exports, immutable digests and clean isolated consumers against the
repository's supported PHP, Node and TypeScript matrix. Passing source tests is
not a substitute for package-consumer evidence.

## Qualification, Adoption And Publication Separation

These are three independent authorities:

1. **CAP05 qualification** proves one fixed source tree and two projections.
2. **CAP06 private downstream adoption** proves an application can consume the
   exact qualified artifacts without duplicate generic Runtime or a bypass.
3. **Publication** requires a later, separate approval record that fixes the
   version, source/projection commits and digests, registry ownership,
   provenance, immutable tag/Release, publication order, rollback policy and
   clean registry-consumer probes.

Neither qualification nor private adoption sets `publication_authorized`.
Publication does not imply production readiness or authorize an application
migration. A public release action must not mutate the already qualified source
tree and is outside CAP00-CAP06 until explicitly approved.

## CAP03 Result And CAP04 Dependency Gate

CAP03 changed only its contracted EntitlementQuota and projection paths. PR #10
passed baseline, dependency review, qualification, quality, recovery and
verify. The exact CAP03 merge is
`c27e03006135adce56627b438a2ac82a4fef5d95` with tree
`f253b76ca09f056b60e2abd49a43251ba38383ef`.

The exact Yjs client and Host transport boundary is fixed by the accepted
[CAP04 dependency decision](../decisions/dependencies/p1-cap04-collaboration.md).
That decision installs nothing. CAP04 Runtime starts only after its independent
exact contract is accepted from the resulting merge commit. CAP05
qualification, CAP06 adoption and publication remain separate authorities.

The accepted [CAP04 Collaboration contract](./p1-cap04-collaboration-contract.md)
starts from Core PR #11 merge `7105800845e364da9a2fa731b7a1d8cdf6b5163b`
and its exact tree. CAP04 installation and Runtime remain limited to that
contract's explicit write groups and stop line.
