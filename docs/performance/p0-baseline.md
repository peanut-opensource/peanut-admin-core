# P0 Performance Baseline

The P0 gate measures security-critical paths; it does not claim a production QPS capacity. The benchmark installs a fresh reference profile into MySQL, seeds 5,000 Projects and paged WorkItems, verifies result correctness, and then records p50, p95, and p99 latency.

## Fixed Dataset And Runtime

- MySQL image: `mysql:8.4.10`.
- PHP baseline runtime: `8.3.24`.
- Runner classes: Darwin/Linux on arm64, aarch64, or x86_64; unknown classes fail instead of borrowing this baseline.
- Four complete warm-container calibration measurements were taken on 2026-07-16 after the query shape changed to `authorized-structured-target-query-v2`.
- Version 3 replaces the earlier synthetic WorkItem path with the production DataPermission engine, target resolver, policy evaluation, and SQL constraint compiler.
- The checked-in p95 for each scenario is the highest observed p95 with its current operation shape. Existing unchanged auth baselines were retained when they were already higher than the new observations.
- CI uses the same lock files, PHP patch version, database image, fixture size, sample counts, and script.

The versioned machine-readable values are in `tests/performance/p0-baseline.json`. `scripts/test-performance` fails when the current p95 exceeds the recorded value by more than 20 percent.

## Recorded P95

| Scenario | Dataset | Baseline p95 | Query bound |
| --- | ---: | ---: | ---: |
| Tenant login | 20 independent sessions | 314.405 ms | Password hashing and transaction |
| Tenant refresh | 20 one-time rotations | 27.282 ms | One rotation transaction |
| Tenant context | 30 samples x 20 validations | 110.394 ms | Session and state validation |
| Typed targets | 30 operations x 10 IDs | 558.467 ms | 11 parameters per authorized query; 20-row page |
| Typed targets | 10 operations x 500 IDs | 323.058 ms | 501 parameters per authorized query; 20-row page |
| Typed targets | 3 operations x 5,000 IDs | 489.323 ms | fixed 2 parameters per authorized query; 20-row page |
| Shared-master scope | 20 operations x 10 typed targets | 97.744 ms | 1 query per operation |

The typed-target scenarios call the real Module `PdoTargetResolver` and paginated `PdoWorkItemQuery`, including functional permission checks, effective data policy, target validation, and SQL constraint compilation. Up to 500 requested IDs use parameterized `IN`; larger requests use one JSON parameter and `JSON_TABLE`, so the 5,000-target SQL remains bounded at two parameters. The shared-master scenario uses one truth table and one scope table and verifies that only the typed-target-visible candidate is returned.

## Interpretation

These values are regression references, not service-level objectives. A production product must establish its own hardware, dataset, concurrency, cache, and endpoint baselines. A changed runner class requires a separately reviewed baseline update; increasing a number solely to make CI pass is prohibited.
