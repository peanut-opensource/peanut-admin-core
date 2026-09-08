# Alpha.13 Publication Candidate Contract

Document ID: `core-doc-status-alpha13-publication-candidate-contract`

```text
task: CORE-ALPHA13-CANDIDATE
mode: Development
state: D05 review repairs prepared; affected-gate verification pending
prerequisite: 2901732c6f722a91186b46c40725ed6cfc60339c
composer_package: peanut-admin/core@0.1.0-alpha.13
npm_package: @peanut-admin/admin@0.1.0-alpha.13
candidate_commit: the commit containing this contract, subject to integration-owner freeze
deferred_verification: CORE-ALPHA13-Q01
qualification: pending one new immutable-candidate run and fixed-commit review
publication: pending successful qualification, immutable preflight and release authority
downstream_adoption: false
```

## Scope And Inherited Changes

This Development commit prepares the next coordinated package identity from the
converged prerequisite. It changes no Runtime or test behavior. The existing
alpha.12 publication recorded by `10b85c5fba73615f02eb6bb39c9ef63a9d458bb6`
remains historical: source `9089516a18f19e19a048683594087e0b4ffc5455` and
Composer split `9017212da0da63f445d693be94d533f681c6dc92`. Neither that
publication nor its qualification proves anything about this later Runtime.

The alpha.13 source includes these already committed changes:

- `9358686fee873dd235489c8794abf556fd70ec4f`: Local, Aliyun, Qcloud and
  Qiniu low-level storage drivers and product-neutral storage contracts.
- `22f6a6cc5ae5bb56aefd8b625c86f9cdcf630aea`: Auth exception mapping in
  `ProblemDetailsAdapter` and preservation of Qiniu provider identity.
- `41f65f53e8af71e83d5920e69951dc93817e05e8`: PHP unit/integration and Web
  test entry points aggregate the current package suites.
- `0710a94e1d0522f75dc0333f8dc71d03ab4d1d2b`: Local path, stream and
  filesystem contract hardening, object-key checks and Qcloud correction.
- `2901732c6f722a91186b46c40725ed6cfc60339c`: atomic administrator form
  commands in `MemberAdminService`, including the directly owned assertions.
- `4b89dd1`: MySQL 8.4 integration-boundary repair for ArtifactRevision parent
  lineage, native-PDO Workflow placeholders and EntitlementQuota failure replay.
- `1c86fa6`: serialize every tenant-owner removal path on the tenant row and add
  a deterministic two-transaction MySQL regression for the final-owner invariant.

These are source facts, not new qualification claims. Storage SDKs remain
optional Composer suggestions under the accepted storage dependency decision.
Core must not install them into the aggregate lock; a selecting Host owns its
SDK dependency, credentials, audit and real-provider acceptance.

## Exact Write Set And Development Acceptance

The write set is README; root/backend/package manifests and root locks;
`packages/php/composer.json`, `packages/web/package.json`; the ArtifactRevision
and EntitlementQuota `Package::VERSION` constants and the existing
EntitlementQuota version assertion; Starter backend/frontend manifests and
locks; version expectations in `scripts/check-workspace` and
`tests/starter/assert-generated-starter.php`; current lock evidence;
`resources/project-resources.json`; this contract, status index, testing guide,
source map, documentation registry and its generated catalog/license inventory;
`deptrac.yaml` classifies the three accepted optional storage SDK types and allows
only FileMedia to depend on that layer.

All exact first-party identities become `0.1.0-alpha.13`. Composer 2.10.2
regenerates only the Core path-package lock entries and consumer content hashes;
the inherited optional `suggest` metadata is included. Third-party resolutions
remain unchanged. The pnpm change is confined to the exact workspace specifiers.

One focused Development round checks the existing workspace identity block,
Composer manifest/lock consistency, both offline frozen pnpm locks, documentation
governance, documentation impact closure and `git diff --check`. Package behavior,
browser, installation, recovery, performance and the aggregate `./scripts/check`
are deferred to `CORE-ALPHA13-Q01`. Offline lock generation is not a fresh
security-advisory audit. No new test or qualification entry point is added.

The first Q01 attempt stopped in `check-docs` before stateful resources because
the operator PATH omitted the registered Docker CLI; the single failed-group
retry passed after restoring `/usr/local/bin`. The continuation then stopped at
the architecture group because the already accepted Aliyun, Qcloud and Qiniu
optional SDK types were not classified in Deptrac. This Development repair adds
the narrow `StorageProviderSdks` layer and no package dependency or Runtime code;
it invalidates that candidate identity. The new fixed candidate reruns only the
affected documentation-governance and architecture groups before continuing the
still-unexecuted Q01 groups.

