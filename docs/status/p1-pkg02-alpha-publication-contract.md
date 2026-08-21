# P1-PKG02 Alpha Publication Contract

## Status

```text
state: approved-planning-contract
package_candidate_commit: b84b8876cf24e7b749f0e79ab95053e772c922e7
composer_package: peanut-admin/core@0.1.0-alpha.1
npm_package: @peanut-admin/admin@0.1.0-alpha.1
qualification_owner: P1-PKG02-QUALIFICATION
publication_owner: P1-PKG02-PUBLICATION
```

P1-PKG02 qualifies and, only after every external ownership gate is recorded,
publishes the two public package boundaries produced by P1-PKG01. Internal
domain directories remain implementation ownership boundaries and must not
become independently published packages.

This contract does not add the future PC/UniApp client package. It does not
change Runtime behavior, package source, PHP namespaces, Web exports, schemas,
migrations, OpenAPI, authorization, Tenant isolation, audit, status
transitions, user-visible behavior, or the existing downstream-consumption
lock.

## Fixed Candidate Qualification

The package source candidate is the clean commit
`b84b8876cf24e7b749f0e79ab95053e772c922e7`. Its retained R32-R38 browser
remediation passed all 46 declared Playwright tests, R39 passed recovery,
clean-install, and internal Starter verification, and R40 passed all seven
performance scenarios and focused performance contracts. R41 through R44 then
completed the fixed-tree workspace and repository guards through their
explicit no-repeat resume contracts. The planning commit that fixes this
contract may be checked out above that candidate, but no package or Runtime
source may change before package-content inspection.

`P1-PKG02-QUALIFICATION` runs the repository aggregate gate exactly once:

```bash
./scripts/check
```

After it passes, the owner performs one package-content inspection without
publishing:

1. create an exact temporary split of `packages/php/` from the candidate;
2. verify that the split root contains `composer.json`, `LICENSE`, the ten
   runtime namespaces, and no Host, frontend, secret, generated credential, or
   unrelated monorepo content;
3. run `composer validate --strict` against the split manifest;
4. run `pnpm --dir packages/web pack --dry-run --json` and verify that every
   declared public subpath, package metadata, license, and required source file
   is present, while Host, test fixtures, secrets, and unrelated monorepo
   content are absent;
5. record SHA-256 digests for both immutable package projections.

If the aggregate gate or either content inspection fails, the owner performs
one read-only diagnosis and stops. A fix requires an independent remediation
contract and a new candidate commit; a failed package is never published.

The qualification evidence may change only:

- new `docs/reviews/p1-pkg02-alpha-publication-qualification.md`;
- `docs/status/index.md` for the exact qualification result;
- `docs/content-status.json` to register the evidence document.

Qualification does not authorize publication.

## Registry And Repository Gates

Before `P1-PKG02-PUBLICATION` may write external state, one approval record
must name and verify all of the following:

- the npm organization or owner authorized to publish the public
  `@peanut-admin/admin` scope;
- an npm automation token available to the publishing workflow without being
  committed or printed;
- the Packagist owner authorized to publish `peanut-admin/core`;
- the public Composer split repository whose root is the generated
  `packages/php/` projection, including its GitHub owner and immutable tag
  convention;
- GitHub repository and workflow permissions for the split push, tag, Release,
  npm provenance, and Packagist update;
- exact artifact contents, licenses, SHA-256 digests, and version-to-candidate
  mapping from the qualification evidence;
- rollback by publishing a newer immutable version and deprecating the broken
  version, never by mutating or deleting a published version.

The development monorepo remains the sole source of truth. The Composer split
repository is generated release output and accepts no direct development
commits. Publishing the monorepo root as `peanut-admin/core`, using a mutable
version, overwriting an existing version, or adding aliases, `replace`,
`provide`, metapackages, compatibility packages, or module-by-module packages
is forbidden.

## Publication Result

When every gate is satisfied, publication creates exactly:

- one immutable Composer version `peanut-admin/core` `0.1.0-alpha.1` from the
  qualified PHP split;
- one immutable public npm version `@peanut-admin/admin@0.1.0-alpha.1` from the
  qualified Web package;
- one GitHub prerelease that records the candidate commit, projection commits,
  registry URLs, artifact digests, licenses, and qualification evidence.

An isolated clean Composer consumer and an isolated clean npm consumer each
install the registry version once and resolve every documented namespace or
subpath. Passing those probes proves alpha package consumability only. It does
not claim production readiness, stable API compatibility, application
migration completion, or SaaS completion.

## Stop Conditions

Work stops before publication when any of these remains unknown or false:

- the fixed candidate aggregate gate passed;
- package contents and digests match the candidate;
- npm and Packagist ownership is verified;
- the Composer split repository exists with protected generated ownership;
- non-interactive publishing credentials are available;
- an immutable version can be created without replacing existing history;
- provenance and registry consumer probes can be recorded without exposing a
  credential.

## P1-PKG02-R01 Qualification Environment Retry

The first aggregate invocation stopped in the `scripts/check` preflight before
any check or test ran because `MYSQL_PORT` and `DB_PORT` were absent. This is an
environment-contract failure, not package or Runtime evidence. R01 authorizes
one new aggregate invocation against the unchanged package candidate with the
following complete isolated environment:

```bash
export COMPOSE_PROJECT_NAME=peanut-admin-pkg02-q01
export MYSQL_PORT=33412
export CACHE_PORT=36412
export BACKEND_PORT=38112
export FRONTEND_PORT=35212
export PEANUT_BROWSER_BACKEND_PORT=38212
export PEANUT_BROWSER_FRONTEND_PORT=35312
export MYSQL_DATABASE=peanut_admin_pkg02_qualification
export DB_HOST=127.0.0.1
export DB_PORT=33412
```

All six host ports were confirmed free before this contract. The repository
Compose file remains the sole service definition and starts MySQL 8.4.10 and
Valkey when their owning checks require them. `MYSQL_PORT` and `DB_PORT` must
remain equal; no existing application, shared database, fallback port, remote
database, or compatibility environment file may be used.

R01 may run only `./scripts/check` once with the exact environment above. It
may not change package source, tests, assertions, configuration, database
contracts, authorization, Tenant isolation, or qualification thresholds. A
failure receives one read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R02 Fixed PHP And Composer Toolchain Retry

