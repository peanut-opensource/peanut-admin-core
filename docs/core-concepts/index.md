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

`MemberAdminService::createAdministrator()` and `updateAdministrator()` own one native ThinkPHP transaction
for an administrator form: account and credential creation where applicable, member profile and
department, role assignment, status transition, revision increments and success audits commit
together. The host supplies the authorized tenant actor; Core enforces tenant activity, scoped
relations, the expected member revision and the final active owner guard. Any exception rolls
back the complete command. Existing member lifecycle commands remain independently transactional.
Disabled creation stays pending, and editing never changes account credentials.
Consumers must lock a Core version containing these commands before adopting them;
source implementation alone is not package or downstream qualification.
Development commit `61287a9` provides the native Core transaction implementation
adopted by Application commit `14ce7b1b`.
Application commit `e67acd72` verifies the development-only local package
link, full startup, shared connection state, nested handling and failure
rollback against the registered development database. Remaining domain PDO
callers still await their atomic migration. Core development commits `e0102fc`
and `cab7415` use that boundary for atomic operations and ReferenceCodes, but
the ReferenceCodes MySQL/Edition gate is still pending and none of this evidence
by itself qualifies or publishes a new Core package. Settings and
ArtifactRevision now also use injected ThinkPHP stores in development source.
ArtifactRevision keeps its cross-module repository and Workflow resolver
semantics, but removes the repository's PDO accessor and internal service
assembly; its dynamic database, consumer-ownership and both-Edition gates remain
pending. EntitlementQuota and Workflow follow the same development boundary:
their business repositories remain, their one implementations are ThinkPHP
stores, Runtime dependencies are constructor-injected, and Workflow adapters
carry business values rather than PDO identity. Their compensation, concurrency
and cross-domain transaction evidence is still pending the registered database
Gate.
Kernel Tenant and Platform Authorization likewise retain their business
repositories because authorization evaluation is consumed across Kernel,
DataPermission and Host composition, while their production implementations
now use only the existing ThinkPHP principal/RBAC/Module Models. Dynamic
isolation and both-Edition qualification have not been rerun for those changes.
Authorization revision bumps similarly route through the owning Tenant, member,
role and Module Models and remain database-atomic.
Tenant and Platform authentication remain separate audiences with separate
session repositories; their credential lockout, challenge, token rotation and
security-event state now persists through native Models without collapsing the
two business contracts.
The Menu Catalog follows the same boundary: Host, Workspace and Upgrade callers
retain one business contract, and its implementation uses the existing Menu,
Permission and Module Models without a Db facade.
Module Runtime availability and Tenant mutation now use those same native
Tenant and Module Models; deployment status, Tenant enablement, row locks and
revision increments remain separate fail-closed authorities.
Tenant Module configuration also uses those Models without weakening its
transaction, locked revision check, authorization invalidation or audit event.
System/default Tenant resolution and entry-host binding now share the native
Tenant/Binding Model boundary while preserving strict active-state and unique
binding checks.
Tenant and Platform Workspace projections use those same native Models plus
the dedicated principal, role, permission, Module and audit Models, and still
normalize identifiers and revisions at the API boundary.
Account self-service uses the owning identity, session and security Models for
profile and password mutations while preserving its transaction, cross-audience
revocation and password-attempt advisory lock. Audit creation also stays on its
dedicated Models.
Department administration likewise uses the owning organization, Tenant and
member Models without weakening hierarchy locks, cycle/depth validation,
revision checks, authorization invalidation or audit atomicity.
Tenant role administration similarly uses the owning RBAC, Module and Tenant
Models while retaining assignability rules, locked revision checks and the
shared authorization/audit transaction.
Tenant member administration uses the existing identity, membership,
organization, RBAC and Tenant Models while preserving account reuse, final
active-owner protection and atomic security/authorization revisions and audit.
Platform Tenant governance uses the owning Tenant, operator, built-in Role and
TenantModule Models while preserving lifecycle revisions, owner activation
requirements, Module manager validation and Platform/Tenant audit visibility.
Tenant owner candidate provisioning uses the same native identity, membership,
RBAC, Tenant, operator and audit Models while retaining candidate uniqueness,
locked activation revisions and idempotency evidence lookup.
Platform access administration uses the existing identity, operator/RBAC,
permission and session Models while retaining serialized control-plane changes,
final-control-admin protection, revision checks and audit atomicity.
Fresh-install Bootstrap uses the same Platform/Tenant role and assignment
Models while retaining its native transactions, owner activation sequence and
one explicit MySQL advisory lock for the first Platform owner.
The shared permission/resource/operation/target/condition catalog Models belong
to Kernel Authorization. DataPermission consumes them alongside its own
Tenant-owned policy Models, so package direction matches table ownership.
Native transactions, MySQL advisory locks and Edition `information_schema`
validation are the intentional non-Model framework boundaries that remain.
ArtifactRevision, EntitlementQuota, FileMedia and Settings use native Raw
expressions for atomic updates and database time. DataPermission policy reads
normalize Model rows and use the Kernel Department Model for hierarchy closure.

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

Product versions and customer release sequences are distinct: see [Product version identity](../architecture/product-version-identity.md). A customer Instance keeps an independent `instance_version` and an explicit `source_product_version`; that instance number does not select Core packages or Module migrations.

A `ProductProfile` is a version-controlled installation recipe. It can select modules, initial menu contributions, and optional setup such as a default root department. It is not an authorization record and is not stored as a P0 runtime table.

A `Module` owns a reusable capability. `TenantModule` records whether a deployed module is open for a tenant. Effective access requires all three conditions: the module is installed, the tenant has it open, and the member has the required functional and data permissions.

A Module provider declares contract bindings as compatible implementation classes
by default. Host-owned startup factory closures are limited to configuration,
provider/Edition selection, vendor SDK, framework callback and mutable Worker
state that cannot be represented by a class binding. Core validates and combines
those declarations; the Host's single composition root is the only code that
invokes and applies them. The [accepted ThinkPHP 8 Runtime direction](../architecture/index.md#accepted-thinkphp-8-runtime-direction)
defines the migration and test gates.
