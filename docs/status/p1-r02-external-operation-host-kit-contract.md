# P1-R02 External Operation Host Kit Contract

## Status

```text
task: P1-R02
state: candidate
prerequisite: 536bca2c0676f17c8d6405b4689b0d994ba3b38b
contract_commit: efa2318c34d0f48b568381bc68672d9e94954851
schema_owner: external host and fictional fixture Module
migration: no Kernel migration
dependency_change: none
runtime_ledger_change: none
qualification: candidate only
```

P1-R02 provides a framework-neutral PHP composition kit for an external host.
It binds a host-owned HTTP operation to trusted Tenant or platform context,
Module availability, functional permission, typed-target data authorization,
P1-R01 atomic command primitives, and RFC 9457 Problem Details without copying
the reference host.

Implementation starts only from the exact prerequisite above and only after
this contract is committed independently. Uncommitted files from another
worktree are not an input.

## Objective And Non-Goals

The kit must let a host register explicit operations and compose existing
Peanut Admin capabilities in a fixed fail-closed order. The host keeps its
application namespace, routes, API prefixes, OpenAPI source, generated route
artifact, generated TypeScript types, domain schema, commands, queries, audit
event names, and optional outbox schema.

This task does not add:

- a generic repository, CRUD engine, controller generator, route generator, or
  public project generator;
- an application domain table, state machine, category, inventory, location,
  commerce, or other product model;
- a second authentication, permission, Module, target, transaction,
  idempotency, audit, or error model;
- a Kernel outbox table, delivery worker, retry loop, distributed transaction,
  or cross-database atomicity;
- a reference-host API route or Runtime coverage row;
- a dependency, package publication, release, compatibility promise, or
  downstream-consumption approval.

## Host-Owned Configuration

`ExternalHostConfiguration` accepts and validates:

- one existing `ModuleHostLayout` for the host backend root, PHP namespace
  root, and frontend root;
- a non-empty list of host-owned Module manifest roots;
- a Tenant API prefix and a separate platform API prefix;
- paths to the host-owned OpenAPI source, generated route artifact, and
  generated TypeScript types;
- a non-empty list of server-registered Client keys;
- the trusted request-ID header name.

API prefixes must be distinct absolute path prefixes with no traversal,
query, fragment, placeholder, or trailing slash. Artifact and Module paths must
be normalized host-relative paths and cannot escape the host root. Client keys
and the request-ID header use bounded ASCII identifiers. The configuration
does not accept a Tenant ID, operator ID, account ID, permission result, target
result, route handler, or domain value.

The host passes the same `ModuleHostLayout` and manifest roots to the existing
`ModuleRegistryCompiler`. R02 does not introduce another manifest compiler or
Module path convention. OpenAPI and generated artifacts remain outside the
Kernel package and operation totals remain host-owned.

## Operation Contract

Each `ExternalOperationDefinition` declares:

- stable `operationId`, HTTP method, concrete host path, and audience;
- owning Module key;
- an existing `PermissionRequirement`, including explicit `all` or `any`
  matching;
- optional protected resource key;
- data-authorization mode: `none`, `query`, or `targets`;
- target cardinality: `none`, `one_required`, `zero_or_one`, or
  `many_readable`;
- whether the operation is an atomic command and whether it requires an
  `Idempotency-Key`.

The audience must match the permission requirement. Tenant operations must be
under the configured Tenant prefix and platform operations under the platform
prefix. Read operations cannot request atomic execution or idempotency. Atomic
commands use `POST`, `PUT`, `PATCH`, or `DELETE`; every fictional create,
update, and status command requires idempotency.

The fictional external Module is `fixture.record`. It exists only in executable
tests and examples and proves:

```text
GET   {tenant_prefix}/fixture/records
GET   {tenant_prefix}/fixture/records/{record_id}
POST  {tenant_prefix}/fixture/records
PATCH {tenant_prefix}/fixture/records/{record_id}
POST  {tenant_prefix}/fixture/records/{record_id}/status
```

These operations prove list, detail, create, update, and status behavior. They
do not define a reusable domain model or enter the reference host.

## Trusted Context And Execution Order

`ExternalOperationRequest` carries a sanitized `RequestId`, an already-created
trusted context object, method, path, body, typed-target input, optional raw
idempotency header, and explicit UTC comparison and expiry times. Request body,
query, route variables, and headers cannot create or replace the context.

`TrustedContextAdapter` accepts only:

- `TenantContext` created from `ValidatedTenantSession` for a Tenant operation;
- `PlatformContext` created from `ValidatedPlatformSession` for a platform
  operation.

It verifies audience, registered Client key, and request-ID continuity. Missing
or mismatched context fails before Module, permission, target, or domain code.

