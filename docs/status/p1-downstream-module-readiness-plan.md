# P1 Downstream Module Readiness Plan

## Status

```text
state: active
planning_base: d612a85045e2e9eb017719cd42a2f781d35b1f69
qualified_downstream_lock: 0ab02a9b735ba9f4c23509cb366b9bf04039ebf8
target: qualified Starter v1 fixed commit for application-owned Module development
release_status: not approved
```

This plan defines the smallest P1 capability set required before a downstream
application can implement its first business Module without copying reusable
administration infrastructure into the application repository.

The Peanut Admin standard application is one product surface: it is the live
Demo, the maintainers' development workbench, and the starter application that
downstream projects extend. It must consume the same public packages and Module
contracts available to those projects, without Demo-only Runtime shortcuts.

The current downstream lock does not move while this plan is active. A task
commit proves only its own candidate slice. Only the final fixed-commit
qualification may approve a new downstream lock.

## Boundary Decision

Peanut Admin owns reusable administration infrastructure. A downstream
application owns its domain tables, commands, queries, state machines,
permissions, pages, projections, and events.

This plan adopts:

- atomic transaction, idempotency, and audit primitives for external Modules;
- a host-configurable backend operation composition contract;
- a host-configurable protected Web Runtime and Module route registry;
- a configurable Workspace Shell and reusable Peanut-owned administration
  contributions;
- reusable typed-target page orchestration;
- minimal settings and reference-code Modules;
- fixed-starter and full-stack external-host qualification.

This plan does not adopt:

- a generic domain repository or a universal CRUD engine;
- application-specific fields, workflows, names, examples, or policies;
- a public project generator, CRUD generator, codemod, or source-upgrade tool;
- file, media, notification, queue, scheduler, import/export, extension market,
  public package, SemVer, release, or production commitments.

Account self-service and effective-access preview remain useful P1 candidate
slices and are retained in the aggregate candidate. They are not runtime
dependencies of an application-owned business Module.

## Required Tasks

### P1-R01 Operation Atomicity Primitives

Objective: make one PDO and one explicit transaction boundary authoritative for
an external Module command.

Required behavior:

- nested operations use savepoints instead of silently sharing an outer
  transaction without rollback isolation;
- idempotency acquisition, domain effects, audit evidence, outbox writes, and
  idempotency completion can be composed on the same PDO;
- abandoned `processing` idempotency records have a deterministic recovery or
  rejection path based on expiry and request hash;
- denied, failed, and successful outcomes can be recorded without exposing
  secrets or target existence;
- failure injection proves that no partial domain, audit, outbox, or
  idempotency-completion state remains.

Non-goal: Peanut Admin does not own an application's domain transaction or
outbox schema. It provides primitives and a contract harness; the application
provides the domain callable and domain-owned outbox adapter.

### P1-R02 External Operation Host Kit

Objective: let an external host compose trusted context, Module availability,
functional permission, typed-target data authorization, transaction primitives,
and stable HTTP errors without copying the reference host.

The exact prerequisite, API and security behavior, file whitelist, test owner,
and stop line are fixed by the
[P1-R02 External Operation Host Kit Contract](./p1-r02-external-operation-host-kit-contract.md).

Required behavior:

- host-owned Module namespaces, API prefixes, OpenAPI documents, and generated
  types remain application-owned;
- reusable middleware and adapters accept host configuration rather than fixed
  repository paths or operation counts;
- missing Module, permission, target provider, operation declaration, or
  context fails closed;
- a fictional external Module proves list, detail, create, update, and status
  command paths without introducing a generic domain model.

### P1-W01 Protected Transport Origin Boundary

Objective: ensure a protected Web client never attaches an access token to an
origin outside its configured API authority.

Required behavior:

- absolute and relative requests resolve against an explicit allowed origin;
- a cross-origin request is rejected before `fetch` and before an authorization
  header is created;
- Client-scoped refresh, idempotent replay, request IDs, and allowed-path checks
  retain their current behavior.

### P1-W02 Host Runtime And Module Routing

Objective: move reusable login, refresh, Tenant selection, trusted context,
menu, route guard, Tenant switching, logout, and stale-request disposal into
package APIs consumable by an external host.

Required behavior:

- one build-time Module registry owns route records, menu resolution, access
  guards, and Module-store disposal;
- duplicate route names or paths fail at build/test time;
- an unavailable Module or denied permission does not load its page chunk;
- host-owned Client keys, API prefixes, generated types, and branding remain
  configurable;
- Tenant switching clears old context, target selection, collections, and
  pending response visibility.

### P1-W03 Workspace Shell And Common Contributions

Objective: provide a usable administration shell without requiring every host
to copy the reference frontend.

The exact prerequisite, host configuration, audience boundary, file whitelist,
test contract, and stop line are fixed by the
[P1-W03 Workspace Shell Contract](./p1-w03-workspace-shell-contract.md).

Required behavior:

- configurable desktop and mobile Workspace layout, navigation, identity area,
  breadcrumbs, Tenant switch, and logout;
- reusable authentication, Tenant selection, forbidden, not-found, unavailable,
  rate-limit, and session-expired contributions;
- reusable Peanut-owned member, Department, Role, Module, data-policy, audit,
  and account contributions;
- typed-target orchestration for query, pagination, zero/one/many cardinality,
  selection persistence, and Tenant/Module disposal;
