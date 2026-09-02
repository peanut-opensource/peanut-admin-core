# Core Concepts

## Identity And Membership

| Concept | Meaning |
| --- | --- |
| Credential | A verified login mechanism. P0 implements email and password only. |
| Account | The global login identity found through a credential. |
| Tenant | The customer, operating organization, and SaaS isolation root. |
| TenantMember | An account's membership, status, roles, and permissions inside one tenant. |
| PlatformOperator | A separate platform-governance identity that does not become a tenant member implicitly. |
| Department | A tenant-internal organization tree used for people management and data scope. It is not a child tenant. |

An account may join several tenants through separate `TenantMember` records. Login first authenticates the account, then resolves the selected active tenant and active membership. Switching tenants creates a new trusted tenant session; a client-supplied `tenant_id` never establishes authority.

## Tenant And Business Targets

A tenant may manage many categories and many instances in each category: several projects, stores, warehouses, suppliers, or domain-specific targets. These objects belong to their modules and do not enter the Kernel as a universal subject table.

A member keeps one membership even when managing several targets. Permissions may express different sets per operation:

```text
read example.project {A, B}
update example.project {A}
read example.queue {Q1, Q2}
```

Each target set contains one registered target type. Cross-category requests use separate typed sets. The session does not store a current target; each operation resolves and validates requested targets through the owning module.

## Operation Cardinality

Operations declare how targets may be used:

| Cardinality | P0 behavior |
| --- | --- |
| `none` | The operation has no business target. |
| `one_required` | Exactly one validated target is required; this is the default for ordinary writes. |
| `many_readable` | A list may read several authorized targets and show target ownership. |
| `aggregate_read` | A read-only aggregate may summarize several authorized targets. |
| `policy_publish` | One policy is published with a separate result per target. |
| `bulk_write` | Disabled in P0 unless a later contract explicitly designs and qualifies it. |

## Product Profiles And Modules

A `ProductProfile` is a version-controlled installation recipe. It can select modules, initial menu contributions, and optional setup such as a default root department. It is not an authorization record and is not stored as a P0 runtime table.

A `Module` owns a reusable capability. `TenantModule` records whether a deployed module is open for a tenant. Effective access requires all three conditions: the module is installed, the tenant has it open, and the member has the required functional and data permissions.

A Module provider declares contract bindings as compatible implementation classes
or Host-owned startup factory closures. Core validates and combines those
declarations; the Host's single composition root is the only code that invokes
and applies them.
