# P1-PKG11 Tenant Refresh Sampling Follow-up Contract

## Status

```text
state: accepted
prerequisite_commit: 7fbd445d8fa547830b7782a7ac147d9ed414e0fd
runtime_change: none
package_publication: none
```

PR #5 changed documentation only. Independent Performance and Quality runs
against the same source commit, `a511ecb`, reported tenant-refresh p95 values of
`7.899 ms` and `264.661 ms`, respectively. The existing benchmark uses 18
samples. With the current nearest-rank calculation, `ceil(18 * 0.95)` selects
the eighteenth observation, so p95 is the maximum observation for that run.
The two measurements do not provide evidence of a Runtime regression.

## Objective And Non-Goals

Make the tenant-refresh p95 observation less sensitive to one extreme sample
by fixing a 20-sample follow-up that reuses the 20 login tokens already created
by the benchmark. Preserve the existing nearest-rank percentile calculation,
the p95 threshold and baseline value, the Runtime, and CI behavior. Keep p99 in
the benchmark output as diagnostic evidence.

This contract does not authorize a Runtime change, percentile or threshold
change, database or fixture change, new login-token generation, CI change,
package or release action, or any change to another performance scenario. It
does not authorize automatic tests during implementation.

## Exact Implementation Write Set

After this contract is committed, the follow-up implementation may change only:

- `tests/performance/run.php` — change the tenant-refresh benchmark from 18 to
  20 samples and consume the existing 20 login tokens exactly once; do not
  change the percentile function, p95 threshold, Runtime calls, fixture, or
  CI path;
- `tests/performance/PerformanceQualificationContractTest.php` — add a static
  contract assertion that locks the tenant-refresh sample count to 20 and
  preserves the existing benchmark contract;
- `docs/performance/p0-baseline.md` — change only the tenant-refresh row from
  18 to 20 one-time rotations.

No other file may change. In particular, `tests/performance/p0-baseline.json`,
`scripts/test-performance`, CI workflows, Runtime source, database schema,
dependencies, package metadata, and release records remain unchanged.

## Sampling And Reporting Contract

The benchmark must retain the existing 20 login operations that populate
`$loginTokens`; tenant-refresh consumes those tokens for 20 one-time rotations
and does not perform an additional login or create another fixture. The
nearest-rank calculation and all warm-up behavior remain byte-for-byte
equivalent. The returned `p50_ms`, `p95_ms`, `p99_ms`, and `samples` fields stay
in the JSON output. `p99_ms` remains diagnostic evidence only: it is not added
to the threshold comparison or converted into a qualification gate.

The baseline table records tenant-refresh as 20 one-time rotations. Its
recorded p95 value (`27.282 ms`) and the machine-readable baseline remain
unchanged; a sample-count change alone must not be used to raise a threshold or
rewrite a measured value.

## Verification And Qualification Stop Line

The implementation owner performs one static review, one exact-write-set check,
and one `git diff --check`. No PHP, performance, integration, browser, CI, or
aggregate test runs occur in the implementation stage.

The qualification owner runs `./scripts/test-performance` exactly once for the
resulting fixed candidate. A failure blocks this qualification and is reported
with its direct evidence; it is not retried. Already-passed qualification
groups are not rerun. This follow-up does not establish Runtime regression
evidence, move a downstream-consumption lock, publish a package, create a tag or
release, or claim production readiness.

Completion is one independently reviewable implementation commit after this
contract commit, followed by the qualification stop line above.
