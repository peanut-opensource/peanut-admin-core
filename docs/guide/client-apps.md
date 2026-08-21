# Client Applications

The `@peanut-admin/admin` package exposes one UI-neutral request and session
state machine through three subpaths:

- `@peanut-admin/admin/client` contains the core client and application
  envelope decoder contract.
- `@peanut-admin/admin/client/nuxt` adapts a structural `$fetch` function.
- `@peanut-admin/admin/client/uniapp` adapts the callback-based request API.

The package does not persist tokens, navigate to a login route, display a
message, or define an application response envelope. The host supplies those
responsibilities through a session, decoder, and hooks.

## Core Client

```ts
import { createClient } from '@peanut-admin/admin/client'

const client = createClient({
  transport,
  session: {
    accessToken: () => tokenStore.accessToken,
    clear: () => tokenStore.clear(),
  },
  decoder: response => {
    if (response.ok) return { kind: 'success', data: response.data }
    if (response.status === 401) return { kind: 'unauthorized', code: 'AUTH_EXPIRED' }
    return { kind: 'business', code: response.code, message: response.message }
  },
  hooks: {
    unauthorized: () => loginRouter.push('/login'),
    businessError: error => toast(error.message),
  },
})

const profile = await client.request<{ name: string }>({
  path: '/api/v1/account',
  method: 'GET',
})
```

Request paths are relative and are validated before the session is read. The
client removes caller-provided `Authorization` headers and adds one Bearer
header only when `auth` is enabled and the session returns a non-empty token.
Set `auth: false` for a public request.

The public header contract accepts plain records, entry tuples, or a structural
`forEach` header source. Core request normalization and URL composition do not
depend on browser `Headers` or `URL` globals, so the same state machine runs in
UniApp mini-program runtimes as well as browsers and Node-based hosts.

The decoder must return exactly one of `success`, `unauthorized`, or `business`.
Success requests resolve to decoded data. Unauthorized responses clear the
session before the unauthorized hook; concurrent unauthorized responses on one
client instance share that clear-and-hook operation. Business failures invoke
the business hook and both failure paths reject with `ClientRequestError`.

## Nuxt

Pass the host's `$fetch` implementation to the adapter. GET and DELETE data is
sent as `query`; all other methods send data as `body`.

```ts
import { createNuxtClientTransport } from '@peanut-admin/admin/client/nuxt'

const transport = createNuxtClientTransport({
  baseUrl: 'https://admin.example',
  $fetch,
})
```

The adapter has no Nuxt, Vue, router, store, or envelope dependency.

## UniApp

Pass the host's callback-based `request` implementation. The adapter maps
headers to `header`, resolves `success.data`, and rejects the original `fail`
value.

```ts
import { createUniAppClientTransport } from '@peanut-admin/admin/client/uniapp'

const transport = createUniAppClientTransport({
  baseUrl: 'https://admin.example',
  request: uni.request,
})
```

Storage, navigation, notifications, token refresh, and business DTOs remain
owned by the application.
