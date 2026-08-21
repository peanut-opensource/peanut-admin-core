# P1-W01 Protected Transport Origin Contract

## Status And Objective

```text
task: P1-W01
state: implementation-ready
prerequisite: d2e3f843633905105730a24a688bc52d005f1880
migration: none
dependency_change: none
qualification: candidate only
```

P1-W01 prevents a protected Web transport from attaching an access token or
credentialed request policy to an origin outside the API authority configured
by its host.

The existing audience path and Client-scoped refresh rules remain
authoritative. Origin authorization is an additional boundary, not a
replacement for path, audience, permission, or server-side authorization.

## Non-Goals

This task does not:

- create a general URL allowlist, proxy, gateway, or redirect service;
- validate server certificates, DNS, CORS response headers, or deployment
  topology;
- change token storage, cookie names, refresh rotation, or API path ownership;
- add a dependency or an application-specific Client;
- qualify a release, package publication, production deployment, or new
  downstream lock.

## Configuration Contract

`createProtectedFetch()` derives one exact allowed origin in this order:

1. `allowedOrigin`, when explicitly provided;
2. the origin resolved from `baseUrl`;
3. `globalThis.location.origin` for an explicitly same-origin browser host.

At least one source must resolve to an absolute `http:` or `https:` URL.
Configuration fails synchronously with `API_ORIGIN_INVALID` when:

- no source can resolve an origin;
- the configured URL uses another scheme or has an opaque origin;
- the configured URL contains a username or password;
- `allowedOrigin` contains a path other than `/`, query, or fragment.

Origin comparison uses the URL Standard's normalized `origin` value. Scheme,
hostname, and effective port must all match. Hostname comparison follows URL
normalization; path case remains governed by the existing path predicate.
`http://example.test` and `https://example.test` are different origins, as
are explicit non-default ports.

`createTenantApiClient()` and `createPlatformApiClient()` pass their
`baseUrl` into this contract. An external host using `createProtectedFetch()`
may pass either `allowedOrigin` or `baseUrl`; its API prefix remains defined
by `isAllowedPath`.

## Request Contract

`createProtectedFetch()` continues to accept a constructed `Request`.
`Request.url` is absolute according to the Fetch API. Before reading the
access token, creating security headers, coordinating refresh, or invoking
`fetch`, the transport performs:

1. configuration validation;
2. exact origin comparison;
3. the existing audience/path predicate.

A mismatched origin throws `API_ORIGIN_MISMATCH`. The error may include the
rejected origin but must not include the access token, cookie, query values,
fragment, request body, or configured credentials. The underlying `fetch`,
`getAccessToken`, `setAccessToken`, and `refresh` callbacks are not called.

A matching origin with a denied path continues to throw
`API_AUDIENCE_MISMATCH` before token access or `fetch`.

Malformed URLs are rejected as `API_ORIGIN_INVALID`; native parser details are
not exposed as an application Problem Details response.

## Header, Cookie, And Redirect Contract

Only after origin and path checks pass may the transport:

- read the in-memory access token;
- set `Authorization: Bearer <token>`;
- attach or preserve `X-Request-Id`;
- set `credentials: include`.

Protected requests use `redirect: manual`. The transport returns the first
redirect response and never automatically forwards a bearer token or
credentialed request to a redirect target. Redirect interpretation belongs to
the host or server protocol and cannot bypass the origin check.

Credential-exchange routes remain subject to the same origin and path checks.
Their current special behavior is retained: a 401 from login, refresh, or
Tenant selection does not recursively start another refresh.

## Refresh And Replay Contract

Refresh remains scoped by the host-provided stable Client key. P1-W01 does not
coordinate different refresh scopes.

After a same-origin protected request returns 401:

- the existing refresh coordinator may perform one rotation;
- the retry is allowed only for GET, HEAD, OPTIONS, or a request carrying an
  `Idempotency-Key`;
- the cloned retry source is checked against the same fixed allowed origin and
  allowed-path predicate before the refreshed token is attached;
- a caller cannot mutate `baseUrl`, `allowedOrigin`, or the retry target
  between attempts.

Cross-origin denial never triggers refresh and is never replayed.

## Error And API Boundary

P1-W01 uses stable client-side errors:

| Code | Meaning |
| --- | --- |
| `API_ORIGIN_INVALID` | The protected transport has no valid HTTP(S) origin configuration, or a request URL cannot be validated. |
| `API_ORIGIN_MISMATCH` | The request origin differs from the configured API authority. |
| `API_AUDIENCE_MISMATCH` | Existing error: the request path does not belong to the configured audience or Client prefix. |

These errors occur before an HTTP exchange and therefore are not RFC 9457
Problem Details responses. Reference and external hosts may map them to a local
safe error state, but must not retry them automatically.

## Schema, Persistence, Permission, And Audit

- No database schema or migration changes.
- No OpenAPI operation or generated API type changes.
- No functional permission or data-policy changes.
- No server audit row is created because no denied request reaches the server.
- A host may record a redacted local diagnostic containing only the stable code,
  configured origin, rejected origin, and request ID. Peanut Admin does not add
  a local logging dependency in this task.

## Concurrency And Idempotency

The allowed origin is immutable within one protected transport instance.
Concurrent requests use the same normalized origin and existing refresh
coordinator.

Origin rejection has no side effect and needs no idempotency record. Existing
non-idempotent replay rules remain unchanged and must be regression-tested.

## Implementation Whitelist

Runtime implementation may modify only:

- `packages/web/admin-core/src/api/client.ts`;
- `packages/web/admin-core/tests/api-client.spec.ts`;
- `starter/frontend/verification/clients.spec.ts`;
- `docs/guide/admin-web.md`;
- `docs/examples/verification.json` only if an executable documentation marker
  is required;
- `README.md` and `docs/status/index.md` only for candidate status.

`starter/frontend/src/clients.ts` may change only if the implementation needs
to pass an explicit `allowedOrigin` that cannot be derived from its existing
`baseUrl`. No other starter or reference-host source is in scope.

Forbidden changes include:

- dependency manifests and lock files;
- PHP packages, backend, schema, migrations, OpenAPI, and Runtime coverage;
- auth store, refresh coordinator, cookie, session, or Client registry
  semantics;
- Module manifests, routes, menus, business pages, or application examples;
- public generator, template, package publication, or release files.

## Test Ownership And Test-First Cases

Test owner: `P1-WEB-TRANSPORT-ORIGIN-001`.

The first Runtime edit adds failing tests for:

1. same-origin absolute request succeeds and carries the token;
2. cross-scheme, cross-host, and cross-port requests fail before token access
   and `fetch`;
3. explicit default ports normalize correctly;
4. a denied API prefix fails before token access and `fetch`;
5. invalid or credential-bearing origin configuration fails synchronously;
6. same-origin 3xx is returned without automatic redirect forwarding;
7. same-origin 401 refresh and replay remain functional;
8. cross-origin denial never refreshes or replays;
9. non-idempotent request without `Idempotency-Key` is not replayed;
10. the fixed external starter rejects a different origin without invoking its
    transport callback.

The focused gate is:

```bash
pnpm exec vitest run packages/web/admin-core/tests/api-client.spec.ts
```

The starter-owned case runs inside the generated standalone workspace through
`./scripts/verify-internal-starter`; `starter/frontend` is intentionally not a
member of the repository root pnpm workspace.

Then run:

```bash
pnpm lint
pnpm typecheck
pnpm test:unit
pnpm build
./scripts/verify-internal-starter
./scripts/check
git diff --check
```

Docker-backed aggregate checks must use a worktree-specific Compose project,
database/cache ports, database names, and browser ports. `MYSQL_PORT` and
`DB_PORT` must carry the same isolated MySQL port.

## Acceptance

P1-W01 is complete only when:

- all test-first cases pass without weakening existing audience or refresh
  assertions;
- no rejected request invokes token, refresh, or fetch callbacks;
- the fixed starter proves the behavior through the public package root;
- docs, architecture, dependency, supply-chain, unit, browser, recovery,
  performance, starter, and workspace checks pass;
- the task is one independently reviewable implementation commit with a clean
  worktree.

## Qualification Stop Line

The planning and implementation commits are unqualified P1 candidates. They do
not move the current downstream lock, publish packages, create a tag or release,
approve production, or authorize any application-specific Runtime. P1-W01 is
eligible for downstream consumption only as part of the later P1-Q01
fixed-commit qualification and separate approval.