R01 passed the documentation status checks and then stopped at the first PHP
autoload because the shell selected PHP 8.1.33 while the locked dependencies
require PHP 8.3 or newer. No PHP, Web, database, browser, recovery,
performance, or Starter test ran. The repository-required tools already exist
locally as PHP 8.3.24 and Composer 2.10.2.

R02 authorizes one new aggregate invocation against the unchanged package
candidate and unchanged R01 service environment. Before the invocation, the
owner may create only the temporary executable link
`/private/tmp/peanut-pkg02-toolchain/composer` pointing to
`/private/tmp/peanut-composer-2.10.2`. The command environment must additionally
contain:

```bash
export PATH=/private/tmp/peanut-pkg02-toolchain:/opt/homebrew/opt/php@8.3/bin:$PATH
export PEANUT_COMPOSER=/private/tmp/peanut-composer-2.10.2
```

Immediately before the aggregate invocation, `php -r 'echo PHP_VERSION;'` must
report `8.3.24`, `composer --version` must report `2.10.2`, and
`$PEANUT_COMPOSER --version` must report `2.10.2`. These are preflight facts,
not additional qualification runs.

R02 may then run only `./scripts/check` once with the complete R01 and R02
environment. It may not edit dependencies, locks, package source, tests,
assertions, configuration, qualification thresholds, or committed files. A
failure receives one read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R03 Documentation Runtime Count Remediation

R02 fixed the PHP and Composer toolchain, passed documentation status, Module
manifest verification, and the first two documentation PHPUnit groups, then
stopped in `scripts/verify-doc-examples`. Static diagnosis found one stale
Stage B assertion that still expects 75 P0 plus 4 P1 operations and 79 generated
routes. The authoritative Runtime ledger and generated route table both contain
75 P0 plus 64 P1 operations, for 139 routes.

Only `scripts/verify-doc-examples` may change. Its executable documentation
assertion and matching error message must replace the stale P1 and total-route
counts with `64` and `139`. It must retain the exact 75-operation P0 assertion,
per-P0 generated-route lookup, concrete-handler rejection, JSON parsing,
installation example, database isolation, Starter verification, and every
other check unchanged. Changing the Runtime ledger, OpenAPI, generated routes,
handlers, test selection, threshold logic, or accepting a range is forbidden.

After static review and `git diff --check`, R03 runs `./scripts/check-docs`
once with the complete R01/R02 environment. If it passes, qualification
continues once from `./scripts/check-dependency-decisions` through the remaining
commands in `scripts/check`, in the same order and environment, without
rerunning `check-doc-content-status` or `check-docs`. A failure receives one
read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R04 Starter Kernel Compatibility Version Remediation

R03 reached the internal Starter verification and stopped because the Starter
passed `KernelPackage::VERSION` (`0.1.0`) to the Module compiler while the
current Kernel compatibility protocol and Stage C Module manifests use
`1.0.0` / `^1.0`. Package release versions and the Module compatibility
protocol are separate version axes. A package version must never be used as a
substitute for the Host's declared compatibility version.

R04 may change only:

- `docs/status/p1-pkg02-alpha-publication-contract.md`;
- `scripts/verify-doc-examples` (the retained R03 count correction only);
- `starter/backend/config/modules.php`;
- `starter/backend/src/Module/ModuleRegistryFactory.php`;
- `starter/backend/tests/settings.php`;
- `starter/backend/tests/smoke.php`;
- `starter/backend/src/Modules/Example/Greeting/module.json`;
- `starter/backend/src/Modules/Peanut/Settings/module.json`;
- `starter/backend/src/Modules/Peanut/ReferenceCodes/module.json`;
- `starter/backend/src/Modules/Peanut/FileMedia/module.json`;
- `starter/backend/src/Modules/Peanut/TaskJob/module.json`;
- `starter/backend/src/Modules/Peanut/NotificationSms/module.json`;
- `tests/starter/assert-generated-starter.php`;
- `tools/project-generator/src/ProjectGenerator.php`;
- `tests/project-generator/static-contract.php`;
- `tests/project-generator/run.php`.

The Starter Module config and every generated project config must declare
`kernel_version` as `1.0.0`. The Starter compiler and the one direct compiler
fixture must consume that value. All Starter Module manifests must declare the
already documented `^1.0` compatibility protocol. Package smoke assertions
must continue to prove the independently versioned `0.1.0` package contents.
The generator and generated-Starter assertions must reject a missing or drifted
compatibility version.

R04 must not change the published package version, Module versions, Runtime
behavior, schemas, routes, operations, permissions, package manifests, release
candidate identity, test selection, or compatibility matcher. It must not
weaken a Module constraint to make compilation pass.

After static review and `git diff --check`, R04 runs `./scripts/check-docs`
once with the complete R01/R02 environment. If it passes, qualification
continues once from `./scripts/check-dependency-decisions` through the remaining
commands in `scripts/check`, in the same order and environment, without
rerunning `check-doc-content-status` or `check-docs`. A failure receives one
read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R05 Starter Admin Client Menu Remediation

R04 passed the Kernel compatibility boundary and then stopped when the Starter
compiler rejected `peanut.task-job.page` because it targeted the unregistered
`admin-web` Client. Read-only diagnosis confirmed that the Starter's declared
admin Client is `operations-web`: it is registered by backend auth, exposed by
the frontend Client registry, used by the earlier Starter Modules, and fixed by
the internal-Starter guide. Four later Module menus copied the reference Host's
`admin-web` key without registering or exposing that Client.

R05 retains the exact R03 and R04 changes above and may additionally change
only:

- `starter/backend/src/Modules/Peanut/TaskJob/Resources/menus.json`;
- `starter/backend/src/Modules/Peanut/NotificationSms/Resources/menus.json`;
- `starter/backend/src/Modules/Peanut/ImportExport/Resources/menus.json`;
- `starter/backend/src/Modules/Peanut/IntegrationSecurity/Resources/menus.json`;
- `tests/starter/assert-generated-starter.php`.

Each listed Tenant menu must target only `operations-web`. The generated
Starter assertion must prove that every configured Tenant Module menu targets
that registered admin Client. Adding `admin-web` to auth, frontend, or Module
configuration, retaining both keys, changing the platform Client, or weakening
unknown-Client rejection is forbidden. Project generation continues to replace
the template key with the application's explicitly selected admin Client.

