# P1 Override Host Consumption Qualification

## Decision

```text
state: qualified
source_candidate_commit: de35258601fabf4da6e737961762c3ba7264b780
qualification_contract: P1-OVR05
remediation_contract: P1-OVR05-R01
scope: P1-OVR01 through P1-OVR04
publication_authorized: false
downstream_consumption_authorized: false
```

The combined PHP and Web override mechanism is qualified as a fixed
post-`alpha.1` candidate. Both registries fail closed, and both reference Hosts
consume one real package-owned service slot without selecting the migrated
implementation directly.

This record qualifies the commit that contains it. It does not change the
historical `0.1.0-alpha.1` publication source, publish a package, move the
downstream lock, or authorize Peanut Admin application migration.

## Qualified Behavior

### PHP

- `ServiceOverrideRegistry` validates stable service keys, exact versions,
  interfaces, implementations, duplicates, unknown overrides, and unknown
  resolution lookups.
- The registry exposes immutable binding and redacted diagnostic copies.
- ThinkPHP registers one registry instance and binds its resolved interface map
  during Host startup.
- `SmsProvider` defaults to `DisabledSmsProvider`; an application may provide a
  matching implementation through `backend/config/service-overrides.php`.
- Invalid application configuration fails startup. The notification worker
  requests `SmsProvider` from the container and has no provider-name switch or
  implicit LocalDev fallback.

### Web

- `AdminOverrideRegistry` validates stable keys, kinds, exact versions,
  validators, duplicates, unknown overrides, and unknown resolution lookups.
- Resolution values and diagnostic metadata are immutable; diagnostics omit
  selected values and runtime data.
- The Admin Shell package owns one workspace-component resolver slot with
  tenant and platform defaults.
- The reference Host constructs one registry from package slots and the
  application override list. `WorkspaceLayout` consumes only
  `runtime.workspaceShell(audience)`.
- Invalid resolver values fail startup, and invalid delayed component results
  fail without falling back to package defaults.

## Verification Evidence

### Q1 PHP Registry And Host

Command:

```bash
/opt/homebrew/opt/php@8.3/bin/php vendor/bin/phpunit --fail-on-risky \
  packages/php/kernel/tests/Unit/Override/ServiceOverrideRegistryTest.php \
  backend/tests/Smoke/ServiceOverrideHostWiringTest.php
```

Result: passed on PHP 8.3.24, 20 tests and 30 assertions, with no errors,
failures, warnings, or risky tests. The integration repair restores only the
ThinkPHP handlers that differ from the test's initial handlers.

### Q2 Web Registry And Host

Command:

```bash
pnpm exec vitest run \
  packages/web/admin-core/tests/overrides.spec.ts \
  frontend/tests/override-host.spec.ts
```

Result: passed, 2 files and 10 tests.

### Q3 Web Public Types

Command:

```bash
pnpm --filter @peanut-admin/admin \
  --filter @peanut-admin/reference-admin typecheck
```

The first run found one TypeScript control-flow narrowing error after
`Map.get()`. P1-OVR05-R01 authorized the exact nullish fail-closed expression
repair. The single permitted rerun passed for both `@peanut-admin/admin` and
`@peanut-admin/reference-admin`. Q1 and Q2 were not repeated.

## Boundary Review

- Public package count remains exactly two: Composer `peanut-admin/core` and
  npm `@peanut-admin/admin`.
- No manifest, dependency, lockfile, version, Runtime operation, API, OpenAPI,
  generated artifact, database, business page, or application repository was
  changed by qualification.
- PHP core remains framework-independent; ThinkPHP wiring stays in the Host.
- The npm registry remains generic; the Shell package owns its real slot and
  the Host owns only override declarations.
- No Vite alias protocol, path-copy consumption, compatibility selector, dual
  provider authority, silent fallback, or dynamic remote code was introduced.

## Remaining Gates

Before Peanut Admin may consume this candidate:

1. fix an immutable new package candidate version and projection digests;
2. verify npm scope and Packagist publication ownership and credentials;
3. publish both packages through the approved registry workflow;
4. pass isolated registry-consumer probes for both artifacts;
5. make a separate downstream decision fixing the published versions and
   Peanut Admin's platform/tenant identity mapping;
6. migrate one complete auth/permission/Shell path and remove the old
   application implementation without compatibility layers.
