# Edition-neutral persistence scope

Core does not know product Edition names. Hosts choose one explicit persistence scope when composing
repositories and Schema:

- `tenant-scoped` stores and filters a Tenant ownership column and is the default;
- `instance-scoped` keeps trusted logical context for authorization, audit and public records, while
  storing one physical application partition without a Tenant ownership column.

The choice is made at the host composition boundary. Domain services do not branch on deployment mode.
Each repository validates that its owned tables match the explicitly selected scope when its first
operation begins, before any repository SQL can read or modify data, and reuses that successful result
for the lifetime of the repository instance. Constructing a repository or composing a Host has no
database side effect. The repository never infers a scope from Schema, skips validation for another PDO
driver or falls back to another mode. Cross-mode data conversion belongs to the host because it changes
ownership and recovery semantics.

That paragraph describes the current Alpha.13 PDO implementation, not the
long-term persistence API. Under the [accepted ThinkPHP 8 Runtime direction](./index.md#accepted-thinkphp-8-runtime-direction),
the same scope and trusted-context invariants move to the owning ThinkPHP Models,
Queries and TenantScope. A migration batch must preserve both physical schemas
and remove its corresponding repository/PDO path atomically; it may not emulate
an Edition with a scope bypass or keep a second persistence implementation.

ReferenceCodes development commit `cab7415` is the first domain migration on
that boundary: its three owned tables retain their explicit Tenant identity and
the old PDO repository is removed in favor of one injected ThinkPHP connection.
This source result does not qualify either Edition; the registered MySQL
isolation/concurrency run and the fixed Standalone/Multi-tenant candidate remain
required before delivery.

Settings and ArtifactRevision now use the same injected ThinkPHP connection
boundary in development source. Their Tenant identity, optimistic concurrency,
rollback and immutable revision semantics remain unchanged. ArtifactRevision's
cross-module repository contract remains, but no longer exposes a PDO
connection; its existing Workflow connection-identity port is owned by the next
Workflow batch. These source changes do not qualify either Edition.

EntitlementQuota and Workflow also retain their Tenant columns and ownership
contracts while replacing the old PDO repositories with injected ThinkPHP
stores. Workflow notification/task intents stay cross-domain business
contracts; PDO identity is no longer part of those adapter APIs. Dynamic
compensation, concurrency, rollback and both-Edition qualification remain
required.

Kernel Tenant and Platform Authorization also keep their cross-capability
repository contracts while their production implementations read principals,
roles, Modules and permissions through native ThinkPHP Models. Explicit Tenant
predicates and the existing authorization revision hashes remain unchanged;
database and both-Edition qualification are still pending.
The shared authorization revision repository routes each Tenant, member, role
and Module increment through its owning Model while retaining explicit Tenant
identity and a database-atomic increment.
Tenant and Platform authentication also keep their distinct audience contracts
while credential, challenge, session/token and security-event persistence uses
native Models. Locking, conditional state changes and atomic security revision
increments remain part of those state machines.
The shared Menu Catalog also uses native Menu, Permission and Module Models for
both deployment and Tenant projections; its explicit Tenant condition and
cross-Host business contract remain intact.
Module Runtime mutation likewise retains explicit Tenant ownership and
database-atomic revision increments while using the native Tenant and Module
Models for both physical Edition layouts.
System/default Tenant resolution uses the same native Tenant Model in both
layouts, while entry-host binding preserves its explicit Tenant identity and
fail-closed cardinality checks.

The executable contracts and exact write sets are recorded in
[`P1-ED01`](../status/p1-ed01-edition-persistence-scope-contract.md) for Idempotency, Task/Job and
Import/Export, and [`P1-ED01-R01`](../status/p1-ed01-r01-settings-persistence-scope-contract.md) for
Settings.