`ExternalOperationHost` executes in this order:

1. validate the operation against Host configuration and the request method and
   path;
2. require the trusted audience context;
3. require the Module in the compiled registry;
4. require active deployment installation and, for Tenant operations, an
   enabled and effective Tenant Module record;
5. evaluate the declared functional permission through the existing
   `PermissionMiddleware` and `PermissionRequirement`;
6. parse typed targets through existing `TypedTargetInput` and authorize them
   through the existing `DataPermissionAdapter`;
7. invoke a read handler or enter the R01 atomic command adapter.

No domain handler runs after a failed step. There is no super-user bypass,
silent permission fallback, implicit Module enablement, body-supplied Tenant,
or target-existence fallback.

## Typed-Target Contract

`TypedTargetAdapter` preserves the existing Kernel target representation:

- `none` rejects supplied targets;
- `one_required` requires exactly one typed target with one ID;
- `zero_or_one` accepts zero or one typed target;
- `many_readable` accepts zero or more non-duplicated typed target sets;
- `query` returns the existing engine constraint to the host query handler;
- `targets` calls the existing target assertion before a domain command.

The adapter never infers a target resource key, role, resolver, Provider,
operation, or cardinality. Malformed input is `422`. A missing declaration,
resolver, Provider, wrong target category, cross-Tenant target, or denied target
fails closed and does not expose a raw target identifier or implementation
class.

## Atomic Command Contract

`AtomicOperationAdapter` receives one caller-owned PDO and constructs the R01
`PdoTransactionManager`, `PdoIdempotencyRepository`, and
`PdoAuditRepository` on that same PDO. For an idempotent command it:

1. enters `PdoTransactionManager::run()`;
2. acquires the Tenant-member or platform-operator idempotency record;
3. returns a safe terminal replay or rejects a non-owned processing record;
4. invokes the application-owned domain callable with the same PDO;
5. appends the application-declared redacted audit evidence;
6. invokes an optional application-owned outbox callable with the same PDO;
7. completes the idempotency record with the safe response;
8. returns so the transaction manager commits.

The domain result carries only response status/body, audit event/action,
scalar redacted metadata, and optional resource identity. The host is
responsible for ensuring replay bodies and audit metadata contain no secret,
credential, SQL, stack trace, private path, hidden target existence, or raw
authorization input.

Unexpected exceptions roll back domain, audit, outbox, and idempotency state.
R02 does not take over expired `processing` records and does not modify R01
primitives. Cross-connection and cross-database writes are outside the
guarantee.

## Problem Details And Security

`ProblemDetailsAdapter` reuses `ApiException`, `ProblemDetails`, and
`RequestId`. It maps known context, Module, functional authorization,
data-authorization, validation, idempotency, and transaction failures to stable
HTTP responses with `application/problem+json`.

- missing authentication is `401`;
- functional denial is `403`;
- unavailable Tenant Module and denied or invalid target visibility are
  non-enumerating `404` responses;
- invalid request or target shape is `422`;
- idempotency conflict is `409`;
- failed Module deployment is `503`;
- unknown exceptions are `500 INTERNAL_ERROR` with a generic detail.

Responses never expose SQL, stack traces, tokens, credentials, filesystem
paths, Provider classes, raw target identifiers, or cross-Tenant existence.
The request ID is preserved in every Problem Details response.

## Exact File Whitelist

The contract commit may change only:

- `docs/status/p1-r02-external-operation-host-kit-contract.md`;
- `docs/content-status.json`;
- `docs/status/p1-downstream-module-readiness-plan.md`;
- `docs/status/index.md`.

The implementation commit may add only:

- `packages/php/kernel/src/Host/ExternalHostConfiguration.php`;
- `packages/php/kernel/src/Host/ExternalOperationDefinition.php`;
- `packages/php/kernel/src/Host/ExternalOperationRequest.php`;
- `packages/php/kernel/src/Host/ExternalOperationResponse.php`;
- `packages/php/kernel/src/Host/ExternalOperationResult.php`;
- `packages/php/kernel/src/Host/AuthorizedExternalOperation.php`;
- `packages/php/kernel/src/Host/TrustedContextAdapter.php`;
- `packages/php/kernel/src/Host/ModuleAvailabilityAdapter.php`;
- `packages/php/kernel/src/Host/PermissionAdapter.php`;
- `packages/php/kernel/src/Host/TypedTargetAdapter.php`;
- `packages/php/kernel/src/Host/AtomicOperationAdapter.php`;
- `packages/php/kernel/src/Host/ProblemDetailsAdapter.php`;
- `packages/php/kernel/src/Host/ExternalOperationHost.php`;
- `packages/php/kernel/tests/Unit/Host/ExternalHostConfigurationTest.php`;
- `packages/php/kernel/tests/Unit/Host/ExternalOperationDefinitionTest.php`;
- `packages/php/kernel/tests/Unit/Host/ExternalOperationHostTest.php`;
- `packages/php/kernel/tests/Unit/Host/TypedTargetAdapterTest.php`;
- `packages/php/kernel/tests/Unit/Host/ProblemDetailsAdapterTest.php`;
- `packages/php/kernel/tests/Integration/Host/ExternalOperationHostIntegrationTest.php`;
- `examples/external-host/README.md`;
- `examples/external-host/ExampleExternalHostContractTest.php`.

