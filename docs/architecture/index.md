# Architecture

Peanut Admin P0 is a modular monolith in one public monorepo. It uses PHP 8.3 and ThinkPHP 8 for the reference backend, Vue 3 and TypeScript for Admin Web, MySQL 8 for persistence, and a replaceable cache adapter.

## Repository Layers

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
