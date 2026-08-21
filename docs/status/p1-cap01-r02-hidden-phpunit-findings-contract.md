# P1-CAP01-R02 Hidden PHPUnit Findings Remediation Contract

## Status

```text
task: P1-CAP01-R02
state: accepted remediation
prerequisite_commit: 159fa5ac7ca4465ba75ab1faf24821adba688ef1
source_candidate_commit: 3972c9aefcd55ac71d07a47739a99d23bb0ae30c
pull_request: peanut-opensource/peanut-admin#7
failed_group: check-workspace PHP aggregate
test_owner: P1-WORKFLOW-RUNTIME-001
runtime_change: none
schema_change: none
dependency_change: none
package_publication: none
```

## Findings

P1-CAP01-R01 removed the class-load fatal and its one authorized PHP aggregate
executed all 622 tests. The completed report contains exactly two new findings:

1. `SchemaTest::testDeclaresTheExactCheckConstraintCorpus` expects one literal
   backslash before each dot in the SQL text for `chk_workflow_event_key`, while
   the schema emits two. The schema text is correct for MySQL's string-literal
   parsing: the SQL statement needs two backslashes so the regular-expression
   engine receives one escaped literal dot. The test expectation is stale; the
   production schema must not change.
2. `WorkflowAtomicityContractHarnessTest::testInjectsEverySelectedWorkflowCheckpointAndRequiresCommittedSuccess`
   completes its exception-based contract verification but makes no PHPUnit
   assertion, so PHPUnit marks it risky. The test must explicitly register one
   assertion after the Harness returns successfully. Harness behavior and its
   failure detection must not change.

No other failure, error or risky test was reported. The aggregate used PHP
8.3.24, PHPUnit 12.5.31, 622 tests and 3598 assertions; 274 environment-gated
tests remained skipped as in the failed CI group.

## Objective And Non-Goals

Align the static schema expectation with the already correct MySQL SQL literal
and make the successful Harness contract visible to PHPUnit. This task does
not change a schema statement, graph behavior, Harness behavior, skip state,
warning policy, test count, dependency, package version, CI workflow or Runner
configuration.

The repair must not weaken the event-key pattern, replace the exact corpus with
a contains assertion, remove failure cases, suppress risky-test reporting or
use a blanket `doesNotPerformAssertions` annotation.

## Exact Implementation Write Set

After this contract commit, the implementation may change only:

- `packages/php/workflow/tests/Unit/Database/SchemaTest.php` — double only the
  two PHP-source backslash escapes in the expected `chk_workflow_event_key` SQL
  string so the resulting expected SQL contains the same two literal
  backslashes as `Schema::createSql()`;
- `packages/php/testing/tests/Unit/Workflow/WorkflowAtomicityContractHarnessTest.php`
  — add exactly one PHPUnit assertion count after the successful Harness call;
- this contract and `docs/status/index.md` for final result wording only.

No production source, schema, PHPUnit configuration, script, workflow,
manifest, lockfile or other test may change. An insufficient whitelist blocks
the task and requires a separate contract correction.

## Verification And Stop Line

The implementation owner performs static review, exact-write-set inspection
and `git diff --check`. The remediation owner then runs only the failed PHP
aggregate once under PHP 8.3:

```bash
php vendor/bin/phpunit
```

The same 622 tests must complete with no failure, error or risky test. A second
failure blocks CAP01 and is not retried under this contract. A pass is committed
as the exact same tree. Already passed Security, Performance, Recovery,
Internal Starter, dependency-review, browser, documentation and package groups
must not be run by this task.

This repair does not qualify CAP01, publish Alpha.5, move a downstream lock or
start CAP02. CAP01 closes only after repository-required PR evidence passes and
PR #7 is merged into `dev`; CAP02 must pin that exact merge commit.
