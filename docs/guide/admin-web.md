# Admin Web Composition

The reference Admin Web is one Vue 3 build with strictly separated tenant and
platform workspaces. Reusable behavior comes from
`@peanut-admin/admin/core` and `@peanut-admin/admin/shell`; final routing,
pages, branding, and Module assembly live in `frontend`.

## Audience Clients

Create tenant and platform clients separately. Each client rejects the other API prefix, stores access tokens only in memory, attaches request IDs, includes the matching refresh cookie, and coordinates a single refresh rotation for concurrent 401 responses.

The configured `baseUrl` also fixes the only HTTP(S) origin that may receive a
protected request. Scheme, host, and effective port must match before the
transport reads the in-memory token or invokes `fetch`; invalid configuration
fails with `API_ORIGIN_INVALID`, while a different request origin fails with
`API_ORIGIN_MISMATCH`. Protected requests use manual redirect handling so a
browser cannot automatically forward their bearer token or refresh cookie to a
redirect target. Audience-path rejection remains a separate
`API_AUDIENCE_MISMATCH` boundary.

Every Tenant Client supplies a stable refresh scope such as `single-store-web:tenant`. The default browser coordinator combines Web Locks and `BroadcastChannel` so independent tabs of the same Client can consume one rotated access token without reusing the old refresh token. Different Client keys use different scopes and never coordinate or exchange tokens.

An application with its own OpenAPI schema uses the exported `createProtectedFetch()` with its own `baseUrl` or exact `allowedOrigin`, allowed-path predicate, credential-exchange predicate, and generated `openapi-fetch` Client. It does not need to reuse Peanut Admin's generated `paths` type or duplicate the authentication replay logic. Tests and non-browser hosts can inject `createMemoryRefreshCoordinator()`.

Non-idempotent requests are replayed only when they carry an `Idempotency-Key`.

## Module Contribution

```ts
import { defineAdminModule } from '@peanut-admin/admin/core'

export default defineAdminModule({
  key: 'example.work-item',
  routes: [{
    name: 'example.work-item.list',
    path: '/app/example-work-items',
    component: () => import('./WorkItemListPage.vue'),
    access: {
      moduleKey: 'example.work-item',
      permissionKeys: ['example.work-item.read'],
    },
  }],
  disposeOnTenantChange: true,
})
```

Remote component paths, `eval`, and runtime Plugin JavaScript are not supported in P0.

## Application Overrides

Reusable Web implementations can declare typed build-time override slots through
`@peanut-admin/admin/core`. A slot key is a lowercase dotted identifier that
includes its owner and kind, and its contract version is matched exactly:

```ts
import type { AdminOverride } from '@peanut-admin/admin/core'
import { WORKSPACE_SHELL_OVERRIDE_KEY } from '@peanut-admin/admin/shell'

export const ADMIN_HOST_OVERRIDES: readonly AdminOverride[] = [{
  key: WORKSPACE_SHELL_OVERRIDE_KEY,
  kind: 'service',
  contractVersion: '1.0.0',
  value: audience => audience === 'tenant' ? ApplicationTenantShell : ApplicationPlatformShell,
}]
```

The reference Host constructs one registry from package-owned slot declarations
and the application list. `WorkspaceLayout` calls the selected resolver; it does
not import the default shells or fall back after an invalid application result.

Registry construction fails with an `ADMIN_OVERRIDE_` error for an invalid or
duplicate slot, an unknown or duplicate replacement, a kind or exact-version
mismatch, or a value rejected by its slot validator. There is no fallback for
an invalid replacement. `diagnostics()` returns immutable key, kind, version,
and source metadata only; values, Tenant data, credentials, and API responses
must remain outside diagnostics.

## Zero, One, And Many Targets

The operation target store is keyed by Module, protected resource, operation, target type, and cardinality.

- Zero candidates: disable the operation and show an empty scope state.
- One candidate: select it automatically for `one_required` operations.
- Several candidates: require one explicit selection for ordinary writes.
- Several readable candidates: allow a narrowed multi-selection and show an ownership column.
- Aggregate reads: show a scope summary and remain read-only.

The selection is only request input. The backend resolver and provider remain authoritative.

## Tenant Switch

Tenant switch disposes Module stores, menus, target selections, pending requests, and cached collections before the new context renders. Responses from an older tenant generation are ignored. Shell preferences may persist only when they contain no tenant or business data.

## Explicit Error States

The Shell provides dedicated states for forbidden access, missing resources, stale ETags, rate limiting, Module unavailability, service unavailability, and session expiry. It does not convert a denied or unavailable operation into an empty successful page.
