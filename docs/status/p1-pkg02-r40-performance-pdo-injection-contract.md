# P1-PKG02-R40 Performance PDO Injection Contract

## Observed Failure

The recovery-qualified candidate resumed at performance and stopped before
collecting a sample with `method param miss:dsn`. The performance fixture had
already created its isolated PDO connection, but called
`TenantAuthRuntimeFactory::create()` without that connection. The factory then
asked the ThinkPHP container to construct native PDO reflectively, so the
container attempted to resolve PDO's scalar `dsn` constructor parameter.

Current integration callers explicitly inject their fixture PDO into the same
factory. This is test-harness composition drift, not a production performance
regression.

## Authorized Change

R40 may change only:

- `tests/performance/run.php`.

The performance fixture must pass its existing isolated `$pdo` to
`TenantAuthRuntimeFactory::create(pdo: $pdo)`. All fixture data, benchmark
operations, sample and warmup counts, query shapes, environment evidence,
baseline values, and the 20 percent regression threshold must remain unchanged.

R40 must not register a global container binding, create another connection,
change the Runtime factory, production service wiring, benchmark scenario,
baseline, report schema, or performance assertion.

After static review and `git diff --check`, run `./scripts/test-performance`
once with the complete qualification environment. If it passes, qualification
continues once through workspace checks and the remaining `scripts/check`
guards; internal Starter verification remains authoritative from the passing
R39 recovery group and is not repeated. A failure receives one read-only
diagnosis and stops.
