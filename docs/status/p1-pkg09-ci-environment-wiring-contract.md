# P1-PKG09 CI Environment Wiring Contract

## Status

```text
state: accepted
prerequisite_commit: 894a1d0
runtime_change: none
package_publication: none
```

The P1-PKG08 CI run proved the cross-platform license inventory and corrected
documentation build input, then exposed three isolated test-environment wiring
defects. The full-stack Vite child process did not inherit the Playwright port
defaults, integration tests did not receive the explicit local database host
and still contained macOS-only temporary paths and an alternate Compose project
name, and the fixed performance PHP container could not authenticate to MySQL
over the Compose bridge.

## Objective And Non-Goals

Make the existing GitHub qualification runners use their own isolated services
and portable temporary directory. This task does not change Peanut Admin
deployment or downstream database addresses, Runtime behavior, performance
values, package or dependency versions, schemas, APIs, published artifacts, or
release tags. GitHub CI must not access a LAN or production database.

## Exact Write Set

- `scripts/test-browser`;
- `tests/security/SecurityQualificationContractTest.php`;
- `scripts/test-integration`;
- `backend/tests/Upgrade/SettingsUpgradeTest.php`;
- `backend/tests/Upgrade/UpgradeLifecycleTest.php`;
- `backend/tests/Upgrade/UpgradeWorkflowIntegrationTest.php`;
- `scripts/test-performance`;
- `tests/performance/PerformanceQualificationContractTest.php`;
- this contract, `docs/content-status.json`, and `docs/status/index.md`.

No other file may change.

## Environment Contracts

The browser runner supplies validated default backend and frontend ports before
fixture setup and Playwright startup so every child process receives the same
values.

The integration runner exports `DB_HOST=127.0.0.1` for its host-published
Compose MySQL port. Upgrade fixtures use `sys_get_temp_dir()` rather than a
macOS-only path and address the same Compose project started by the integration
runner.

The fixed PHP performance container shares the temporary MySQL container's
network namespace and connects to `127.0.0.1:3306`. This is isolated GitHub
Runner infrastructure; the local PHP path continues to use the caller's host
and configured published port.

## Focused Acceptance And Stop Line

1. Static review proves the exact write set and that no LAN, production, schema,
   Runtime, baseline, version, or dependency setting changed.
2. Bash syntax, PHP lint for changed PHP files, JSON parsing, exact write-set
   review, and `git diff --check` pass once.
3. Only the previously failed `quality`, `qualification`, and `baseline` PR #1
   results are evaluated on the resulting commit. Passed checks are not
   manually rerun or reanalyzed.
4. A failure receives one read-only diagnosis and stops this task; no gate may
   be skipped, weakened, or retried in a loop.