After static review and `git diff --check`, R05 runs `./scripts/check-docs`
once with the complete R01/R02 environment. If it passes, qualification
continues once from `./scripts/check-dependency-decisions` through the remaining
commands in `scripts/check`, in the same order and environment, without
rerunning `check-doc-content-status` or `check-docs`. A failure receives one
read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R06 Consolidated Settings Install Root Remediation

R05 passed Module compilation and then stopped because the Starter Settings
fixture still required `settings/composer.json`. P1-PKG01 intentionally removed
all internal domain manifests: `peanut-admin/core/composer.json` is now the only
Composer manifest, and its PSR-4 map owns `settings/src/`. Read-only diagnosis
found no second Starter fixture that requires an internal domain manifest.

R06 retains the exact R03 through R05 changes and may additionally change only
`starter/backend/tests/settings.php`. The fixture must continue to prove that
Composer installed `peanut-admin/core` below the Starter vendor directory. It
must verify the public core manifest at the installed package root and the
Settings `src/Package.php` below that root. It must not require, recreate, or
accept an internal Settings manifest, path repository, copied source tree,
fallback root, or compatibility package.

After static review and `git diff --check`, R06 runs `./scripts/check-docs`
once with the complete R01/R02 environment. If it passes, qualification
continues once from `./scripts/check-dependency-decisions` through the remaining
commands in `scripts/check`, in the same order and environment, without
rerunning `check-doc-content-status` or `check-docs`. A failure receives one
read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R07 Structured Module Dependency Assertion Remediation

R06 passed the consolidated Settings install check and then stopped because the
Starter Import/Export fixture still expected the pre-schema string dependency
list. The committed manifest uses the current versioned dependency objects for
File/Media and Task/Job. Static review found no second Starter fixture with the
same stale assertion.

R07 retains the exact R03 through R06 changes and may additionally change only
`starter/backend/tests/import-export.php`. The assertion must require the exact
ordered dependency objects `peanut.file-media@^0.1` and
`peanut.task-job@^0.1`. It must not change the manifest, dependency versions,
Module compiler, schema, dependency resolution, or Tenant requirement list,
and it must not accept both the old and current shapes.

After static review and `git diff --check`, R07 runs `./scripts/check-docs`
once with the complete R01/R02 environment. If it passes, qualification
continues once from `./scripts/check-dependency-decisions` through the remaining
commands in `scripts/check`, in the same order and environment, without
rerunning `check-doc-content-status` or `check-docs`. A failure receives one
read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R08 Starter Web Async Response Remediation

R07 passed every PHP Starter integration and then stopped in the Starter Web
typecheck. Four Host adapters passed the `Promise<Response>` returned by their
required fetch function directly to local helpers typed for a resolved
`Response`. The public transport contracts already require the resulting
Promises and must not be widened or weakened.

R08 retains the exact R03 through R07 changes and may additionally change only:

- `starter/frontend/src/modules/peanut-file-media.ts`;
- `starter/frontend/src/modules/peanut-task-job.ts`;
- `starter/frontend/src/modules/peanut-import-export.ts`;
- `starter/frontend/src/modules/peanut-integration-security.ts`.

Each local result helper must accept `Promise<Response>`, await it exactly once,
and derive body, headers, and status from the resolved Response. R08 must not
change request URLs, methods, headers, credentials, abort signals, response
parsing, runtime permissions, public package types, or allow both synchronous
and asynchronous fetch contracts.

After static review and `git diff --check`, R08 runs `./scripts/check-docs`
once with the complete R01/R02 environment. If it passes, qualification
continues once from `./scripts/check-dependency-decisions` through the remaining
commands in `scripts/check`, in the same order and environment, without
rerunning `check-doc-content-status` or `check-docs`. A failure receives one
read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R09 Vue Relative Import Architecture Remediation

R08 completed `check-docs`, and the resumed dependency-decision gate passed.
The Runtime architecture gate then rejected four same-directory Vue component
imports. Its relative resolver recognizes TypeScript files and TypeScript index
files but not `.vue`, so a valid import such as `./FileAssetSelector.vue` is
reported as unresolved or cross-package before the existing package-root check
can classify it.

R09 may change only this contract and `scripts/check-architecture`. The
relative resolver must recognize an exact `.vue` target while retaining the
existing internal module roots, allowed dependency matrix, private-import
rejection, package-root containment check, TypeScript resolution, and cycle
analysis. It must not treat arbitrary extensions as source, scan generated
output, permit parent-directory escape, or remove any architecture check.

After static review and `git diff --check`, R09 reruns only
`PEANUT_RUNTIME_STAGE=runtime ./scripts/check-architecture` once with the
complete R01/R02 environment. If it passes, qualification continues from
`./scripts/check-openapi` through the remaining commands in `scripts/check`, in
the same order and environment. Passed documentation and dependency-decision
groups are not rerun. A failure receives one read-only diagnosis and stops
PKG02 qualification.

## P1-PKG02-R10 Explicit Vue Specifier Resolution Remediation

R09 retained the architecture boundary but appended `.vue` to an import that
already ended in `.vue`, producing a nonexistent `.vue.vue` candidate. The
read-only diagnosis confirmed that the original resolved path exists and is
inside its internal module root.

R10 retains the R09 write set. The resolver must use the original resolved path
only when the specifier explicitly ends in `.vue`; extensionless imports keep
the existing TypeScript and index candidates. Every selected candidate must be
an existing regular file. No other extension, directory, generated file,
parent escape, dependency edge, or public package import becomes allowed.

After static review and `git diff --check`, R10 reruns only
`PEANUT_RUNTIME_STAGE=runtime ./scripts/check-architecture` once with the
complete R01/R02 environment. If it passes, qualification continues from
`./scripts/check-openapi` through the remaining commands in `scripts/check`, in
the same order and environment. Passed groups are not rerun. A failure receives
one read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R11 Installed Core Migration Path Remediation

R10 resolved the Vue imports, after which the architecture gate rejected five
Host references to monorepo `packages/php/*` migration directories in
`UpgradeWorkflow`. The workflow already resolves its Kernel schema through
Composer `InstalledVersions`; installed applications must use that same public
package root for core migrations instead of requiring a source checkout.

R11 retains the R09/R10 write set and may additionally change only
`backend/app/command/UpgradeWorkflow.php`. Kernel and DataPermission migration
paths must resolve from their package names through the existing fail-closed
`packagePath()` helper and then append the corresponding internal directory.
All current, run, and individual-migration paths must use those helpers.

