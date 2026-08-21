# P0 Security Baseline

This page summarizes implemented security contracts. Final ASVS mapping and release qualification are produced by the P0 total gate.

## Isolation

- Tenant context comes only from a validated tenant session.
- Platform and tenant audiences use separate sessions, cookies, clients, API prefixes, guards, roles, and audit streams.
- Tenant tables require non-null tenant identifiers and tenant-local composite constraints where relationships cross tables.
- Missing Module, TenantModule, permission, operation, resolver, or provider state fails closed.
- Platform authority does not bypass tenant business authorization.

## Credentials And Sessions

- P0 accepts email-password credentials only.
- Passwords use the Kernel password hasher and never appear in output or audit metadata.
- Refresh tokens are hashed at rest, rotate on use, and detect reuse.
- Access tokens stay in browser memory; refresh cookies are Secure and HttpOnly.
- Tenant switch creates a new trusted session and disposes old tenant state.

## Authorization

- Functional permission and data authorization are separate decisions.
- List, detail, create, update, delete, aggregate, and async paths require parity.
- Typed target categories cannot be confused or silently converted.
- Shared-master visibility and usage require a registered scope provider.
- P0 has no super-user flag, `tenant_id = 0`, silent fallback, or production test bypass.

## API And Operations

- BIGINT identifiers are decimal strings at API boundaries.
- Problem Details uses stable codes without stack traces or secrets.
- Sensitive writes use idempotency and optimistic concurrency where declared.
- Installation reads passwords from environment variables and emits identifiers only.
- Migration checksum drift stops the upgrade.

Run `./scripts/test-security` and `./scripts/check` before review. Passing these checks is necessary but not sufficient for a public production release.
