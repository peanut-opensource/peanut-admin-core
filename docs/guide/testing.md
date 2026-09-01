# Testing

Peanut Admin uses layered verification. A bounded development task proves the
behavior it changes; a fixed milestone candidate proves the repository as a
whole. This keeps feedback fast without weakening fail-closed security,
qualification, or release evidence.

## Development Task Verification

For ordinary, reversible, bounded work:

1. Name the affected behavior and test owner in the task contract.
2. Run the smallest relevant unit, integration, type, build, static, or
   documentation checks that cover the change.
3. Run `git diff --check` and inspect the staged file list.
4. Record the aggregate checks deferred to the Starter v1 milestone candidate.

Do not run the full repository suite merely because a task changes code or
documentation. Do not omit a focused check needed to prove the task itself.

The following boundaries require risk-proportionate focused checks in the same
task that changes them:

- Tenant isolation, identity, authentication, authorization, and audit;
- schema migrations, destructive behavior, and rollback boundaries;
- concurrency, idempotency, transaction atomicity, and external side effects;
- breaking changes to public APIs, package contracts, generated artifacts, or
  downstream integration contracts.

A task that does not touch one of these boundaries does not inherit its full
test suite. A task that does touch one must test the changed boundary, including
the relevant negative and failure paths.

## Worktree Dependencies

Run the following once when opening a new worktree:

```bash
./scripts/bootstrap-worktree-dependencies
```

The command resolves the configured pnpm content store dynamically and performs
an offline, frozen install. The persistent content store must not be inside any
Git repository or worktree tree. If the store is missing required content,
populate it through the separate, explicitly networked command and retry:

```bash
./scripts/warm-worktree-dependencies
./scripts/bootstrap-worktree-dependencies
```

Focused tests then reuse that worktree-local layout. Bootstrap is a no-op while
the lock and package configuration, Node and pnpm versions, operating system,
architecture, and resolved store path remain unchanged. It removes and rebuilds
only ignored `node_modules` layouts inside the current worktree; a dependency
link into another worktree is never reused.

## Milestone Candidate Verification

The stable aggregate entry point is:

```bash
./scripts/check
```

The qualification owner first claims the candidate's registered resources and
exports fixed ports for MySQL, the generated Host database contract, cache,
browser backend/frontend and generated starter backend/frontend. In particular,
`PEANUT_STARTER_BACKEND_PORT` and `PEANUT_STARTER_FRONTEND_PORT` are required;
the starter verifier never selects a random listener. A missing or occupied
registered port stops qualification instead of switching to another address.
GitHub-hosted workflow jobs select
`peanut-admin-core-github-ci-starter-backend` and
`peanut-admin-core-github-ci-starter-frontend` from
`resources/project-resources.json`; their fixed loopback ports are isolated by
the per-job runner and are removed with the job. The aggregate quality job also
selects the registered, checksum-pinned ripgrep 15.1.0 binary before any
negative-pattern gate runs. Starter verification launches Vite as the owned
child process so repeated aggregate invocations release the same fixed port.

It builds the documentation and Admin Web, validates OpenAPI and Module manifests, runs architecture checks, PHP unit and MySQL integration tests, authorization security tests, browser tests, PHPStan, Deptrac, PHP-CS-Fixer, ESLint, TypeScript checks, Vitest, and production builds.

Run `./scripts/check` and the qualification-only suites against a clean, fixed
commit when preparing a Starter v1 milestone, qualification, or release
candidate. That concentrated gate includes the full regression and browser
matrix, clean install and upgrade, backup and restore, performance, starter
reproducibility, and independent fixed-commit review required by the candidate.
The secret gate scans the complete history reachable from the fixed candidate
`HEAD` plus all current tracked and untracked files; unrelated local Git refs
from another branch, worktree, or tool snapshot are not candidate evidence.
`./scripts/verify-internal-starter` creates and installs two independent starter
copies and belongs only to this fixed-candidate or qualification phase.

If a performance check fails, the affected qualification, release, or
downstream consumption-lock movement remains blocked until it passes. The
failure and remediation evidence stay recorded, but unrelated ordinary feature
development may continue on independently reviewable commits.

Historical Q01 contracts, failed runs, and remediation evidence remain valid
history. They do not require every new capability task to repeat the old Q01
sequence. Q01 is rerun as the concentrated qualification task after a fixed
Starter v1 candidate exists.

## Focused Commands

```bash
./scripts/test-unit
./scripts/test-integration
./scripts/test-security
./scripts/test-browser
./scripts/check-openapi
./scripts/check-architecture
./scripts/check-docs
```

Run the fictional cross-Module contract example with MySQL available:

```bash
PEANUT_INTEGRATION=1 php vendor/bin/phpunit \
  examples/module-contract/ExampleModuleContractTest.php
```

That example proves typed Project and Queue targets, one shared Reference identity space, a single-target write, a multi-target read, per-target policy publication, category-confusion denial, private-scope denial, and P0 bulk-write rejection.

## Security Test Rules

- A skipped security test is not qualification evidence.
- Tenant and platform audiences require separate negative tests.
- Lists and single-object actions must use the same provider semantics.
- Tests must include missing context, stale revision, disabled Module, wrong target type, cross-tenant identifiers, and shared-master scope denial.
- Browser state must be cleared on tenant switch; late responses from the previous tenant must not render.

The full browser suite uses real MySQL, ThinkPHP, and Vite services for its full-stack projects and does not intercept `/api/**`. Desktop and mobile projects must complete with zero skipped tests.

These zero-skip rules apply whenever the security or full-browser suite is in
the current task's risk scope and always apply to milestone qualification.

Documentation examples are checked by `./scripts/verify-doc-examples`. The verifier binds prose markers to current source symbols, proves all 75 current P0 operations use concrete handlers, performs a temporary database install, runs the Module tutorial, and executes the internal starter in clean directories.