R11 must not accept the Host repository root as a fallback package root, add a
constructor override, change migration ordering or ledger names, alter release
verification, weaken unavailable-package failure, change a package name, or
relax the architecture prohibition on Host filesystem references to
`packages/php` or `packages/web`.

After static review and `git diff --check`, R11 reruns only
`PEANUT_RUNTIME_STAGE=runtime ./scripts/check-architecture` once with the
complete R01/R02 environment. If it passes, qualification continues from
`./scripts/check-openapi` through the remaining commands in `scripts/check`, in
the same order and environment. Passed groups are not rerun. A failure receives
one read-only diagnosis and stops PKG02 qualification.

## P1-PKG02-R12 Public Package Documentation Remediation

R11 removed the Host's monorepo package paths, after which the architecture
gate found an internal project name in two README files included in the
Composer projection. Public package documentation must describe only generic
downstream consumption boundaries.

R12 retains the R09 through R11 changes and may additionally change only:

- `packages/php/import-export/README.md`;
- `packages/php/integration-security/README.md`.

The two references must use generic downstream-consumption wording without
changing capability, ownership, qualification, release, or stop-line meaning.
R12 must not exclude README files from the package, weaken the public-content
gate, rename a product in code, or alter Runtime behavior.

Because these README files are part of `peanut-admin/core`, the resulting R12
commit supersedes `deb85a7e3e65b4d323a6eff4c694724a1fd23338` as package source.
A separate planning commit must record the exact resulting 40-character commit
before any qualification resumes. No prior fixed-candidate qualification result
is evidence for the new projection.

## P1-PKG02-R13 Replacement Candidate Qualification

R12 produced the clean replacement source commit
`9c91228e02c34e550daa4f1bd869d01f5269333b`. Relative to the former package
projection, its Composer content changes only the two public README references;
the same commit also contains the qualified Starter/Host and architecture-gate
repairs recorded by R03 through R11.

R13 may change only this contract and `docs/status/index.md` to record the
replacement candidate. With the complete R01/R02 environment, the qualification
owner runs `./scripts/check` exactly once against the planning commit above that
unchanged source candidate. Previous partial or complete results are historical
diagnostics and are not carried forward as fixed-candidate evidence.

If the aggregate gate passes, package-content inspection proceeds against the
exact PHP and Web projections from the replacement candidate. If it fails, the
owner performs one read-only diagnosis and stops. Any source repair requires a
new remediation contract and, when the package projection changes, another
explicit candidate rollover.

## P1-PKG02-R14 Supply-Chain Audit Diagnosis

R13 passed documentation, dependency, architecture, OpenAPI, Runtime coverage,
Starter build, and production-build gates, then stopped at the root pnpm audit
inside `scripts/check-supply-chain`. Composer audit passed. The required single
read-only diagnosis recorded six high-severity advisories and no critical
advisory:

- `js-yaml 4.2.0` is affected below `4.3.1`;
- `brace-expansion 2.1.2` is affected below `2.1.4`;
- `brace-expansion 5.0.7` is affected below `5.0.9`.

All three are transitive development-tool dependencies in the root workspace
lock. The supply-chain gate audits only the root workspace; it does not audit
the independently locked generated Starter. R14 performs no committed write
and supplies no qualification evidence.

## P1-PKG02-R15 Transitive Advisory Remediation

R15 may change only:

- this contract;
- `pnpm-workspace.yaml`;
- `pnpm-lock.yaml`.

The root workspace must add exact major-scoped overrides resolving
`js-yaml@4` to `4.3.1`, `brace-expansion@2` to `2.1.4`, and
`brace-expansion@5` to `5.0.9`. The lock must be regenerated with the declared
pnpm 11.13.0 toolchain and must remove only the three vulnerable resolutions
identified by R14 in favor of those exact safe versions.

R15 must not change a direct dependency manifest, Starter lock, package public
manifest or export, audit threshold, supply-chain script, license gate, Runtime
source, schema, route, permission, Tenant behavior, or user-visible behavior.
It must not use an ignore rule, advisory exception, patched fork, compatibility
package, or broader dependency upgrade.

After static review and `git diff --check`, R15 runs `pnpm install
--lockfile-only --frozen-lockfile=false` once, followed by `pnpm audit
--audit-level high --json` once. If the audit has zero high and critical
advisories, the resulting clean source commit becomes a new package candidate.
A separate planning commit must replace the exact 40-character
`package_candidate_commit` before the aggregate `./scripts/check` is run once
against that unchanged candidate. Previous R13 partial results are diagnostics
only and are not carried forward as fixed-candidate qualification evidence.

## P1-PKG02-R16 Advisory-Remediated Candidate Qualification

R15 produced the clean replacement source commit
`cd6207f28532dec305214b38c662a3e891f7692a`. Its only changes from the R13
candidate are the R14/R15 contract record, three exact root workspace
overrides, and their corresponding root lock resolutions. The Composer and npm
public package projections are otherwise unchanged.

R16 may change only this contract and `docs/status/index.md` to record the new
candidate. With the complete R01/R02 environment, the qualification owner runs
`./scripts/check` exactly once against the planning commit above the unchanged
R15 source candidate. Previous partial results are historical diagnostics and
are not carried forward as fixed-candidate evidence.

If the aggregate gate passes, package-content inspection proceeds against the
exact PHP and Web projections from this candidate. If it fails, the owner
performs one read-only diagnosis and stops. Any source repair requires a new
remediation contract and candidate rollover.

## P1-PKG02-R32 Browser Fixture Capability And Secret Remediation

R31 started and health-checked PHP on the accepted qualification port. Forty
desktop/mobile browser tests passed and six failed. Trace inspection proved
that all four Reference Codes failures entered the configured Runtime path:
the authenticated context included the `peanut.reference-codes` Module, but
the browser fixture role omitted both `peanut.reference-codes.read` and
`peanut.reference-codes.manage`. The route guard therefore rejected the page
before its component or transport loaded. The two Settings failures reached
the real API and returned `SETTING_SECRET_UNAVAILABLE` because the browser
test process did not provide the Settings Sodium keyring environment.

R32 retains R31 and may additionally change only:

- `frontend/tests/fixtures/full-stack-setup.php`;
- `scripts/test-browser`.

The full-stack fixture must grant the two accepted Reference Codes permissions
to the existing Alpha and Beta browser-owner roles. It must not bypass the
route guard, widen a production role, alter Module activation, or change any
API, menu, permission catalog, route, or product behavior.

