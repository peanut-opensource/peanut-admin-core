# P1-PKG02-R45 Qualification Closure Contract

## Fixed Candidate

R41 through R44 produced clean candidate
`b84b8876cf24e7b749f0e79ab95053e772c922e7`. The package projections retain
the two consolidated `0.1.0-alpha.1` boundaries. R41 corrected current lock
evidence, R42 repaired one qualification-fixture format violation, R43 aligned
one stale unit assertion with the authoritative R35 create-snapshot contract,
and R44 removed internal product naming from public status history.

The qualification groups completed through the contracts' no-repeat resume
rules. The initial workspace run passed lock reproduction, Composer, PHPUnit,
PHPStan, and Deptrac. R42 passed PHP-CS-Fixer; the workspace tail passed lint,
typecheck, build, and Compose configuration; R43 passed all Web units; and R44
passed the remaining license, product-neutral content, directory, and diff
guards. Previously qualified browser, recovery, Starter, and performance groups
were not repeated.

## Authorized Change

R45 may change only:

- this contract;
- `docs/decisions/dependencies/p1-pkg02-lock-evidence.json`;
- `docs/status/p1-pkg02-alpha-publication-contract.md`;
- `docs/status/index.md`.

These files must record the exact candidate, completed verification evidence,
and the remaining publication stop line. They must not claim registry
publication, downstream approval, a tag, Release, or production readiness.

R45 must not change a package, Runtime, test, manifest, lock, script, public
version, dependency, or earlier qualification contract. After static review,
JSON parsing, exact write-set inspection, and `git diff --check`, commit the
planning update without rerunning a passed qualification group. Package-content
inspection then uses the exact R45-recorded candidate.
