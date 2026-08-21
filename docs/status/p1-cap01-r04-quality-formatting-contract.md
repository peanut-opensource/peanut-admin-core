# P1-CAP01-R04 Quality Formatting Contract

## Status

```text
task: P1-CAP01-R04
state: accepted mechanical remediation
prerequisite_commit: 06262711da20c480345e4dd218d13ca8d8e72334
source_candidate_commit: 3972c9aefcd55ac71d07a47739a99d23bb0ae30c
failed_run: 31519487650
failed_job: 93872741070
failed_check: PHP CS Fixer ordered imports
runtime_semantics_change: none
test_semantics_change: none
dependency_change: none
package_publication: none
```

## Finding

The final CAP01 Quality run completed all 622 PHPUnit tests with 3599
assertions and completed PHPStan with no error. Its last formatter check found
only two existing Workflow integration-test import blocks whose lexical order
differs from the repository `ordered_imports` rule. No executable statement,
assertion, fixture, schema or Runtime file failed.

PR #7 was already merged because the repository did not require Quality as a
blocking status. This follow-up starts from that exact merge commit and does
not rewrite or revert the merge. Branch-protection remediation is tracked
independently and is not part of this source write set.

## Exact Implementation Write Set

After this contract commit, one mechanical implementation commit may change
only:

- `packages/php/workflow/tests/Integration/Application/WorkflowRuntimeTest.php`;
- `packages/php/workflow/tests/Integration/Application/WorkflowCapabilityCompositionTest.php`.

The only permitted changes are the import moves printed by PHP CS Fixer in job
`93872741070`. No symbol, alias, executable statement, test, assertion, data,
schema, Runtime source, script, configuration, manifest or lockfile may change.

## Verification And Stop Line

The implementation owner checks the exact two-file write set, runs
`git diff --check`, and runs PHP CS Fixer in check mode with path intersection
limited to these two files under PHP 8.3. It does not rerun PHPUnit, PHPStan,
Recovery, Security, Performance, Starter, browser or package checks locally.

The follow-up is submitted as a PR to `dev`. Repository automation remains
authoritative; a new non-formatting failure receives one read-only diagnosis
and stops instead of widening R04. CAP02 starts only after this follow-up is
merged and the final Core `dev` commit is fixed as its 40-character
prerequisite. R04 does not qualify, adopt or publish Alpha.5, and the DCS
candidate remains `UNKNOWN`.