`scripts/test-browser` must generate one fresh XChaCha20-Poly1305 key with the
existing PHP Sodium runtime for each invocation. It must export that key as a
single-entry JSON keyring through `PEANUT_SETTINGS_SECRET_KEYS` and select the
same entry through `PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID` before fixture setup
and Playwright start. The key must remain process-local and unlogged. R32 must
not add a production fallback, fixed key, committed secret, weaker cipher, or
Settings behavior change.

After static review and `git diff --check`, the owner runs
`./scripts/test-browser` once with the complete R01/R02 environment. If it
passes, qualification continues once through recovery, performance, internal
Starter verification, workspace checks, and the remaining `scripts/check`
guards in their original order. Previously passed groups remain authoritative
and must not be rerun. A failure receives one read-only diagnosis and stops.

R32 resolved both Settings failures and allowed every Reference Codes route to
load through the real configured Tenant Client. Forty-two browser tests passed
and the four Reference Codes variants reached `GET
/api/v1/reference-code-sets`, which returned `200` with `items: []`. The
declaring infrastructure Module correctly owns an empty
`reference-code-sets.json`; no application Module in the reference profile
declares a set, while the workflow test requires one selectable declaration.

## P1-PKG02-R33 Reference Application Set Declaration

R33 retains the exact R32 fixture permissions and process-local Settings
keyring and may additionally change only:

- `backend/app/Modules/Example/Reference/module.json`;
- new `backend/app/Modules/Example/Reference/Resources/reference-code-sets.json`.

The fictional `example.reference` Module must declare its trusted
`reference_code_sets` resource and exactly one neutral example set containing
only the required `key`, `name`, and `description` fields. The declaration
exists to make the reference application exercise the reusable Reference Codes
workflow; it contains no code value, Tenant identifier, permission, business
state, default entry, or application rule. The reusable
`peanut.reference-codes` Module must retain its empty definition resource.

R33 must not change the loader, registry, synchronization, API, schema,
migration, permission, browser assertion, route, package manifest, public
export, or production fallback behavior. After static review and
`git diff --check`, the owner runs `./scripts/test-browser` once with the
complete R01/R02 environment. If it passes, all four R32/R33 source files form
one clean replacement candidate commit; a separate planning commit records its
exact hash before qualification resumes through recovery and the remaining
unexecuted groups. A failure receives one read-only diagnosis and stops.

## P1-PKG02-R34 Reference Codes Dialog Viewport Remediation

R33 loaded the fictional reference set, opened the real create workflow, and
left forty-two browser tests passing. The four desktop/mobile Reference Codes
variants then failed the same dialog geometry assertion. Trace measurement
proved that the form and every interactive control were inside the viewport
and did not overlap. The element carrying `role="dialog"` was Element Plus's
full-viewport `.el-overlay-dialog`; while the dialog was already visible, the
default `dialog-fade` enter transition still translated that element 20 pixels
above the viewport. Its measured top was therefore `-20` until the decorative
transition completed.

R34 retains the exact R32 fixture permissions and process-local Settings
keyring plus the R33 reference application declaration. It may additionally
change only:

- `packages/web/reference-codes/src/ReferenceCodesPage.vue`.

All three Reference Codes `ElDialog` instances must use the Element Plus
`transition` contract with CSS transitions disabled. Their existing titles,
widths, viewport max-height, scroll behavior, forms, controls, close behavior,
focus behavior, pending states, validation, and Runtime calls must remain
unchanged. R34 must not change the browser assertion or timing, add a wait,
alter global Element Plus behavior, change another page, or weaken viewport or
overlap acceptance.

After static review and `git diff --check`, the owner runs
`./scripts/test-browser` once with the complete R01/R02 environment. If it
passes, all four retained R32/R33 files and the R34 page fix form one clean
replacement candidate commit; a separate planning commit records its exact
hash before qualification resumes through recovery and the remaining
unexecuted groups. A failure receives one read-only diagnosis and stops.

## P1-PKG02-R35 Created Entry Snapshot Advancement

R34 removed the transient dialog geometry failure, and all four Reference
Codes variants completed validation and received `201` from the real create
endpoint. The following list refresh remained fixed at the deliberately
historical `2000-01-01T00:00:00.000Z` snapshot and correctly returned no
entries. The created entry has a backdated effective version but a 2026 system
creation time; the existing bitemporal query correctly hides an identity before
its actual creation. The page therefore waited for a row that cannot exist in
that historical snapshot. Forty-two other browser tests passed.

R35 retains the exact R32 through R34 source changes and may additionally
change only:

- `packages/web/reference-codes/src/runtime.ts`.

After a create response is parsed and its entry identity is validated, the
Runtime must advance `state.asOf` to the authoritative `createdAt` returned by
that response before it reloads the collection. It must continue using the
existing parsed instant contract and existing collection reload. This moves
the operator from the historical snapshot to the first snapshot in which the
new identity exists while preserving the submitted effective interval,
including backdated or future-effective versions.

R35 must not change repository or query bitemporal semantics, derive the new
snapshot from the client clock, alter create input, response parsing, API,
schema, transaction, authorization, idempotency, browser assertion, timeout,
or test data, or add a compatibility path. Append, replace, retire, stale
reload, and ordinary filter behavior remain unchanged.

After static review and `git diff --check`, the owner runs
`./scripts/test-browser` once with the complete R01/R02 environment. If it
passes, all retained R32-R35 source files form one clean replacement candidate
commit; a separate planning commit records its exact hash before qualification
resumes through recovery and the remaining unexecuted groups. A failure
receives one read-only diagnosis and stops.

## P1-PKG02-R36 Historical Retirement Response Parsing

R35 advanced the create snapshot and all four real Reference Codes variants
completed create, append, competing update, stale reload, replacement, and
retirement. The post-retirement refresh intentionally retained the snapshot
captured immediately before retirement. The backend correctly returned the
entry as active at that snapshot while retaining its later `retired_at` audit
instant. The Web response parser incorrectly required every active snapshot to
have a null `retired_at`, rejected the valid bitemporal response, and replaced
the collection with a protocol error.

One unrelated mock-backed desktop test failed because its hashed JavaScript
chunk request received Chromium `net::ERR_NETWORK_CHANGED`. The same test
passed in the mobile project and in prior qualification runs. R36 records this
as a browser-environment interruption and authorizes no product, fixture,
assertion, timeout, retry-policy, or network-handling change for it.

