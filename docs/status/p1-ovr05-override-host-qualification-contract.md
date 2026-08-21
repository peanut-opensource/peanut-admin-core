# P1-OVR05 Override Host Consumption Qualification Contract

## Status

```text
state: qualified
source_candidate_commit: de35258601fabf4da6e737961762c3ba7264b780
scope: P1-OVR01 through P1-OVR04
runtime_operations: none
qualification_status: passed
```

## Objective

Qualify one fixed post-`alpha.1` tree for the standard PHP and Web application
override mechanism. The candidate must prove both fail-closed registries and
their first real ThinkPHP and Vue Host consumption chains without broadening
package boundaries, Runtime behavior, or downstream authority.

This task is the integration owner for P1-OVR01 through P1-OVR04. Historical
focused checks are supporting evidence only; the groups below run once against
the same final tree.

## Fixed Scope

The source candidate is commit
`de35258601fabf4da6e737961762c3ba7264b780`. Qualification covers only:

- the typed Web override registry in `@peanut-admin/admin/core`;
- the PHP service override registry in `peanut-admin/core`;
- the SMS provider ThinkPHP Host binding and factory consumption path;
- the workspace-shell Vue Host resolver consumption path;
- public TypeScript exports required by the reference Host.

No business Runtime operation, database, API, page capability, package manifest,
version, dependency, lockfile, publication projection, or Peanut Admin
application source is in scope.

## Integration Repair

Before running verification, the integration owner may change only
`backend/tests/Smoke/ServiceOverrideHostWiringTest.php` to restore ThinkPHP's
error and exception handlers after every test. This is test isolation only; it
must not weaken assertions, suppress PHPUnit risk detection, change application
code, or add a bypass.

## Verification Groups

### Q1 PHP Registry And Host

Run once with PHP 8.3 and fail on risky tests:

```bash
/opt/homebrew/opt/php@8.3/bin/php vendor/bin/phpunit --fail-on-risky \
  packages/php/kernel/tests/Unit/Override/ServiceOverrideRegistryTest.php \
  backend/tests/Smoke/ServiceOverrideHostWiringTest.php
```

The group must prove default and application resolution, all registry
construction failures, immutable bindings/diagnostics, disabled SMS default,
application SMS binding, invalid Host startup, and factory container
consumption. It must finish without errors, failures, warnings, or risky tests.

### Q2 Web Registry And Host

Run once:

```bash
pnpm exec vitest run \
  packages/web/admin-core/tests/overrides.spec.ts \
  frontend/tests/override-host.spec.ts
```

The group must prove default and application resolution, all Web registry
construction failures, immutable diagnostics, default tenant/platform shells,
application resolver selection, invalid startup value, and invalid delayed
resolver result.

### Q3 Web Public Types

Run once:

```bash
pnpm --filter @peanut-admin/admin \
  --filter @peanut-admin/reference-admin typecheck
```

Both the public npm package and its reference Host must typecheck. The Host must
consume the override API only through `@peanut-admin/admin/core` and
`@peanut-admin/admin/shell` exports.

## Pass Criteria

Qualification passes only when:

1. all three groups pass on the same final tree;
2. `git diff --check` passes;
3. the write set is exactly the test-isolation repair plus qualification
   records listed below;
4. no registry silently falls back after an invalid override;
5. Host code no longer directly selects the migrated SMS or workspace-shell
   implementation;
6. the public boundary remains exactly one Composer and one npm package.

A failed group may be diagnosed once, repaired as one static batch, and rerun
once. Passed groups are never rerun. A second failure blocks qualification.

## Qualification Record Task

The qualification owner may change only:

- `backend/tests/Smoke/ServiceOverrideHostWiringTest.php` for the isolation
  repair above;
- `docs/reviews/p1-override-host-consumption-qualification.md`;
- `docs/status/index.md` for qualification status only;
- `docs/content-status.json` to register the review;
- this contract only for recording final state.

The task must not change runtime source, package manifests, dependencies,
lockfiles, versions, generated artifacts, publication records, application
repositories, or any test assertion outside the one Host test.

## Authority And Stop Line

A passing qualification proves the override mechanism at this fixed candidate.
It does not authorize npm or Composer publication, change the fixed
`0.1.0-alpha.1` publication source, move the existing downstream lock, or permit
the Peanut Admin application to consume an unpublished/path-mapped candidate.

Publication and downstream application migration require a separate explicit
decision fixing the qualified commit, package versions, registry artifacts,
installation method, identity/audience mapping, rollback, and removal of old
application implementations.
