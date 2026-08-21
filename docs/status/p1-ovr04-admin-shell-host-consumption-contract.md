# P1-OVR04 Admin Shell Host Consumption Contract

## Status

```text
state: implemented
prerequisite_commit: a4e1ca9
package: @peanut-admin/admin
runtime_operations: none
qualification_status: candidate-only
```

## Objective

Prove the first real Web application override chain by moving reference Admin
workspace-shell selection to the accepted `AdminOverrideRegistry`. The Admin
Shell package owns one stable resolver slot, the reference Web Host constructs
the registry once during runtime creation, and `WorkspaceLayout` requests its
tenant or platform shell through the resolved service instead of importing and
choosing `AdminShell` or `PlatformShell` directly.

This task makes P1-OVR01 an exercised Host boundary. It does not migrate the
Peanut Admin application, publish the npm package, or authorize downstream
consumption.

## Service Slot

The package-owned slot is fixed as:

```text
key: peanut.shell.service.workspace-component
kind: service
contract_version: 1.0.0
value: WorkspaceShellResolver
```

`WorkspaceShellResolver` accepts only `tenant` or `platform` and returns a Vue
component. The package default returns `AdminShell` for tenant and
`PlatformShell` for platform. The slot validator accepts only callable resolver
values. A package-owned resolution helper validates the returned value and
fails with `ADMIN_SHELL_OVERRIDE_RESULT_INVALID` when a selected resolver does
not return a Vue component.

## Host Configuration And Wiring

The application owns `frontend/src/app/overrides.ts`. It exports the immutable
list of `AdminOverride` declarations supplied at build time; the reference Host
uses an empty list by default.

`createAdminRuntime()` must:

1. combine the package-owned shell slot list with the application-owned
   override list;
2. construct one immutable `AdminOverrideRegistry` during startup;
3. expose registry diagnostics and a `workspaceShell(audience)` method on the
   Host runtime;
4. fail runtime construction for an invalid application override.

`WorkspaceLayout.vue` must call `runtime.workspaceShell(audience)` and must not
import, select, or fall back to the package shell components directly.
Tests may inject an explicit override list through runtime dependencies; normal
Host startup reads only the application-owned override module.

There is no second registry, Vite alias, import-order override, component-name
switch, or invalid-override fallback.

## Security And Failure Semantics

- Override declarations are trusted build-time application code and are
  validated before protected navigation renders.
- Selection does not bypass audience guards, authentication, permissions,
  Module availability, Tenant lifecycle disposal, navigation registration, or
  route ownership.
- Diagnostics contain only key, kind, contract version, and source; they do not
  contain components, tokens, Tenant data, menu payloads, or API responses.
- A resolver result is validated on every resolution so a delayed invalid
  component cannot silently fall back to the package default.

## Non-Goals

- No Peanut Admin application migration, login-page change, auth/audience
  decision, route or API compatibility adapter, or Arco/Element Plus coexistence
  work.
- No business page, Module contribution, menu, permission, backend, OpenAPI,
  generated artifact, schema, database, or Runtime operation change.
- No new npm package, package manifest, dependency, lockfile, client subpath,
  publication, tag, release, or downstream-consumption approval.
- No fixed-tree qualification or change to the `0.1.0-alpha.1` publication
  candidate.

## Implementation Task

The implementation may change only:

- `packages/web/admin-shell/src/overrides.ts`;
- `packages/web/admin-shell/src/index.ts`;
- `frontend/src/app/overrides.ts`;
- `frontend/src/app/runtime.ts`;
- `frontend/src/shell/WorkspaceLayout.vue`;
- `frontend/tests/override-host.spec.ts`;
- `docs/guide/admin-web.md`;
- `docs/status/index.md` for candidate status only;
- this contract only for recording implementation state.

The implementation must not change package manifests, dependencies, lockfiles,
existing registry semantics, generated artifacts, other Host routes or pages,
backend source, publication records, package version, or P1-OVR01/P1-OVR02/
P1-OVR03 source.

## Verification Ownership

The implementation owner performs static review, verifies the exact write set,
runs `git diff --check`, and runs the focused Web Host test once:

```bash
pnpm --dir frontend exec vitest run tests/override-host.spec.ts
```

The test owns default tenant/platform resolution, explicit application override
resolution, immutable source diagnostics, invalid override construction, and
invalid resolver-result failure. Package-wide unit, typecheck, build, browser,
aggregate, publication, and downstream-consumer checks remain deferred to a
later fixed-candidate qualification.

## Stop Line

OVR04 is an unqualified post-`alpha.1` candidate. It must not be merged into the
fixed publication branch, published, tagged, represented as Peanut Admin
application consumption, or used to move the downstream lock until a later
fixed-tree qualification and explicit downstream decision approve it.