R36 retains the exact R32 through R35 source changes and may additionally
change only:

- `packages/web/reference-codes/src/contracts.ts`;
- `packages/web/reference-codes/tests/contracts.spec.ts`.

The shared entry parser must continue to require a non-null `retiredAt` for a
retired lifecycle. When parsing a list, it must normalize the envelope `as_of`
once and validate lifecycle relative to that snapshot: a non-null retirement
at or before `as_of` is retired, while a null or later retirement is active.
The parser must accept an active historical entry that carries a later
retirement audit instant. The focused contract test must prove that accepted
case and retain fail-closed coverage for inconsistent lifecycle/timestamp
combinations.

R36 must not remove `retired_at`, hide it from historical responses, change
the backend bitemporal query, mutation response, API, schema, authorization,
filters, browser workflow, or add a compatibility shape. Direct entry parsing
must still reject a retired lifecycle without a retirement instant.

After static review and `git diff --check`, the owner runs the focused
`packages/web/reference-codes/tests/contracts.spec.ts` Vitest file once and
then `./scripts/test-browser` once with the complete R01/R02 environment. If
both pass, all retained R32-R36 source and test files form one clean
replacement candidate commit; a separate planning commit records its exact
hash before qualification resumes through recovery and the remaining
unexecuted groups. A failure receives one read-only diagnosis and stops.

## P1-PKG02-R26 Explicit Authentication PDO Injection

R25 passed the package, supply-chain, unit, and Web groups and completed the
integration group. Nine HTTP-fixture errors shared one cause: the fixtures
create authentication before the ThinkPHP application registers `AppService`,
so the Runtime factories attempted to reflectively construct PDO. The same
group also proved the Kernel ledger now contains exactly 40 migrations rather
than the stale expected 38.

R26 may change only:

- this contract;
- `backend/app/middleware/TenantAuthRuntimeFactory.php`;
- `backend/app/middleware/PlatformAuthRuntimeFactory.php`;
- the four HTTP integration fixtures that call those factories before App
  startup;
- `packages/php/kernel/tests/Integration/Schema/KernelMigrationTest.php`.

Each factory may accept an explicit nullable PDO dependency. When absent it
must retain the existing fail-closed ThinkPHP container lookup; it must not
construct a fallback connection or read additional environment configuration.
The four fixtures must pass their already isolated PDO explicitly. Production
controllers and middleware continue to call the default container-backed path.
The Kernel migration test must require exactly 40 ledger rows while retaining
its complete table, index, repeat-install, and rollback checks.

R26 must not add a compatibility route or field, change authentication,
authorization, transaction, schema, migration, or connection behavior, or
weaken any assertion. After `git diff --check`, the owner runs the four focused
HTTP integration files and Kernel migration test once with the complete
R01/R02 environment. A passing source commit becomes the next package
candidate and a separate planning commit records its exact hash.

R26 removed all nine pre-application PDO construction errors and the corrected
Kernel migration assertion passed. Seven real HTTP requests still returned 500
because the application path contains `AppService` but no ThinkPHP
`app/service.php` registration file. ThinkPHP therefore never executes the
existing PDO binding when the HTTP application loads, and the middleware's
default container-backed factory call again reaches reflective PDO
construction.

## P1-PKG02-R27 ThinkPHP Application Service Registration

R27 retains the exact R26 changes and may additionally add only
`backend/app/service.php`. The file must use ThinkPHP's standard application
service list and register only `PeanutAdmin\App\AppService`. The existing
`AppService` remains the sole owner of the PDO binding and production Runtime
factories must continue to use the container-backed path.

R27 must not construct a fallback PDO, duplicate database environment parsing,
change middleware, controller, route, authentication, authorization, schema,
migration, or error behavior, or add a test-only application service. After
`git diff --check`, the owner reruns only the four focused HTTP integration
files and Kernel migration test once with the complete R01/R02 environment. A
passing source commit becomes the next package candidate and a separate
planning commit records its exact hash before one new aggregate qualification
run.

R27 removed every HTTP 500 and 10 of the 11 focused tests passed. The remaining
effective-access test rejected the substring `session` anywhere in the encoded
response, but the accepted Integration Security catalog now legitimately
publishes `session-read` and `session-revoke` operation names. The response did
not expose a session field or credential.

## P1-PKG02-R28 Exact Redacted-Field Assertion

R28 retains R26/R27 and may additionally change only
`backend/tests/Integration/EffectiveAccessPreviewHttpIntegrationTest.php`. Its
redaction assertion must continue to reject every existing forbidden field,
including `session`, as an exact JSON object key rather than rejecting the same
text inside an unrelated public catalog value. All response, authorization,
data-scope, audit, cross-Tenant, validation, and no-security-event assertions
must remain unchanged.

R28 must not allow a forbidden response key, remove a forbidden name, alter
production code, filter an accepted operation, or weaken any other assertion.
After `git diff --check`, the owner reruns the same failed five-file focused
group once with the complete R01/R02 environment. A passing source commit
becomes the next package candidate and a separate planning commit records its
exact hash before one new aggregate qualification run.

## P1-PKG02-R29 Registered-Service Candidate Qualification

R26 through R28 produced the clean replacement source commit
`f9a8fe38c18db2637fb2ac8f4cfad02332209403`. The four HTTP integration files
and Kernel migration test passed 11 tests and 271 assertions. The replacement
registers the already defined application PDO service, keeps production
authentication on the ThinkPHP container path, and changes no public package
manifest, version, namespace, export, schema, migration, route, or operation.

R29 may change only this contract and `docs/status/index.md` to record the new
candidate. With the complete R01/R02 environment, the qualification owner runs
`./scripts/check` exactly once against the planning commit above the unchanged
source candidate. Previous partial results remain diagnostics only.

If the aggregate gate passes, package-content inspection proceeds against the
exact PHP and Web projections from this candidate. If it fails, the owner
performs one read-only diagnosis and stops. Any source repair requires a new
remediation contract and candidate rollover.

R29 passed documentation, dependency, architecture, OpenAPI, Runtime coverage,
supply-chain, PHP unit, Web unit, MySQL integration, and PHP security groups.
The browser group stopped before executing any browser assertion because the
local Playwright cache lacked the Chromium Headless Shell revision required by
the locked `@playwright/test` 1.61.1. The cache contained only unrelated older
and newer revisions; the package manifest and lock remained unchanged.

