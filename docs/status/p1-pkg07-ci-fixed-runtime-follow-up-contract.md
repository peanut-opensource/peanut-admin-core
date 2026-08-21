# P1-PKG07 CI Fixed Runtime Follow-up Contract

## Status

```text
state: accepted
prerequisite_commit: 4ba32ae
runtime_change: none
package_publication: none
```

P1-PKG06 restored the missing port inputs, dependency graph, and patched
`nanoid` resolution. Its second CI run exposed three independent stale
projections: two Starter assertions still required Admin Web Alpha.2, the
generated license inventory still recorded `nanoid@3.3.16`, and setup-php
resolved PHP 8.3.33 despite the performance baseline requiring 8.3.24.

## Objective And Non-Goals

Align the failed merge gates with already-authorized Alpha.3 and run the
performance gate through the repository's existing
`php:8.3.24-cli-bookworm` image. The local default remains the caller's PHP
binary. This task does not change a performance value, environment allowance,
Runtime behavior, package version, dependency version, schema, API, or
published artifact.

## Exact Write Set

- `tests/starter/assert-generated-starter.php`;
- `scripts/check-workspace`;
- `docs/reference/third-party-licenses.generated.md`, generated only through
  `./scripts/check-third-party-licenses --write`;
- `scripts/test-performance`;
- `tests/performance/PerformanceQualificationContractTest.php`;
- `.github/workflows/performance.yml`;
- this contract, `docs/content-status.json`, and `docs/status/index.md`.

## Fixed PHP Execution Contract

When `PEANUT_PERFORMANCE_PHP_IMAGE` is absent, `scripts/test-performance`
retains its current local `php` behavior. When present, the script validates a
single Docker image reference and executes every PHP performance step through
that image with the repository mounted read-only enough for execution, the
working directory fixed to `/workspace`, and host networking used to reach the
MySQL container started by the same script. No shell evaluation is permitted.

CI builds the image from `docker/php/Dockerfile`, whose base is exactly
`php:8.3.24-cli-bookworm`, then supplies the internal image tag to the script.

## Focused Acceptance

1. Starter and workspace assertions require Admin Web
   `workspace:0.1.0-alpha.3` while Composer remains Alpha.2.
2. The generated license inventory records `nanoid@3.3.17`.
3. The performance contract test proves the fixed-image hook and workflow
   wiring without changing baseline values.
4. `bash -n`, PHP lint for changed PHP files, YAML parsing, exact write-set
   review, and `git diff --check` pass once.
5. Only the failed PR #1 workflows rerun after the implementation push.

