# Kernel Schema And States

Kernel tables use the `pa_` prefix and MySQL `utf8mb4_0900_ai_ci`. Tenant-owned tables require `tenant_id NOT NULL`; platform tables do not use a magic tenant identifier.

## Identity And Tenancy

| Table | Purpose | Main states |
| --- | --- | --- |
| `pa_account` | Global login identity | active, locked, disabled, closed |
| `pa_credential` | P0 email-password credential | active, locked, revoked |
| `pa_tenant` | SaaS customer and isolation root | provisioning, active, suspended, closed |
| `pa_tenant_member` | Account membership in one Tenant | pending, active, suspended, left |
| `pa_department` | Optional tenant organization tree | active, disabled, archived |
| `pa_platform_operator` | Platform governance identity | active, suspended, closed |

An Account may have several TenantMember records, one per tenant. Tenant creation and owner activation are separate actions. A Tenant cannot become active until it has an active owner member.

## Authorization

| Table group | Purpose |
| --- | --- |
| `pa_permission`, `pa_role*`, `pa_platform_role*` | Functional RBAC |
| `pa_protected_resource`, `pa_resource_operation*` | Declared resource operations |
| `pa_target_type` | Typed target registry, not target instances |
| `pa_data_condition_definition`, `pa_data_permission_*` | Role data policies and target sets |
| `pa_tenant_module` | Per-tenant Module enablement and revision |

Role and member assignments never cross tenant composite keys. Platform roles are stored separately from tenant roles.

## Sessions And Audit

Tenant and platform sessions, refresh tokens, and audit events use separate tables and audience checks. Every tenant login challenge and session binds a host-registered Client key. Independent Clients receive independent session families and refresh cookies. Security revisions invalidate stale sessions. Refresh token reuse revokes the affected Client session family.

Tenant audit events always have a target tenant. A platform operator acting on tenant governance records is represented as a platform actor in the tenant audit stream; this does not create a TenantMember.

## Module Runtime

`pa_module_installation` records deployment state. `pa_module_migration` records Module ownership, checksum, batch, and result. `pa_menu_definition` is a manifest projection, not a permission grant.

Kernel migration inventory is checked in integration tests. Existing migration files are not edited after execution; schema corrections are new migrations.