## P1-PKG02-R30 Locked Playwright Browser Environment Retry

R30 changes no committed file other than this contract. The qualification
owner may run `pnpm exec playwright install chromium` once to install the exact
browser revision selected by the committed lock. It must not change a package
manifest, dependency lock, Playwright version, browser project, test, source,
threshold, or qualification assertion.

After installation, qualification resumes once from `./scripts/test-browser`
through the remaining `scripts/check` commands in their original order:
recovery, performance, internal Starter verification, workspace checks, the
fixed license hash, private-path/product-content guards, required/deferred
directory checks, and `git diff --check`. Previously passed groups must not be
rerun. Any resumed-group failure receives one read-only diagnosis and stops.

R30 installed the exact locked Chromium revision and browser execution began.
Thirty mock-backed desktop/mobile tests passed. All 16 real-backend tests
failed at their first API request because `playwright.config.ts` started and
health-checked PHP on a hard-coded port 4180 while the accepted qualification
environment and the full-stack Vite proxy used
`PEANUT_BROWSER_BACKEND_PORT=38212`. The resulting proxy connection refusal is
test-service wiring, not a product response or browser assertion failure.

## P1-PKG02-R31 Playwright Backend Port Wiring Remediation

R31 may change only this contract and `playwright.config.ts`. The Playwright
configuration must parse and validate `PEANUT_BROWSER_BACKEND_PORT` with the
same integer range contract as the frontend port, retaining 4180 only as the
local default. The PHP web-server command and its health URL must both use the
resolved backend port so they match the existing full-stack Vite proxy.

R31 must not change the qualification environment, Vite proxy, browser
projects, test selection, retry/worker/skip policy, application source, API,
fixture data, manifest, lock, or assertion. After `git diff --check`, the owner
runs `./scripts/test-browser` once with the complete R01/R02 environment. If it
passes, qualification continues once through recovery, performance, internal
Starter verification, workspace checks, and the remaining `scripts/check`
guards in their original order. Previously passed groups remain authoritative
and must not be rerun. A failure receives one read-only diagnosis and stops.

## P1-PKG02-R20 Old-Lock Upgrade Test Process Isolation

R19 passed supply-chain qualification, PHP unit tests, and Web tests, then the
integration group terminated while the old-lock upgrade compatibility test
scanned a temporary clone. The shared PHPUnit process had already loaded the
current Host's global `CreateExampleTargets` migration class; loading the same
class name from the clone caused a duplicate-class fatal before PHPUnit could
finish reporting the group.

R20 may change only this contract and
`backend/tests/Upgrade/SettingsUpgradeTest.php`. The old-lock compatibility
test method must run in a separate PHPUnit process with parent global-state
preservation disabled. Its existing database setup, immutable old commit/tree
assertions, upgrade, compatibility, backup, restore, and cleanup behavior must
remain unchanged.

R20 must not rename or namespace a migration, alter migration discovery or
execution, suppress PHP errors, split the aggregate gate, change production
upgrade code, or weaken any assertion. After `git diff --check`, the owner runs
only that focused integration test once with the complete R01/R02 environment.
A passing clean source commit becomes the next package candidate; a separate
planning commit records its exact hash before one new aggregate qualification
run.

R20 removed the duplicate-class fatal. The focused test then reached its first
upgrade-result assertion and reported 13 applied module migrations instead of
the stale expected 8. Static inventory reconciliation proved that the fixed old
lock owns exactly 3 module migrations and the current candidate owns exactly
16, so both the current-install expectation 11 and upgrade-delta expectation 8
predate five already accepted Module migrations.

## P1-PKG02-R21 Current Module Migration Count Remediation

R21 retains the R20 process-isolation change and may additionally change only:

- `backend/tests/Install/InstallWorkflowIntegrationTest.php`;
- `backend/tests/Upgrade/UpgradeWorkflowIntegrationTest.php`;
- `backend/tests/Upgrade/SettingsUpgradeTest.php`.

Every exact current module-migration count in those tests must be 16. Every
exact upgrade delta from the immutable old-lock count 3 must be 13. The old
count, zero-repeat counts, concrete Settings migration keys, installation
state, schema signatures, backup, restore, rollback, compatibility, and
database-cleanup assertions must remain unchanged.

R21 must not calculate the expected value from production output, accept a
range, change a migration or manifest, alter migration discovery or execution,
or modify production code. After `git diff --check`, the owner runs the three
focused install/upgrade integration files once with the complete R01/R02
environment. A passing clean source commit becomes the next package candidate;
a separate planning commit records its exact hash before one new aggregate
qualification run.

R21 reached the corrected counts, but the focused group terminated when
`UpgradeWorkflowIntegrationTest` loaded the migration classes from its
class-owned temporary clone after another focused test had loaded the current
tree. This class creates one clone in `setUpBeforeClass()` and intentionally
reuses it across all of its methods, so method-level isolation is not the
correct boundary.

## P1-PKG02-R22 Upgrade Integration Class Process Isolation

R22 retains R20/R21 and may additionally change only
`backend/tests/Upgrade/UpgradeWorkflowIntegrationTest.php`. The complete class
must run in one separate PHPUnit process with parent global-state preservation
disabled. Its single class-level clone, per-test database reset, old-release
fixture, ordering, idempotency, checksum, locking, definition, backup, and
cleanup assertions must remain unchanged.

R22 must not isolate individual methods, create multiple clones, rename a
migration, change production code, suppress errors, or weaken an assertion.
After `git diff --check`, the owner reruns the same three focused
install/upgrade integration files once with the complete R01/R02 environment.
A passing clean source commit becomes the next package candidate; a separate
planning commit records its exact hash before one new aggregate qualification
run.

R22 removed the clone-class fatal and exposed three independent stale fixture
assumptions: the accepted Module set now contains 10 rather than 6 Modules; the
old-release installer used the current Composer autoloader without prepending
the immutable old source paths; and the old-table preservation assertion
compared newly created Module tables as though they existed before upgrade.

## P1-PKG02-R23 Expanded Module Upgrade Fixture Remediation

R23 retains R20 through R22 and may change only this contract plus the same
three focused test files authorized by R21. Their exact expected Module order
and installation counts must include the 10 current Modules. The immutable
old-release install subprocess must prepend the old App, Kernel, and Data
Permission PSR-4 roots and reload the corresponding Composer install paths
before it instantiates the old `UpgradeWorkflow`; framework dependencies may
continue to come from the current vendor directory.