That continuation passed OpenAPI, Runtime coverage, third-party inventory,
secret scanning, supply-chain checks, PHP unit (`311 tests / 4,097 assertions`)
and Web (`56 files / 231 tests`). Its first Integration execution then exposed
three pre-existing implementation defects now reachable through the corrected
aggregate entry point: MySQL rejected a CHECK that referenced an AUTO_INCREMENT
column, native PDO rejected repeated named parameters in ArtifactRevision and
Workflow statements, and EntitlementQuota interpreted MySQL JSON key order and
the text `SQLSTATE` as domain state. The repair keeps database-enforced parent
lineage by storing the parent revision number and binding it through an exact
composite foreign key plus a strictly-earlier CHECK; it also uses distinct PDO
parameters and exact order-independent replay fields. The candidate identity is
therefore invalidated again. Q01 may now rerun only the failed Integration group
once; Security, browser, recovery, performance, Starter and workspace groups
remain unexecuted until it passes. Four warning details were not persisted from
the first run and must be recorded verbatim by the retry rather than guessed.

The Integration retry passed `303 tests / 2,937 assertions`; its earlier warning
and risky counts did not recur, while PHPUnit reported eight non-failing
framework deprecations without details. The PHP Security group then passed four
suites (`25/209`, `31/258`, `38/397`, `120/3,693`) with zero skips. The first
Browser invocation stopped before Playwright because the operator PATH omitted
the registered pnpm executable. With the exact Node 24.13.0/pnpm 11.13.0 path,
the first real Browser execution passed 45 of 46 tests and retained a trace for
the sole failure. That trace proves tenant selection returned 200 and the SPA
rendered the requested workspace, while the test's five-second URL assertion
expired as the real `/api/v1/menus` readiness request completed. The focused
repair makes the existing Reference Codes helper await and assert login, Tenant
selection, menu readiness and the requested workspace identity; it does not
increase a timeout, intercept a request or weaken an assertion. This test-only
repair invalidates the candidate identity and permits one Browser failed-group
retry. Recovery and later groups remained unexecuted at that point.

The Browser retry passed all `46/46` tests. Recovery then passed its contract,
clean-install and restore suites (`2/17`, `4 seconds`, `2/27`); its external
report digest is recorded by the candidate lock. Every registered performance
target passed below its threshold, including the 10-target, 500-target and
5,000-target authorization cases and the login, refresh, context and master
routes. The independent Starter run passed deterministic generation, backend
and frontend suites and the production build.

The first Workspace execution reached the real PHPStan stage after Composer,
lock/store and the `688`-test PHP aggregate succeeded (`385` non-integration
tests, `303` integration tests statically skipped by that stage, `3,741`
assertions, eight non-failing framework deprecations). PHPStan then reported one
missing iterable value type on the private test helper
`AdminAccessServiceTest::administrationState()`. The focused repair documents
the exact nested table-snapshot shape; it changes no Runtime, assertion, skip or
analysis rule. This test-only type declaration invalidated the candidate
identity and permitted one Workspace failed-group retry after a focused PHPStan
check. That retry passed PHPStan with zero errors and then reached the formatter
for the first time, which reported only pre-existing multiline layout in the
atomic administrator implementation and its test. The repository formatter was
applied to those exact two files without changing behavior, assertions or rules.
Because formatting is a mechanical gate repair, Workspace may verify the exact
new identity once more. The final repository-contract group remains unexecuted
until Workspace passes.

That exact Workspace retry passed all stages: `688` tests / `3,741` assertions,
PHPStan zero errors, Deptrac zero violations/uncovered, PHP-CS-Fixer `935` files,
Web lint/typecheck, `56` files / `231` tests, production Web and documentation
builds, and Compose validation. The final repository-contract group also passed.
Package-content inspection then passed for the same fixed tree: Composer contains
`728` files and 13 Runtime PSR-4 roots; npm contains `73` packed files and 14
exports, with its dry-run and tarball file lists identical. Digests are recorded
outside the source tree in the immutable candidate lock.

The first fixed-commit D05 review found two inherited issues rather than approving
them away. Low-context review found Starter documentation still naming alpha.2
or generic 0.1.0 while all candidate manifests and locks use alpha.13; the two
public pages now name `0.1.0-alpha.13` as an unpublished, unapproved local
candidate. Authorization review found standalone `replaceRoles()` did not take
the tenant row lock before checking the final-owner invariant. All owner-removal
paths now use tenant-before-member lock order, and the focused MySQL regression
proved two concurrent role removals produce one success, one
`LAST_ACTIVE_OWNER_REQUIRED`, and one remaining active Owner (`9` tests / `61`
assertions). These repairs supersede the reviewed identity. Only their affected
documentation, Integration/static gates and fixed-commit D05 delta review may be
carried out for the new candidate; prior unaffected Q01 groups are not repeated.

The existing license generator initially encountered a missing pnpm package
index for `@playwright/test@1.61.1` in the registered cache. Q01 installed the
exact frozen dependency set from the registered pnpm store; the full generated
inventory and subsequent supply-chain check then passed without changing a
third-party resolution. This is candidate-local qualification state, not a
Registry publication claim.

