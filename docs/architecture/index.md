# Architecture

Peanut Admin Core is a modular monolith in one public monorepo. Its reference backend uses PHP 8.3 and ThinkPHP 8; Admin Web uses Vue 3 and TypeScript, persistence uses MySQL 8, and cache uses a replaceable adapter. The current Core package still exposes PDO-backed persistence and atomic-command contracts. Converging those contracts on ThinkPHP Model/Query/Db/Transaction is an accepted future direction recorded in `repo://peanut-admin/docs/architecture/core-thinkphp-runtime-direction-adr.md`. The 3.1.0 identity alignment does not implement that migration or create a framework-neutral support promise.

## Repository Layers

The [version identity contract](./product-version-identity.md) aligns Core PHP/Web, the Peanut Admin product Application and both Editions for new releases. Module versions and customer Instance versions remain independent; immutable source and package digests identify the actual artifacts.

| Layer | Responsibility |
| --- | --- |
| `packages/php` | The `peanut-admin/core` Composer package; internal directories preserve Kernel and domain ownership. |
| `packages/web` | The `@peanut-admin/admin` npm package; explicit subpath exports expose Core, Shell, and domain contributions. |
| `backend` | ThinkPHP reference host, HTTP adapters, configuration, and CLI composition. |
| `frontend` | Reference Admin Web application consuming only public web-package exports. |
| `docs` | Versioned developer manual, decisions, generated references, and status. |
| `examples` | Fictional contract examples; never product-specific business logic. |

Hosts install the two public packages and compose their explicit APIs. Internal
domains do not depend on host internals, and a host must not deep-import a
domain's private files.

## Accepted ThinkPHP 8 Runtime Direction

The Application and Core supported PHP runtime is ThinkPHP 8. There is no
supported non-ThinkPHP production consumer, and Core is not pursuing
framework-neutral persistence. The canonical cross-repository decision is
`repo://peanut-admin/docs/architecture/core-thinkphp-runtime-direction-adr.md`.
This section is Core's current projection of that decision; it does not claim
that the source migration has happened.

The source audit is fixed to Application
`9781ce0de5588f1ea3afaaf46023b20c49911b4a` and Core
`6aeeb52789fb49a2113bb6bab541629374dc6803`:

| Area | Audited current fact |
| --- | --- |
| Application data access | 31 `TenantOwnedModel` subclasses and broad Model/Query/TenantScope use coexist with 87 production files that mention PDO. `AppService` obtains the ThinkPHP connection as PDO and binds it into the container. |
| Application composition | 10 ModuleProviders declare 55 bindings: 5 class mappings and 50 closures. Providers contain 105 explicit `make()` calls; the production source contains 202 explicit container `make()` calls, 23 Repository, 5 Adapter, 15 Factory and 7 `RuntimeFactory` declarations. |
| Application Commands/Queries | 20 Command and 8 Query contract files do not expose PDO or Models. Only contracts with real cross-Module or Host consumers remain long term. |
| Core package persistence | `packages/php/*/src` contains 555 PHP files; 60 mention PDO, 35 are `Pdo*` files, and 26 of 47 Repository files are `Pdo*Repository`. Publishable source currently has no ThinkPHP imports, Model, `Db::` or container `make()` use. |
| Core Commands/Queries | A name scan finds 15 `*Command*`/`*Query*` files, mixing Host contracts, query services, constraints/compilers, DTOs and PDO implementations rather than one uniform CQRS layer. Retention is decided by semantics and real consumers, not names. |
| Reference host and starter | 85 production files reference ThinkPHP while 66 still reference PDO; 12 RuntimeFactory classes and 18 concrete ModuleProviders hand-assemble much of the PDO graph. HTTP enters ThinkPHP, but installation, upgrade, health and worker paths have not yet converged on one data/bootstrap path. |

The target is one ThinkPHP composition and data model: Core and Application
each own their tables and Models; tenant-owned ordinary business access uses
ThinkPHP Model, Query and the registered TenantScope; writes use the formal
ThinkPHP Db/Transaction boundary. HTTP, CLI, Worker, Cron, installation,
migration and seed execution all enter the same ThinkPHP bootstrap. Standalone
physical schemas, platform scope and the minimum empty-schema installation
context remain narrow governed boundaries, not ordinary TenantScope bypasses.