The implementation commit may minimally update only:

- `README.md` for current candidate status;
- `docs/guide/module-development.md` for public host-kit usage;
- `docs/status/index.md` for current candidate status;
- `docs/status/p1-r02-external-operation-host-kit-contract.md` to change the
  state from `implementation-ready` to `candidate` and record verification.

No other shared file, generated artifact, dependency lock, schema, migration,
reference-host route, Runtime coverage ledger, R01 primitive, parent-repository
file, or consumption lock is in scope. If this whitelist is insufficient,
implementation stops for a contract amendment in a separate commit.

## Test Ownership And Acceptance

`P1-EXTERNAL-HOST-001` owns the executable evidence. Tests are written failing
before source implementation and must prove:

- invalid namespace, roots, API prefixes, artifact paths, Client keys, and
  request-ID headers are rejected;
- operation method, path, audience, permission match, command, idempotency,
  data mode, and cardinality contradictions are rejected;
- body or route Tenant identifiers cannot establish trusted context;
- Tenant and platform contexts and Client keys cannot cross audiences;
- a missing, unregistered, inactive, disabled, or ineffective Module fails
  before the handler;
- permission `all` and `any` retain existing semantics and denied permission
  does not invoke target or domain code;
- malformed, duplicate, wrong-category, unauthorized, and cross-Tenant targets
  fail closed;
- list receives a query constraint and detail/create/update/status receive
  authorized typed targets;
- the fictional Module keeps records isolated by trusted Tenant context;
- first idempotent execution, exact replay, request-hash conflict, and live or
  expired processing rejection follow R01 behavior;
- injected failure after domain, audit, outbox, or completion leaves no partial
  domain, audit, outbox, or idempotency state;
- Problem Details has the correct content type, stable code, and request ID and
  does not disclose sensitive internals;
- existing P0 and R01 unit/integration, PHPStan, Deptrac, formatting, Module,
  documentation, external-host, and diff checks remain green.

The isolated local namespace is:

```text
compose_project: peanut-admin-p1-r02
mysql_port: 33382
cache_port: 36382
backend_port: 38082
frontend_port: 35182
database: peanut_admin_p1_r02_external_host_test
```

Focused verification runs before broader checks. The final required commands
are the affected PHPUnit unit and integration tests, the external-host example,
`./scripts/test-unit`, `./scripts/test-integration`, PHPStan, Deptrac, PHP CS
Fixer check, Module and documentation checks, `git diff --check`, and, once
stable, `./scripts/check` in the isolated environment.

## Implementation Evidence

The candidate implementation adds only the whitelisted Kernel Host classes,
unit and integration tests, and fictional external-host example. It adds no
schema migration, dependency, reference-host route, generated artifact, or
Runtime ledger row.

Current executable evidence includes:

- Host unit and external example: 24 tests and 58 assertions;
- isolated MySQL R02 integration: 3 tests and 37 assertions;
- aggregate PHP unit and contract suites: 162 tests and 2630 assertions;
- aggregate PHP integration, install, upgrade, and health suites: 127 tests and
  1193 assertions;
- focused PHPStan with no errors and Deptrac with zero violations, skipped
  violations, uncovered dependencies, warnings, or errors.

The final isolated `./scripts/check` completed with `Repository checks: OK`.
It retained 75 P0 and 4 existing P1 reference-host operations, passed 162 PHP
unit/contract tests with 2630 assertions, 57 frontend unit tests, 127 PHP
integration/install/upgrade/health tests with 1193 assertions, all four
zero-skipped PHP security groups, 36 desktop/mobile browser tests, recovery,
backup/restore, performance, starter, documentation, PHPStan, Deptrac,
formatting, lint, typecheck, builds, and workspace checks. The final
post-evidence checks are documentation status and `git diff --check`.

## Stop Line

The implementation is one independently reviewable commit after the canonical
contract commit. Completion makes R02 only an unqualified P1 candidate. It does
not move `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`, authorize a downstream
consumer, publish a package, create a tag or release, claim production
readiness, or start P1-B03, P1-B04, or P1-Q01.