The Settings old-lock test must compare post-upgrade structure signatures only
for tables that existed before upgrade. It must continue to prove every such
table is unchanged, every expected Settings table and migration was added, and
the restored database contains exactly the old table set. Newly added Module
tables must not be treated as mutations of old tables.

R23 must not load current application or core classes as the old release,
exclude an old table from comparison, alter an accepted Module, migration,
manifest, package, production autoloader, or upgrade workflow, or weaken backup
and rollback assertions. After `git diff --check`, the owner reruns the same
three focused install/upgrade integration files once with the complete R01/R02
environment. A passing source commit becomes the next package candidate and a
separate planning commit records its exact hash.

R23 resolved Module order and old-table preservation. The focused group then
showed that the remaining authorization/menu counts still describe six
Modules, the immutable old `UpgradeWorkflow` exposes `run()` rather than the
current `installEmptyDatabase()`, and the old health endpoint correctly reports
`degraded` when its non-critical cache probe is absent. An isolated current
install fixed the authoritative catalog counts at 82 permissions, 10 protected
resources, 35 operations, 27 menus, 19 Tenant menus, and 8 platform menus.

## P1-PKG02-R24 Catalog Count And Integration Cache Remediation

R24 retains R20 through R23 and may additionally change only
`scripts/test-integration`. The focused current-install assertions must use the
exact catalog counts above while retaining the existing exact target-type,
operation-target, condition, and condition-operation counts. The old-release
subprocess must call its own `UpgradeWorkflow::run()` after the R23 old-source
autoload is installed.

The integration entry point must require `CACHE_PORT`, start both `mysql` and
`cache`, and wait for an exact Valkey `PONG` before PHPUnit starts. It must keep
the existing MySQL readiness and test selection unchanged. Old-lock health
must remain exactly `healthy`; accepting `degraded`, skipping cache, relying on
an already-running container, or changing production health semantics is
forbidden.

R24 must not alter a production catalog, Module, migration, route, health
service, or upgrade workflow, and must not weaken an exact assertion. After
`git diff --check`, the owner starts the two declared services through the
updated entry-point contract and reruns the same three focused test files once.
A passing source commit becomes the next package candidate and a separate
planning commit records its exact hash.

## P1-PKG02-R25 Upgrade-Fixture Candidate Qualification

R20 through R24 produced the clean replacement source commit
`344d18c150798ccf0800e682855f91cfbdd3fc53`. The three focused install/upgrade
files passed 12 tests and 666 assertions against healthy isolated MySQL and
Valkey services. The commit changes qualification fixtures and their service
entry point only; public package projections and production Runtime behavior
remain unchanged.

R25 may change only this contract and `docs/status/index.md` to record the new
candidate. With the complete R01/R02 environment, the qualification owner runs
`./scripts/check` exactly once against the planning commit above the unchanged
source candidate. Previous partial results remain diagnostics only.

If the aggregate gate passes, package-content inspection proceeds against the
exact PHP and Web projections from this candidate. If it fails, the owner
performs one read-only diagnosis and stops. Any source repair requires a new
remediation contract and candidate rollover.

## P1-PKG02-R17 Generated License Inventory Remediation

R16 passed the Composer and pnpm security audits, then stopped because the
generated third-party license inventory still recorded the three transitive
versions replaced by R15. The existing generator and accepted-license policy
are correct; only their committed output is stale.

R17 may change only this contract and
`docs/reference/third-party-licenses.generated.md`. The owner runs
`./scripts/check-third-party-licenses --write` once with the R01/R02 toolchain.
The generated diff must replace only `js-yaml 4.2.0`,
`brace-expansion 2.1.2`, and `brace-expansion 5.0.7` with the exact R15
versions while preserving package count, ecosystem, package names, SPDX
licenses, ordering, and generated-file header.

R17 must not change the generator, accepted-license list, dependency manifest,
lock, override, audit threshold, package projection, Runtime behavior, or any
manually maintained documentation. After `git diff --check`, the owner runs
`./scripts/check-third-party-licenses` once. A passing clean source commit
becomes the next package candidate; a separate planning commit must record its
exact 40-character hash before one new aggregate qualification run.

R17 did not produce a candidate. Regeneration added every installed Composer
development dependency because the existing generator reads the mutable
`vendor/` installation through `composer licenses`; the prior committed
inventory contained no Composer row. The generated output therefore failed
R17's preserved-package-count acceptance condition and remains uncommitted.

## P1-PKG02-R18 Lock-Derived License Inventory Remediation

R18 may change only:

- this contract;
- `scripts/check-third-party-licenses`;
- `tests/supply-chain/SupplyChainQualificationContractTest.php`;
- `docs/reference/third-party-licenses.generated.md`.

The generator must derive Composer rows directly from the committed
`composer.lock` `packages` and `packages-dev` arrays. It must reject a missing
or malformed package name, version, or license and retain the existing SPDX
allowlist, pnpm inventory path, deterministic ordering, deduplication, and
generated-file format. It must not inspect `vendor/`, invoke `composer
licenses`, omit development dependencies, or infer a license.

The supply-chain contract test must prove that the generator names
`composer.lock` and no longer invokes `composer licenses`. The regenerated
inventory must contain the complete lock-derived Composer rows plus the R15
pnpm versions. R18 must not change either dependency lock, manifest, override,
accepted-license list, security audit, package projection, or Runtime behavior.

After `git diff --check`, R18 runs the focused supply-chain PHPUnit group once
and `./scripts/check-third-party-licenses` once. A passing clean source commit
becomes the next package candidate; a separate planning commit records its
exact 40-character hash before one new aggregate qualification run.

## P1-PKG02-R19 Lock-Derived Candidate Qualification

R18 produced the clean replacement source commit
`5a34f54829c3dd9e04365f3c65c5b5a6cc23daee`. Relative to R15, its package
projections remain unchanged; it only makes release license evidence complete
and deterministic and records the failed R17 diagnosis.

R19 may change only this contract and `docs/status/index.md` to record the new
candidate. With the complete R01/R02 environment, the qualification owner runs
`./scripts/check` exactly once against the planning commit above the unchanged
R18 source candidate. Previous partial results remain diagnostics only.

If the aggregate gate passes, package-content inspection proceeds against the
exact PHP and Web projections from this candidate. If it fails, the owner
performs one read-only diagnosis and stops. Any source repair requires a new
remediation contract and candidate rollover.
