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

The executable contracts and exact write sets are recorded in
[`P1-ED01`](../status/p1-ed01-edition-persistence-scope-contract.md) for Idempotency, Task/Job and
Import/Export, and [`P1-ED01-R01`](../status/p1-ed01-r01-settings-persistence-scope-contract.md) for
Settings.