Docs-impact classifications are architecture-decision, technical,
developer-site and generated because package and governance paths route there.
The existing accepted package boundary and SDK decision suffice; this commit
adds no architecture decision. Exact waived targets are `docs/README.md`,
`docs/index.md`, `docs/architecture/index.md`, `docs/core-concepts/index.md`,
`docs/guide/installation.md`, `docs/guide/module-development.md` and
`docs/guide/troubleshooting.md`: this identity-only preparation changes no
architecture, public command, installation flow or developer navigation.

## Qualification Resource Bundle

The registry adds separate Alpha.13 IDs for MySQL, Valkey, Compose, Chromium,
output and the six listeners; Alpha.12 entries remain historical. The existing
registered PHP 8.3.24, Composer 2.10.2, Node 24.13.0, pnpm 11.13.0 and pnpm
store retain their persistent toolchain lifecycles. The runner already consumes
these parameters; no runner or Compose implementation change is needed:

```bash
export COMPOSE_PROJECT_NAME=peanut-admin-core-alpha13-q01
export MYSQL_PORT=33433 DB_HOST=127.0.0.1 DB_PORT=33433 CACHE_PORT=36433
export BACKEND_PORT=38133 FRONTEND_PORT=35233
export PEANUT_BROWSER_BACKEND_PORT=38233 PEANUT_BROWSER_FRONTEND_PORT=35333
export PEANUT_STARTER_BACKEND_PORT=38333 PEANUT_STARTER_FRONTEND_PORT=35433
export MYSQL_DATABASE=peanut_admin_alpha13_qualification
export TMPDIR=/private/tmp/peanut-admin-core-alpha13-q01
export PEANUT_COMPOSER=/private/tmp/peanut-admin-core-tools/composer-2.10.2
```

The qualification owner must announce every selected registered ID/environment/
address, verify registered tool versions and health, and lease fresh resources.
All eight ports must be unbound; the project, volumes and databases must not
exist. Create and verify the registered writable TMPDIR before the first gate.
The two hard-coded outputs `/tmp/peanut-admin-playwright-results` and
`/tmp/peanut-admin-recovery-report.json` must be absent before the run and
serially, exclusively leased across every candidate and browser/recovery owner.
This is fresh output ownership, never reuse of Alpha.12 output or evidence.
After any outcome, clean only the Alpha.13 project/volumes, exact outputs and
owned child processes; prove zero residual project resources and listeners.
Persistent tool/cache resources are retained. Conflict or stale data blocks
qualification; there is no implicit replacement service, port, browser or mock.

## The One Qualification And Publication Sequence

The machine-readable record at
`docs/releases/qualifications/0.1.0-alpha.13.json` is pending and deliberately
contains no candidate identity or passing evidence. Final publication additionally
requires the [source-tag qualification gate](../guide/release-qualification.md).
The tag may identify a later evidence-only commit; every intervening change must
meet its fixed allowlist and both package subtrees must match the qualified
candidate exactly. Runtime, test or release-tooling repairs require a new freeze.

1. Integrate all approved preparation, resolve known blockers and freeze one
   clean source commit/tree. Record its manifest, four lock, generated-artifact
   and package-inventory SHA-256 identities, resource IDs/environment, lease/run
   ID and command in an immutable candidate lock. The publication verifier computes
   source/tree and package-projection identities; the full resource, manifest and
   inventory lock remains owner-authored and must be compared before invoking the runner.
   A branch name or this self-referential contract is not that immutable lock.
2. From a detached worktree of that exact locked commit, complete the resource
   preflight and exact dependency installation; run `./scripts/check` once for
   `CORE-ALPHA13-Q01`, then package-content inspection and the required
   fixed-commit D05 nine-role review. Record results for the same candidate tree.
   A failure invalidates the candidate; diagnose once, return source repair to
   Development, freeze a new identity and rerun only the failed group once.
3. With successful qualification and release authority, preflight the registered
   source/split GitHub repositories, npm and Packagist: the alpha.13 version,
   annotated tags and Release must be absent. Verify publication credentials
   through metadata only. Do not move, overwrite or delete an existing identity.
4. Publish the exact qualified `packages/php/` projection and its annotated
   Composer split tag first. Only then create the matching source tag, whose
   existing release workflow checks split equality, publishes npm with
   provenance and creates the GitHub Release. Refresh Packagist explicitly
   through an authenticated maintainer session.
5. Verify clean Composer and npm consumers resolve the same immutable alpha.13
   identities and record source/split references, registry integrity and
   provenance. Only the subsequent authorized downstream task may change an
   Application lock. Package publication does not establish deployment.

The source/split GitHub permissions, GitHub `npm-release` environment's
`NPM_TOKEN`, npm provenance capability and Packagist maintainer session are
external stop lines. Missing credentials stop their direct publication step;
never read or persist their secret values. Real storage-provider credentials
and services are Host-owned and are not created or qualified by this bundle.
This preparation creates no tag, Release, registry publication or downstream
lock and performs no push. Prior Alpha.12 approval is not Alpha.13 evidence.
