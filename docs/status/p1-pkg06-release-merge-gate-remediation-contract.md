# P1-PKG06 Release Merge Gate Remediation Contract

## Status

```text
state: accepted
prerequisite_commit: de453f6dccd40b047a51bf8c6669e18759dc3916
runtime_change: none
package_publication: none
```

PR #1 exposed four repository-infrastructure failures after Alpha.3 publication:
required database ports were absent from CI jobs, the performance job selected a
different PHP patch than the versioned baseline, the dependency graph was not
available to dependency review, and the lock resolved a high-severity
`nanoid@3.3.16` advisory fixed by `3.3.17`.

## Objective And Stop Line

Restore the existing merge gates without weakening, skipping, or duplicating
them. This task changes no Runtime behavior, public API, package version,
published artifact, schema, application integration, or performance threshold.
It does not rerun checks that already passed.

## Exact Write Set

- `.github/workflows/ci.yml`;
- `.github/workflows/starter.yml`;
- `.github/workflows/recovery.yml`;
- `.github/workflows/security.yml`;
- `.github/workflows/performance.yml`;
- `pnpm-workspace.yaml` and `pnpm-lock.yaml`;
- this contract, `docs/content-status.json`, and `docs/status/index.md`.

The repository setting change is limited to enabling the dependency graph used
by the existing dependency-review job. No workflow, test, audit, or threshold
may be disabled or made advisory.

## Focused Acceptance

1. CI jobs that invoke the repository, security, recovery, or starter gates
   receive explicit `MYSQL_PORT`, `DB_PORT`, and where required `CACHE_PORT`.
2. The performance job uses PHP `8.3.24`, matching the versioned baseline.
3. pnpm resolves `nanoid` to `3.3.17`, and one high-severity audit reports zero
   high or critical advisories.
4. Dependency review can read the enabled repository dependency graph.
5. Only the checks that failed on PR #1 are allowed to rerun.
6. Static YAML parsing, the exact write set, and `git diff --check` pass before
   the implementation commit.

