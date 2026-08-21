# Authentication And Trusted Context

P0 implements one global email-password credential per login identifier. Authentication resolves an `Account`; authorization happens only after selecting the correct audience and membership.

## Tenant Flow

```text
registered Tenant Client
-> Credential
-> Account
-> active Tenant choice
-> active TenantMember
-> TenantSession
-> TenantContext
```

An Account may have memberships in several tenants. A login that resolves several active memberships returns tenant choices instead of guessing. Tenant selection creates a new tenant-bound session. A request-body or query-string `tenant_id` never establishes the tenant context.

Each deployed Tenant Client is registered by the host and binds its own `TenantAuthService`. The Client key is not accepted as a browser-selected privilege switch. Login challenges, tenant selection, sessions, access tokens, refresh tokens, menu filtering, and refresh cookies all retain that server-selected Client key. A token issued to one Client is rejected by another Client.

Tenant switching uses a short-lived challenge and creates a new session. The Admin Web clears tenant stores and rejects late responses from the previous tenant generation.

## Platform Flow

Platform operators use a separate `PlatformOperator`, `PlatformSession`, refresh cookie, API prefix, guard, context, roles, and audit stream. Platform authority can manage tenant lifecycle and TenantModule state, but it does not imply access to tenant business records.

Tenant Client cookies include the registered Client key, while the platform audience remains separate:

```text
__Host-pa_tenant_refresh_<client-key>
__Host-pa_platform_refresh
```

All are `Secure`, `HttpOnly`, `SameSite=Lax`, and use `Path=/`. Access tokens stay in memory. Two independent Tenant Clients use separate session families and cookies, so one Client's token rotation does not revoke the other Client. A future single-sign-on flow may exchange a short-lived one-time authorization code, but Clients never share a local Session ID, access token, or refresh cookie.

## Session Validation

A Session is the server-side authentication record represented to the browser by tokens; it is not a short identifier stored as application authority in local storage. A validated tenant session binds Account, Tenant, TenantMember, registered Client key, security revisions, expiration, and audience. Suspending or closing an Account, Tenant, or membership invalidates future validation. Refresh tokens rotate; reuse revokes only that Client's session family and records a security event.

The trusted HTTP context contains identifiers derived by the server plus a request ID. It does not carry a mutable global current business target. Typed targets belong to each operation.

## Async Work

Asynchronous handlers accept only a signed trusted envelope and revalidate authorization at execution time. A queued action cannot reuse stale browser authority, silently change audience, or infer a tenant from payload data.
