# P1-CAP01-R01 Quality Process Termination Remediation Contract

## Status

```text
task: P1-CAP01-R01
state: resolved; aggregate advanced to separate contract failures
prerequisite_commit: bbea6b5838de6c4e771b6d1523a0034fbdfa8b9a
source_candidate_commit: 3972c9aefcd55ac71d07a47739a99d23bb0ae30c
source_candidate_tree: d6dbde37907d1dd43b00057fc16fbd1a8d6dd052
pull_request: peanut-opensource/peanut-admin#7
failed_group: check-workspace PHP aggregate
test_owner: P1-WORKFLOW-RUNTIME-001
runtime_semantics_change: none
result: private parser method names no longer collide with public lookup methods
dependency_change: none
package_publication: none
```

## Finding And Sealed Evidence

The PR head fixed above passed the independent Security, Performance,
Recovery, Internal Starter and dependency-review checks. CI Quality then
reached the final `php vendor/bin/phpunit` command in `scripts/check-workspace`.
PHPUnit 12.5.31 completed 91 percent of 622 tests and terminated while reporting
`WorkflowGraphTest::testCanonicalizesAProductNeutralAnyReviewGraph`, with no
PHP exception, assertion detail, syntax error or memory diagnostic. The current
GitHub evidence is run `31513008594`, job `93855744530`.

The controlling qualification handoff records this as the second failure at
the same stop line. The Quality group is therefore blocked under the ordinary
retry budget. This contract creates a separate, bounded remediation; it does
not reinterpret either failure as a pass and does not authorize another blind
Quality rerun.

Static review of the named test and its direct production path found no
`exit`, signal, subprocess, I/O, shutdown handler, unbounded traversal or
reachable unbounded recursion. The fixed graph has four nodes and three edges.
Consequently the test name may identify either a direct PHP/native failure or
only the test active when a process already affected by earlier tests or the
Runner terminated. Source changes are not authorized until the focused
diagnostic distinguishes those cases.

All previously passing check groups are sealed. This remediation must not run
`./scripts/check`, browser, security, performance, recovery, package projection,
Starter, documentation or registry-consumer groups.

## Objective And Non-Goals

The objective is to obtain one deterministic PHP 8.3 result for the named test,
identify whether the failure is local to WorkflowGraph or caused by same-process
suite state, and make the smallest repair that preserves the full 622-test
corpus and every existing assertion.

This task does not add Workflow behavior, schema, HTTP operations, product
logic, dependencies, package versions, CI retries, looser limits, test skips,
release metadata, downstream adoption or publication. It must not split a test
merely to hide a reproducible production defect. It must not increase memory or
time limits without evidence that the accepted limit itself is incorrect.

## Diagnostic Contract

Diagnostics use the installed PHP 8.3 toolchain with `display_errors=1`,
`display_startup_errors=1`, `error_reporting=-1`, PHPUnit result caching
disabled and debug events enabled. Dependency installation is not part of this
task; refreshing the existing generated Composer autoloader is permitted when
the source candidate namespaces are absent from the local ignored `vendor/`
state.

The remediation owner performs the following serially and records the command,
PHP patch version, exit code and last test event:

1. Run only
   `WorkflowGraphTest::testCanonicalizesAProductNeutralAnyReviewGraph` once.
   If it fails, the repair is restricted to the direct test/WorkflowGraph path.
2. If the isolated test passes, run the `packages/php/workflow/tests` subtree
   once with the same diagnostics. If it fails, the repair is restricted to
   the proven Workflow test interaction.
3. If both pass, do not run the 622-test aggregate before repair. Inspect the
   already captured CI order and the immediately preceding tests, then isolate
   the Workflow suite from prior-suite process state only if every same test
   file and assertion remains executed. If the evidence cannot identify a
   bounded repair, stop with the captured results instead of guessing.

An exit caused by a PHP exception or assertion is repaired at that source. An
external signal, native crash or cumulative process-state failure may be
repaired at the PHPUnit suite boundary, but the resulting commands must retain
the same test files, fail-on-warning behavior and exit semantics. Retrying on
failure, randomizing until pass, suppressing output, excluding the test or
marking it skipped/incomplete is forbidden.

## Exact Remediation Write Set

After the diagnostic proves the applicable path, one implementation commit may
change only the narrowest necessary subset of:

- `packages/php/workflow/src/Definition/WorkflowGraph.php`;
- `packages/php/workflow/tests/Unit/Definition/WorkflowGraphTest.php`;
- `packages/php/workflow/tests/Support/WorkflowGraphFixture.php`;
- `packages/php/workflow/tests/Integration/Persistence/PdoWorkflowRepositoryTest.php`;
- `packages/php/workflow/tests/Integration/Application/WorkflowRuntimeTest.php`;
- `phpunit.xml`;
- `scripts/check-workspace`;
- this contract and `docs/status/index.md` for the final result only.

The support fixture may be added only if the diagnostic proves that using a
PHPUnit test class as a shared fixture contributes to the failure. `phpunit.xml`
or `scripts/check-workspace` may change only if isolated Workflow tests pass and
the evidence points to prior-suite process state. A suite-boundary repair must
run all existing configured test directories exactly once across its commands.
No production file may change for a Runner-only or test-fixture defect.

If a required file is outside this whitelist, work stops for an independent
contract correction before that file changes.

## Verification And Stop Line

The implementation owner performs static review, an exact-write-set check and
`git diff --check`. The remediation owner then runs only the previously failed
PHP aggregate once:

```bash
php vendor/bin/phpunit
```

The command must use PHP 8.3, the repository configuration and the unchanged
622-test corpus. A failure blocks CAP01 again and is not retried under this
contract. A pass is committed as the exact same tree and may be pushed to PR
#7; repository-required pull-request checks remain authoritative and may not be
bypassed or weakened.

CAP01 closes only after PR #7 is merged into `dev`. CAP02 starts from the exact
40-character merge commit, not from this contract, a branch name or the
unmerged source candidate. This remediation does not qualify CAP01 under CAP05,
adopt it under CAP06, publish Alpha.5, or move an application dependency lock.

## Result

The isolated PHP 8.3.24 diagnostic exposed the direct fatal that CI had hidden:
`WorkflowGraph` declared public instance lookup methods and private static
parser methods with the same `node` and `transition` names. PHP terminated on
the first class load with `Cannot redeclare`.

The repair renamed only the private parsers to `parseNode` and
`parseTransition` and updated their two internal call sites. No graph behavior,
public method, schema or test was changed. The authorized PHP aggregate then
executed all 622 tests, proving that the premature process termination was
removed. It exited with one Schema expectation failure and one risky Harness
test that had previously been hidden by the fatal. Those two findings are not
waived or repaired under R01; CAP01 remains blocked pending a separate exact
contract.
