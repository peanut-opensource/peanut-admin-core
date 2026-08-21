# P1-OVR01 Admin Web Override Registry Contract

## Status

```text
state: accepted
prerequisite_commit: aed1145ee02053c7bc391e47491c4344cd4345c8
package: @peanut-admin/admin
runtime_operations: none
qualification_status: candidate-only
```

## Objective

Provide one typed, fail-closed override registry in `@peanut-admin/admin/core`
so an application can replace declared reusable services, components, pages,
or route loaders without editing package source, `node_modules`, or Vite
aliases. This is the Web half of the application override protocol; the PHP
registry is a separate task because it has a different type and container
boundary.

The registry resolves a complete set of package-owned default slots plus an
optional set of application-owned replacements. A replacement is valid only
when its stable key, kind, contract version, and optional runtime validator all
match the package declaration.

## Non-Goals

- No application-specific route, page, brand, menu, API path, or LikeAdmin
  compatibility behavior.
- No second npm package, plugin marketplace, dynamic remote code, deep import,
  Vite alias protocol, or mutation after startup.
- No change to authentication, authorization, Tenant isolation, Runtime
  operations, OpenAPI, generated clients, package version, or the qualified
  `0.1.0-alpha.1` publication candidate.
- No implicit override by import order and no fallback for an invalid override.

## Contract

An override slot declares:

- `key`: lowercase dotted identifier with at least three segments, including
  the owner and kind, for example `peanut.shell.component.header`;
- `kind`: one of `service`, `component`, `page`, or `route`;
- `contractVersion`: exact `major.minor.patch` contract version;
- `defaultValue`: package-owned implementation;
- optional `validate(value)`: runtime predicate for boundaries that cannot be
  proven structurally after TypeScript erasure.

An application override declares the same `key`, `kind`, and
`contractVersion`, plus its replacement `value`. Registry construction must:

1. reject an invalid or duplicate slot key;
2. reject an unknown or duplicate override key;
3. reject kind or exact contract-version mismatches;
4. reject a default or replacement rejected by its slot validator;
5. resolve every declared slot to exactly one immutable value;
6. expose whether a resolved value came from `default` or `application`;
7. return a copy of diagnostics metadata without exposing a mutable registry.

Exact contract-version matching is intentional for the initial alpha. SemVer
range negotiation is not inferred and may be added only by a later contract.
Invalid construction throws a stable error code prefixed with
`ADMIN_OVERRIDE_`; it never silently falls back to the package default.

## Security And Ownership

Overrides are trusted build-time application code. They do not bypass route
guards, permission checks, Module availability, audience separation, or data
authorization. The registry owns selection only; the selected implementation
must still receive and enforce the same trusted runtime context.

Package modules own slot declarations and stable keys. The application owns
only the override values supplied at startup. Runtime API responses, Tenant
data, tokens, secrets, and deployment credentials must never be placed in
registry diagnostics.

## Implementation Task

The implementation may change only:

- `packages/web/admin-core/src/runtime/overrides.ts`;
- `packages/web/admin-core/src/index.ts`;
- `packages/web/admin-core/tests/overrides.spec.ts`;
- `docs/guide/admin-web.md`;
- `docs/status/index.md` for candidate status only;
- this contract only for recording the implementation commit;
- `docs/content-status.json` only if documentation registration changes.

The implementation must not change package manifests, lockfiles, exports
outside `@peanut-admin/admin/core`, existing Module contribution behavior,
frontend Host wiring, PHP source, schemas, OpenAPI, generated artifacts, or
publication records.

## Verification Ownership

The implementation owner performs static review, verifies the exact write set,
runs `git diff --check`, and runs the focused Web test once:

```bash
pnpm --dir packages/web exec vitest run admin-core/tests/overrides.spec.ts
```

The test must cover valid default and application resolution plus every
fail-closed construction case listed above. Repository aggregate, browser,
build, package-content, publication, and downstream-consumer checks remain
deferred to a later fixed-candidate qualification.

## Stop Line

The implementation is an unqualified post-`alpha.1` candidate. It does not
alter or invalidate the fixed `alpha.1` source commit and must not be merged
into its publication branch, published, tagged, or consumed by the application
until a later fixed-tree qualification and explicit downstream decision.
