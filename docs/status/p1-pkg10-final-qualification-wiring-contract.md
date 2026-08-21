# P1-PKG10 Final Qualification Wiring Contract

## Status

```text
state: accepted
prerequisite_commit: dec5ffd
runtime_change: none
package_publication: none
```

P1-PKG09 reduced the quality result from twenty integration failures to one,
started the full-stack browser servers successfully, and left the performance
readiness failure isolated. The resulting logs show three remaining runner
defects: the Security job does not install its Chromium runtime, one upgrade
test assumes a linear `HEAD^` although pull-request checkout may be a synthetic
merge commit, and the performance readiness loop discards the only useful
Docker or PDO error.

## Objective And Non-Goals

Finish PR #1 qualification without weakening a gate. Install the already
locked Playwright browser in Security, make the empty-database upgrade test
independent of Git parent topology, and use a deterministic MySQL health and
fixed-PHP connection path that reports a redacted direct failure. This task
does not change deployment or LAN database configuration, Runtime behavior,
performance values, package or dependency versions, schemas, APIs, published
artifacts, or release tags.

## Exact Write Set

- `.github/workflows/security.yml`;
- `.github/workflows/ci.yml` only to supply the same fixed performance PHP
  image already required by the dedicated Performance workflow;
- `backend/tests/Upgrade/UpgradeWorkflowIntegrationTest.php`;
- `scripts/test-performance`;
- `scripts/check-workspace` and `scripts/verify-internal-starter` only to use
  the system `grep` available in qualification environments for existing
  workspace, private-data, and product-language assertions;
- `tests/performance/PerformanceQualificationContractTest.php`;
- `.github/workflows/performance.yml` and `docker/php/Dockerfile` only if the
  fixed PHP image or Runner-local MySQL wiring requires a static correction;
- `docs/decisions/dependencies/p1-pkg03-lock-evidence.json` only to refresh the
  root and starter pnpm lock SHA-256 values after the already-reviewed
  cross-platform `supportedArchitectures` metadata change, without changing
  any dependency version or resolution;
- this contract, `docs/content-status.json`, and `docs/status/index.md`.

No other file may change.

## Qualification Contracts

Security installs Chromium through the same pinned Playwright dependency and
command already used by the CI quality job.

The wrong-source-database test uses the candidate parent for a linear checkout
and the candidate-branch parent for a synthetic pull-request merge checkout.
The source and target identities remain distinct, their migration inventories
remain append-only, and the only intentional mismatch is the empty database.

Performance waits for the Compose MySQL health contract, then runs the fixed
PHP image once against the Runner-local temporary service. A failure exposes
only the direct Docker or PDO reason and never logs credentials. Local PHP
continues to use the caller's host and published port. The aggregate Quality
job builds and supplies that same image only when its repository gate reaches
the performance step.

## Focused Acceptance And Completion

1. Static review proves the exact write set and unchanged deployment, LAN,
   Runtime, baseline, version, dependency, and schema contracts.
2. Bash syntax, PHP lint, workflow YAML parsing, JSON parsing, exact write-set
   review, and `git diff --check` pass once.
3. The resulting PR commit is evaluated by GitHub Actions. Each newly exposed
   failure receives one direct read-only diagnosis and one minimal correction;
   the user has explicitly authorized continuing until all required checks
   pass.
4. After every required PR check succeeds, PR #1 is merged into `dev` and the
   final result is recorded without republishing Alpha.3 or moving npm
   `latest`.