| Existing abstraction | Decision |
| --- | --- |
| Public PDO parameters and `Pdo*Repository` | Replace by domain and delete from public Runtime APIs; do not add a compatibility bridge, dual implementation or dual write. |
| `PdoTransactionManager` | Preserve atomicity, savepoint, rollback and concurrency semantics through ThinkPHP Transaction/Db, then delete the PDO manager. |
| Repository/Adapter | Delete persistence interfaces that only mirror the single ThinkPHP implementation; keep real cross-Module business contracts and external-system adapters. |
| RuntimeFactory | Replace repeated PDO/service-graph factories with constructor injection and the single Host composition root; keep only factories with real lifecycle or dynamic selection value. |
| Commands/Queries/Contracts | Keep real cross-Module or Host business capabilities without PDO, Model or private table leakage; remove unused mirror contracts with their callers. |
| ExecutionContext | Keep the trusted context semantics and establish/clear them at every shared bootstrap entry; an unused duplicate type is not protected merely by its name. |
| ModuleProvider | Ordinary dependencies are `Interface::class => Implementation::class`. Closures and explicit `make()` are limited to primitive configuration, Edition/provider selection, vendor SDKs, framework callbacks and mutable Worker/lease/registry state, with the reason documented. |
| Vendor SDK, HTTP transport, Storage Driver | Keep as genuine replaceable external boundaries. Application owns credentials, authorization, object ledger, compensation and product lifecycle. |

Migration order is fixed and must not be reordered by an implementation task:

1. ModuleProvider simplification.
2. Core ThinkPHP data boundary.
3. ReferenceCodes.
4. Settings.
5. ArtifactRevision.
6. EntitlementQuota / Workflow.
7. Notification.
8. TaskJob.
9. ImportExport, using the existing stable FileMedia business contract without reaching into its private tables.
10. FileMedia, retaining and qualifying Storage Drivers.
11. DataPermission.
12. Kernel Identity / Tenant / RBAC.

Each domain batch replaces its implementation, actual callers, public contract
and composition atomically, then deletes that domain's old PDO path. It must not
leave a long-lived bridge, second implementation, dual write or mirror API.
Repository-wide regular-expression replacement is prohibited. Existing test
assertions remain real: no assertion weakening, skips, early-exit scripts or
`PASSED` placeholders may be used to claim completion.

Qualification for every batch must prove tenant isolation, transaction rollback,
savepoint and concurrent claim/lock/idempotency behavior, and deterministic
Standalone/Multi-tenant behavior from the same frozen source. Missing trusted
context, a scope bypass, a second connection, a weakened test or Edition drift
stops that batch.

Removing Core's public PDO constructors, repositories or transaction contracts
is a breaking source change. An independent Core `0.2.0-alpha.1` line is
rejected because accepted product identity aligns Application, Core PHP/Web and
both Editions. If convergence lands before the first coordinated 3.1.0 release,
the whole product must freeze a new coordinated 3.1.0 prerelease. If a
PDO-preserving 3.1.0 is released first, the whole product selects its next
breaking version later. The failed current 3.1.0 candidate must not be reused.

## Isolation Order

Every tenant operation follows this order:

```text
authenticate tenant audience
-> resolve active Account, Tenant, and TenantMember
-> confirm deployed Module and active TenantModule
-> check functional Permission
-> resolve typed requested targets
-> apply DataPermission provider to query or object action
-> execute application service
-> write audience-aware audit event
```

Tenant-scoped persistence tables use `tenant_id NOT NULL`. Tenant identifiers come from trusted server context, remain immutable after creation, and participate in tenant-local uniqueness and cross-table constraints. `0`, `NULL`, or a magic string never represents platform scope. A host that distributes an instance-scoped artifact keeps the same trusted logical context while omitting Tenant ownership columns only through the explicit [edition-neutral persistence scope](./edition-persistence-scope.md) contract.

## Functional And Data Authorization

Functional RBAC and data permission are independent. A visible menu is not API authorization. A granted operation still cannot access data outside the provider result.

Data authorization is applied consistently to lists, details, creates, updates, deletes, aggregates, imports, exports, asynchronous handlers, and scheduled work. Providers expose both query restrictions and single-object action checks. Missing providers and unresolved target types deny access.

Tenant isolation is always an intersection. Within a data authorization, different dimensions intersect; effective grants from valid roles and assignments may union. P0 has no super-user flag, implicit relationship inheritance, arbitrary policy expression, or silent platform bypass.

## Module Ownership

A module owns each table, model, repository, migration, domain rule, API resource, permission, target type, and public service contract it defines. Another module may call that public contract but may not write, join, or migrate the owner's private tables. The host owns its Module roots, PHP namespace, frontend root, managed table prefixes, and reserved framework-table list; none of those application conventions are inferred from the Module key.

Cross-module writes are coordinated by application use cases with explicit transaction boundaries. Events are published after commit. A future service split may reuse ownership and API contracts, but P0 does not promise cost-free microservice extraction.

Each `ModuleProvider` contributes a deterministic map from contract classes to
compatible implementation classes or Host-owned startup factory closures. The
Host collects those maps in compiled Module order, rejects duplicate or invalid
contracts, and only its single composition root invokes the factories and mutates
the framework container. Business services do not resolve dependencies from the
container or create a second service graph.

## Shared Master Data

Some records have one canonical identity but different owners and scopes. The shared-master contract keeps one table and identifier space. A scope provider decides whether a tenant or typed target may view, use, or maintain a record. Consumers store the stable identifier and call the owner's public contract; they do not join the owner's table or union separate platform and tenant pools.
