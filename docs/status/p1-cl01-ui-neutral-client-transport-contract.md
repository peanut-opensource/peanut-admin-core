# P1-CL01 UI-Neutral Client Transport Contract

## Status

```text
state: implemented
prerequisite_commit: 17181ab741ff635fb171b430257be4c2e4922ed7
package: @peanut-admin/admin
public_subpaths: ./client, ./client/nuxt, ./client/uniapp
runtime_operations: none
qualification_status: candidate-only
```

## Objective

Add one UI-neutral client request/session state machine to the existing npm
package so Nuxt PC and UniApp clients do not duplicate bearer injection,
response classification, unauthorized session clearing, error propagation, and
concurrent unauthorized handling.

The package remains one public npm artifact. The three subpaths are internal
entry boundaries, not separate manifests or independently versioned packages.

## Evidence And Ownership Boundary

The Peanut Admin application currently implements the same sequence in
`pc/composables/useRequest.ts` and `uniapp/src/utils/request.ts`: read a token,
attach `Authorization`, decode an application envelope, clear the session on
unauthorized, route to login, notify other errors, and return data. Nuxt uses
`$fetch`; UniApp uses callback-based `uni.request`.

Only the transport/session state machine is reusable. The application continues
to own:

- its response envelope decoder and codes such as `20000` or `40100`;
- persisted token and profile storage;
- login routes, navigation, toast/message presentation, and localized copy;
- user, article, recharge, payment, OAuth, decoration, and other business DTOs;
- page components and platform APIs.

## `./client` Contract

`@peanut-admin/admin/client` exports:

- `createClient(options)`;
- request, transport, session, decoder, hook, result, and error types;
- `ClientRequestError` with stable kind/code and safe message;
- `resolveClientUrl(baseUrl, path)` for adapter URL composition.

A request contains a relative path, method, optional data and headers, and an
`auth` flag. The client must:

1. reject empty, absolute, protocol-relative, backslash, control-character, dot
   segment, and traversal paths before reading a token;
2. copy caller headers and remove every caller-supplied case-insensitive
   `Authorization` value;
3. attach exactly one Bearer header only when auth is enabled and the session
   returns a non-empty token;
4. call the injected transport and application envelope decoder;
5. return only decoded success data;
6. on unauthorized, coalesce concurrent handling, clear the session before the
   unauthorized hook, then throw a stable request error;
7. on business failure, invoke the business-error hook then throw;
8. convert transport and malformed-decoder failures to stable errors without
   copying raw payloads, tokens, or transport exception messages into public
   error text.

Header input is platform-neutral: plain records, entry tuples, and structural
`forEach` sources are accepted and normalized into the package-owned
case-insensitive header contract. Path and base-URL validation must not depend
on browser `Headers` or `URL` globals.

The session exposes only `accessToken()` and `clear()`. It does not persist data
itself. The decoder returns `success`, `unauthorized`, or `business` and is the
only authority for application-specific envelopes.

## Adapter Contracts

`@peanut-admin/admin/client/nuxt` exports `createNuxtClientTransport()`. It
accepts a structural `$fetch`-compatible function and a base URL. GET/DELETE
data becomes `query`; other method data becomes `body`. It has no import from
Nuxt, Vue, Element Plus, Pinia, or Vue Router.

`@peanut-admin/admin/client/uniapp` exports `createUniAppClientTransport()`. It
accepts a structural callback-based `request` function and a base URL, maps
headers to `header`, resolves `success.data`, and rejects the original transport
failure. It has no import from `@dcloudio`, Vue, Pinia, or a UniApp global.

Both adapters compose URLs only through the core path validator. They do not
own session storage, unauthorized navigation, user feedback, envelope parsing,
or business rules. The UniApp adapter and core remain executable when browser
`Headers` and `URL` globals are absent.

## Security And Failure Semantics

- Token access occurs only after path validation.
- Caller headers cannot inject or preserve an Authorization value.
- Public errors expose stable codes and application-provided safe messages, not
  tokens, raw responses, credentials, stack traces, or transport messages.
- Unauthorized clearing precedes navigation and is coalesced per client
  instance so concurrent responses do not trigger duplicate session teardown.
- Hook failures do not cause fallback success and remain chained to the stable
  client failure.
- The package holds no global session, user, Tenant, or platform state.

## Non-Goals

- No Peanut Admin application migration in this task.
- No token refresh protocol, cookie ownership, offline queue, cache, retry,
  upload/download, WebSocket, payment, OAuth, navigation, UI, or DTO package.
- No third npm package, new dependency, separate version, compatibility wrapper,
  old API alias, or application response-code constant.
- No backend, database, API, OpenAPI, generated artifact, Runtime operation, or
  publication change.

## Implementation Task

The implementation may change only:

- `packages/web/client-core/src/index.ts`;
- `packages/web/client-core/tests/client.spec.ts`;
- `packages/web/client-core/tsconfig.json`;
- `packages/web/client-nuxt/src/index.ts`;
- `packages/web/client-nuxt/tests/nuxt.spec.ts`;
- `packages/web/client-nuxt/tsconfig.json`;
- `packages/web/client-uniapp/src/index.ts`;
- `packages/web/client-uniapp/tests/uniapp.spec.ts`;
- `packages/web/client-uniapp/tsconfig.json`;
- `packages/web/package.json` for files, exports, focused test, and typecheck
  registration only;
- `docs/guide/client-apps.md`;
- `docs/status/index.md` for candidate status only;
- `docs/content-status.json` to register the guide;
- this contract only for implementation state.

The implementation must not change dependencies, lockfiles, other public
exports, existing package source, application repositories, generated files,
versions, or publication records.

## Verification Ownership

After static review and exact write-set validation, run each focused group once:

```bash
pnpm --dir packages/web exec vitest run \
  client-core/tests client-nuxt/tests client-uniapp/tests
```

```bash
pnpm --dir packages/web typecheck
```

Behavior tests own path/header security, success, business and unauthorized
flows, concurrent unauthorized coalescing, stable failures, and both adapter
mappings. Typecheck owns all three public subpath contracts. Package pack,
application consumption, browser, aggregate, publication, and registry probes
remain deferred to the next fixed package candidate.

## Stop Line

P1-CL01 is an unqualified post-`alpha.1` candidate. It must not be published,
tagged, path-mapped into Peanut Admin, or represented as PC/UniApp migration
until a later package qualification and explicit downstream decision approve
the exact registry version.
