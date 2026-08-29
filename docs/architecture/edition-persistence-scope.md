# Edition-neutral persistence scope

Core does not know product Edition names. Hosts choose one explicit persistence scope when composing
repositories and Schema:

- `tenant-scoped` stores and filters a Tenant ownership column and is the default;
- `instance-scoped` keeps trusted logical context for authorization, audit and public records, while
  storing one physical application partition without a Tenant ownership column.

The choice is made at the host composition boundary. Domain services do not branch on deployment mode.
Each repository validates that its owned tables match the explicitly selected scope before any operation;
it never infers a scope from Schema or falls back to another mode. Cross-mode data conversion belongs to
the host because it changes ownership and recovery semantics.

The executable contract and exact current write set are recorded in
[`P1-ED01`](../status/p1-ed01-edition-persistence-scope-contract.md).