- application-owned business pages remain ordinary Module contributions.

Non-goal: this task does not create a universal form schema, DataGrid, domain
workflow builder, or CRUD generator.

### P1-B02 Effective Access Preview

This existing candidate task remains independently reviewable. It must render
an authoritative backend explanation of effective functional and data access;
the frontend must not recompute authorization. It is included in aggregate
qualification but does not block P1-R01, P1-W01, or planning for later tasks.

### P1-B03 Minimal Settings Module

Objective: provide Module-owned typed setting definitions and Tenant-owned
values without turning environment variables or opaque JSON into a policy
store.

The exact prerequisite, ownership boundary, schema, API, Web behavior, file
whitelist, test owner, isolated namespace, and stop line are fixed by the
[P1-B03 Minimal Settings Module Contract](./p1-b03-minimal-settings-contract.md).

Minimum contract:

- stable Module/key ownership, JSON Schema validation, revision and ETag;
- explicit deployment and Tenant scope precedence;
- optional typed-target scope only when the declaring Module registers the
  target type and operation;
- effective time, redacted secret values, audit, and cache invalidation;
- fail-closed reads when a required setting has no effective value.

Application-specific keys and values remain in the application Module.

### P1-B04 Minimal Reference-Code Module

Objective: provide stable code-set infrastructure for administrative reference
values that do not deserve an application-owned domain aggregate.

The exact prerequisite, ownership boundary, schema, API, Web behavior, file
whitelist, test owner, isolated namespace, and stop line are fixed by the
[P1-B04 Minimal Reference Codes Module Contract](./p1-b04-minimal-reference-codes-contract.md).

Minimum contract:

- Module-owned code sets and Tenant-isolated entries;
- immutable code identity, mutable versioned label and metadata, status and
  effective interval;
- optimistic concurrency, audit, deterministic ordering, and as-of query;
- no hierarchy, arbitrary workflow, or application-specific catalog semantics.

Application-owned categories, identifiers, units, and lifecycle rules must not
be represented as Peanut reference codes merely to avoid a domain Module.

### P1-Q01 Starter v1 Fixed-Commit Qualification

Q01 is the concentrated qualification gate for a fixed Starter v1 candidate.
Historical Q01 contracts, failed results, and performance-remediation evidence
remain preserved, but completing their old multi-round sequence is not a
prerequisite for continuing independent reusable-capability work.

The candidate is qualified only when a clean fixed tree proves all of the
following:

- clean install, upgrade from the current downstream lock, recovery, and
  rollback-compatible behavior;
- zero-skipped security, cross-Tenant isolation, origin protection, stale
  idempotency recovery, nested savepoint, and failure-atomicity tests;
- desktop and mobile browser flows for login, Tenant selection, context, menu,
  common administration, a fictional external Module, refresh, denial, Module
  disablement, and Tenant switch cleanup;
- unchanged P0 performance gates plus focused P1 regression measurements;
- reproducible fixed starter creation, install, typecheck, test, build, start,
  and external package consumption;
- content status, executable documentation, OpenAPI, Runtime coverage,
  dependency, license, secret, and architecture checks;
- an independent fixed-commit review and a separate approval before the
  downstream lock changes.

## Task Graph

```text
P1-B01 candidate (complete)
  -> P1-B02 candidate (independent worktree)

P1-R01 operation atomicity
  -> P1-R02 external operation host kit
      -> P1-B03 minimal settings
      -> P1-B04 minimal reference codes

P1-W01 protected transport origin
  -> P1-W02 host runtime and Module routing
      -> P1-W03 workspace shell and common contributions

all Starter v1 candidate slices
  -> P1-Q01 fixed-commit qualification
```

P1-R01 and P1-W01 may execute in parallel because their write sets are
disjoint. P1-B03 and P1-B04 require separate migrations, manifests, OpenAPI
artifacts, and commits; their planning may be parallel, but generated artifacts
and integration are serialized. Additional independently scoped Starter v1
capabilities may continue before Q01. P1-Q01 itself is exclusive and begins
only after the project fixes the Starter v1 candidate commit.

## Parallel Workspace Rule

Concurrent worktrees must not share Docker Compose project names, database
ports, cache ports, test database names, browser ports, or generated output
directories. Each task contract must provide a unique local namespace. A gate
run affected by another worktree's containers is invalid evidence and must be
rerun in an isolated environment.

## Per-Task Contract

Before runtime editing, each task must extend the required task contract in the
[P1 Execution Baseline](./p1-execution-baseline.md) with:

- exact prerequisite commit and file whitelist;
- schema owner, migration, rollback, and upgrade behavior;
- API, permission, data-policy, audit, idempotency, concurrency, and error
  contracts;
- focused failing tests and Runtime coverage owner;
- independent commit and qualification stop line.

No implementation task may infer these details from this aggregate plan.

## Completion And Stop Line

This plan is complete only when P1-Q01 records a reviewed Starter v1 fixed
commit and a separate approval permits a new exact downstream lock.

Until then:

- the current downstream lock remains `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`;
- P1 task commits remain unqualified candidates;
- no application may represent a moving branch as its dependency;
- no public package, tag, release, production, generator, or compatibility
  claim is approved;
- no application-specific business model may enter the Kernel, packages,
  starter, examples, reference host, or documentation.
