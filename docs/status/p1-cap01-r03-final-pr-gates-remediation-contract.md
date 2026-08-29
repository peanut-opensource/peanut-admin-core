# P1-CAP01-R03 Final PR Gates Remediation Contract

## Status

```text
task: P1-CAP01-R03
state: accepted remediation
prerequisite_commit: be4f2e8706b4f705c99b1869beceb28ba1647a61
source_candidate_commit: 3972c9aefcd55ac71d07a47739a99d23bb0ae30c
pull_request: peanut-opensource/peanut-admin#7
failed_groups: quality PHPStan; recovery internal Starter HTTP smoke
test_owner: P1-WORKFLOW-RUNTIME-001
runtime_semantics_change: none
schema_change: none
dependency_change: none
package_publication: none
```

## Findings And Sealed Evidence

P1-CAP01-R02 completed the PHP 8.3 aggregate with 622 tests, 3599
assertions, no failure and no risky test. PR run `31517250728`, job
`93865375343`, then stopped at exactly two PHPStan findings in
`WorkflowAtomicityContractHarness`: a redundant `is_callable` check after the
PHPDoc had already narrowed every probe to `callable`, and an unparsed array
value type because the `snapshot` method placed `@param` and `@return` on one
PHPDoc line.

The independent Recovery run `31517250860`, job `93865375381`, completed its
clean-install and package integration work, built the Starter, and then waited
on frontend port `42387`. The script had selected that port by binding to
`127.0.0.1:0` and closing the socket before Vite started. Another process won
the released port, Vite automatically moved to `42388`, and the smoke probe
continued polling the stale number. This is a time-of-check/time-of-use race,
not a Starter product failure.

The separate Performance job reported one tenant-refresh latency spike. The
same exact PR head subsequently completed the full in-process Performance
qualification in Quality, and none of the two R03 files participates in that
path. R03 therefore does not change performance code, thresholds or baseline.
All PHP tests and every other already passing group remain sealed.

## Objective And Non-Goals

Make the Harness types explicit without changing failure injection or snapshot
behavior, and make the frontend preview bind an operating-system-assigned port
atomically while preserving the real HTTP readiness check.

This remediation does not add Workflow behavior, alter schema, change tests,
weaken static analysis, reserve a fixed port, retry a failed test group, change
performance limits, add dependencies, change package versions, publish a
package, move a downstream lock, start CAP02 or nominate a
downstream-consumption candidate.

## Exact Implementation Write Set

After this contract commit, the implementation may change only:

- `packages/php/testing/src/Workflow/WorkflowAtomicityContractHarness.php` —
  remove only the redundant callable guard and express the existing parameter
  and `array<string, mixed>` return types in parseable PHPDoc;
- `scripts/verify-internal-starter` — keep the backend port behavior unchanged;
  start Vite preview with port `0` and strict-port behavior, remove color from
  its log, wait for the process to publish its actual loopback URL, then pass
  that exact URL to the existing HTTP readiness probe.

No test, workflow, configuration, manifest, lockfile, schema, Runtime source or
other script may change. An insufficient whitelist blocks the task and
requires a separate contract correction.

## Verification And Stop Line

Each implementation owner performs static review, exact-write-set inspection
and `git diff --check` only. After both files are integrated, the CAP01 owner
runs one focused PHPStan analysis of the Harness and one shell syntax check.
The pushed exact tree is then subject to the repository-required PR checks;
the Recovery group is the authoritative retry of its failed Starter smoke.

The Performance spike is observed but is not rerun or repaired by R03 because
the same head already passed that qualification inside Quality. A repeated
PHPStan or Recovery failure stops R03 after one read-only diagnosis. Passed
groups are not manually rerun.

CAP01 closes only after PR #7 is merged into `dev`. CAP02 must pin that exact
40-character merge commit. R03 does not qualify CAP01 under CAP05, adopt it
under CAP06, publish Alpha.5 or form a downstream-adoption candidate; that
candidate remains `UNKNOWN` until the minimum consumable Starter Gate exists.
