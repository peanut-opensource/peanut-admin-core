# P1-PKG02-R43 Reference Create Snapshot Test Contract

## Observed Failure

The resumed workspace unit group passed 208 of 209 tests. The remaining
Reference Codes page test expected create, replace, and retire reloads to keep
the same historical `asOf`. R35 intentionally changed create: after the API
returns a validated entry, the Runtime advances to the authoritative
`createdAt` so the new identity is not hidden before its system creation time.
Replace and retire still retain the selected historical snapshot.

The Runtime behavior and the passing real-browser workflow match R35. The
combined unit assertion predates that contract and is stale.

## Authorized Change

R43 may change only:

- this contract;
- `packages/web/reference-codes/tests/page.spec.ts`.

The existing test must assert that the create reload uses the response
`created_at`, while replace and retire reloads use the explicitly selected
historical `asOf`. All three calls must continue preserving effective-status,
retired-record, page, and page-size filters.

R43 must not change Runtime or page source, transport behavior, API, schema,
bitemporal semantics, authorization, production behavior, test fixtures,
browser assertions, or the R35 contract.

After static review and `git diff --check`, rerun the failed `pnpm test:unit`
group once. The earlier lint, typecheck, build, and Docker Compose results apply
to the unchanged production source tree and must not be repeated. If unit tests
pass, run only the remaining fixed license, content, directory, and diff guards
once. A failure receives one read-only diagnosis and stops.
