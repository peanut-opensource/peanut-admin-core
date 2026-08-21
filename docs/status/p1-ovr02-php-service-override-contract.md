# P1-OVR02 PHP Service Override Registry Contract

## Status

```text
state: accepted
prerequisite_commit: 2728260
package: peanut-admin/core
runtime_operations: none
qualification_status: candidate-only
```

## Objective

Provide one framework-independent, fail-closed registry in
`PeanutAdmin\Kernel\Override` that selects a package default or an
application-owned implementation for an explicitly declared PHP service
interface. The registry is the PHP half of the standard override protocol; a
later Host-consumption task will bind its resolved interface map into
ThinkPHP's container when the first real application slot is migrated.

## Non-Goals

- No ThinkPHP container dependency in `peanut-admin/core` and no change to
  `AppService`, Runtime factories, controllers, routes, schemas, OpenAPI, or
  generated artifacts.
- No class alias, service locator, automatic namespace scan, Module load-order
  override, configuration compatibility layer, or mutation after startup.
- No application-specific implementation, business rule, API shape, product
  name, deployment secret, or LikeAdmin compatibility behavior.
- No change to the fixed `0.1.0-alpha.1` publication candidate.

## Contract

`ServiceOverrideSlot` declares one package-owned selection point:

- stable lowercase dotted `key` with at least three segments and a `service`
  segment, for example `peanut.auth.service.password-hasher`;
- `contract`, which must be an existing PHP interface;
- exact `contractVersion` in `major.minor.patch` form;
- existing package-owned `defaultImplementation` implementing `contract`.

`ServiceOverride` declares the same key, interface, and exact contract version
plus an application implementation. `ServiceOverrideRegistry` constructs one
immutable resolution set and must:

1. reject invalid or duplicate slot keys;
2. reject duplicate interface contracts across slots;
3. reject missing interfaces or implementations that do not implement them;
4. reject unknown or duplicate application override keys;
5. reject interface or exact contract-version mismatches;
6. resolve every declared slot to one default or application implementation;
7. expose an immutable `contract => implementation` binding map for a Host;
8. expose diagnostics containing only key, interface, contract version, and
   source, never instantiated services or runtime data;
9. fail on an unknown lookup instead of returning null or a default.

Exact version equality is intentional for the initial alpha. The registry does
not instantiate services and does not decide constructor dependencies or
lifecycle scope. Invalid construction throws `OverrideException` with a stable
`PHP_OVERRIDE_*` error code and never silently falls back after an invalid
application override.

## Module And Host Boundary

`ModuleRegistry` remains the only authority for Module manifests, dependency
order, migration ownership, menus, permissions, and Tenant enablement. The
override registry selects implementations only for slots already declared by
the Host and cannot discover or enable a Module.

In a later Host task, `AppService::register()` may register the immutable
registry instance and bind each result from `bindings()` into ThinkPHP. A
consumer then requests its interface from the container. Runtime factories do
not read the registry directly, and no Host wiring is authorized in OVR02.

## Implementation Task

The implementation may change only:

- `packages/php/kernel/src/Override/OverrideException.php`;
- `packages/php/kernel/src/Override/ServiceOverrideSlot.php`;
- `packages/php/kernel/src/Override/ServiceOverride.php`;
- `packages/php/kernel/src/Override/ServiceOverrideResolution.php`;
- `packages/php/kernel/src/Override/ServiceOverrideRegistry.php`;
- `packages/php/kernel/tests/Unit/Override/ServiceOverrideRegistryTest.php`;
- `docs/guide/module-development.md`;
- `docs/status/index.md` for candidate status only;
- this contract only for recording implementation status;
- `docs/content-status.json` only if documentation registration changes.

The implementation must not change Composer manifests, autoload roots,
dependencies, lockfiles, existing service interfaces, Runtime behavior,
Module compilation, Host wiring, package version, publication records, or the
P1-OVR01 Web registry.

## Verification Ownership

The implementation owner performs static review, verifies the exact write set,
runs `git diff --check`, and runs the focused PHP test once with the repository
PHP 8.3 toolchain:

```bash
vendor/bin/phpunit packages/php/kernel/tests/Unit/Override/ServiceOverrideRegistryTest.php
```

The test owns valid default and application resolution, immutable diagnostics
and binding output, and every fail-closed construction or lookup case above.
Aggregate, database, HTTP, browser, build, package-content, publication, and
downstream-consumer checks remain deferred to fixed-candidate qualification.

## Stop Line

OVR02 is an unqualified post-`alpha.1` candidate. It must not be merged into
the fixed publication branch, published, tagged, or represented as application
consumption until a later Host task, fixed-tree qualification, and explicit
downstream decision are complete.
