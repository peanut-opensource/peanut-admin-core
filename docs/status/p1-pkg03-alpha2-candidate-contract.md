# P1-PKG03 Alpha.2 Candidate Contract

## Status

```text
state: accepted
prerequisite_commit: fea3098d027acbea416610f207a83421829449a3
composer_package: peanut-admin/core@0.1.0-alpha.2
npm_package: @peanut-admin/admin@0.1.0-alpha.2
npm_dist_tag: alpha
immutable_tag: v0.1.0-alpha.2
qualification_status: pending
publication_authorized: false
```

## Objective

Replace the unpublished `0.1.0-alpha.1` publication candidate with one fixed
`0.1.0-alpha.2` candidate that includes the two public package boundaries, the
qualified PHP and Web override Host chains, and the UI-neutral `./client`,
`./client/nuxt`, and `./client/uniapp` subpaths.

`0.1.0-alpha.1` remains historical qualification evidence and is not published
first. No registry version, split repository, tag, Release, application
migration, or downstream-consumption decision is created by this task.

## Package Boundary

The candidate still contains exactly two public runtime packages:

- Composer `peanut-admin/core`;
- npm `@peanut-admin/admin`.

Domain directories and client subpaths are internal module or entry boundaries,
not independently versioned packages. No alias, compatibility package,
metapackage, `replace`, `provide`, copied source package, or third client package
may be added.

The version change must not alter Runtime behavior, PHP namespaces, Web public
contracts except for the already committed client subpaths, dependencies,
schemas, migrations, OpenAPI, authorization, Tenant isolation, audit,
idempotency, status transitions, UI, or user-visible results.

## Version Candidate Write Set

After this contract is committed independently, the version-candidate task may
change only:

- `packages/php/composer.json`;
- `packages/web/package.json`;
- `composer.json` and `composer.lock`;
- `backend/composer.json`;
- `starter/backend/composer.json` and `starter/backend/composer.lock`;
- `package.json` and `pnpm-lock.yaml`;
- `starter/frontend/package.json` and `starter/pnpm-lock.yaml`;
- `scripts/check-workspace`, only to update Alpha.2 version assertions and add
  `./client`, `./client/nuxt`, and `./client/uniapp` to its exact expected Web
  export list, and to point lock verification at the new
  `p1-pkg03-lock-evidence.json` record;
- `tests/starter/assert-generated-starter.php`;
- `README.md` and `starter/README.md` for current candidate wording;
- `docs/status/index.md` for current candidate wording;
- `docs/reference/third-party-licenses.generated.md`, generated only by its
  existing writer;
- new `docs/decisions/dependencies/p1-pkg03-lock-evidence.json`;
- `docs/content-status.json` must not register the JSON evidence because the
  documentation manifest covers Markdown documents only.

Historical P1-PKG01/P1-PKG02 contracts, qualifications, lock evidence, override
contracts, and their fixed hashes must not be rewritten. Package source,
dependencies, exports, lock resolution other than the first-party version,
tests, scripts other than the exact version and export assertions above, and
publication records must not change.

The integration owner updates structured manifests first, regenerates the four
locks with the existing package managers, regenerates the license inventory,
records exact lock evidence, verifies the exact write set, and runs:

```bash
./scripts/check-workspace
```

This group runs once. A failure receives one read-only diagnosis and one static
batch correction inside this write set; only the failed group may run once
more. The passing clean commit becomes the fixed Alpha.2 candidate.

## PHPStan Remediation Slice

The first Alpha.2 workspace gate reached PHPStan after all 582 PHPUnit tests
passed and exposed thirteen existing static-analysis errors in the qualified
override Host chain. Before the version candidate can be fixed, one separate
remediation commit may change only:

- `backend/app/AppService.php`;
- `backend/app/notification/NotificationRuntimeFactory.php`;
- `packages/php/kernel/src/Override/ServiceOverrideRegistry.php`;
- `packages/php/kernel/tests/Unit/Override/ServiceOverrideRegistryTest.php`.

The remediation may narrow and validate configuration values, preserve the
Registry's fail-closed runtime validation for untrusted arrays, resolve the
Host container through an analyzable boundary, and make negative-test fixtures
explicitly typed. It must not change service keys, contract versions, default
or application resolution precedence, exception codes, container bindings,
notification behavior, package exports, dependencies, schemas, or public API.

After one static batch correction, only the failed PHPStan group runs once:

```bash
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

The remediation owner then performs an exact write-set check and
`git diff --check`, commits the four-file correction independently, and returns
the unchanged version-candidate write set to its workspace gate. A second
PHPStan failure blocks Alpha.2; it must not be suppressed with ignores,
baselines, assertions, casts, or widened types.

## PHP CS Fixer Remediation Slice

The next Alpha.2 workspace gate passed PHPUnit, PHPStan, and Deptrac, then
reported mechanical formatting differences in exactly these files:

- `backend/app/controller/api/platform/v1/OpsConsoleController.php`;
- `backend/app/controller/api/v1/IntegrationSecurityController.php`;
- `backend/app/filemedia/FileDeliveryHttpRuntime.php`;
- `backend/app/importexport/ImportExportHttpRuntime.php`;
- `backend/app/importexport/PdoFileMediaGateway.php`;
- `backend/app/integrationsecurity/IntegrationSecurityHttpRuntime.php`;
- `backend/app/notification/NotificationHttpRuntime.php`;
- `backend/app/notification/NotificationRuntimeFactory.php`;
- `backend/app/ops/PdoOpsTaskDispatcher.php`;
- `backend/app/ops/PdoRuntimeLogProvider.php`;
- `backend/app/task/TaskHttpRuntime.php`;
- `packages/php/integration-security/tests/mysql-harness.php`;
- `packages/php/kernel/tests/Unit/Override/ServiceOverrideRegistryTest.php`.

One separate remediation commit may apply only the existing
`scripts/php-cs-fixer.php` rules to those files. It must not change behavior,
contracts, dependencies, schemas, generated artifacts, or any other path.
After the exact write-set and `git diff --check` pass, only the failed PHP CS
Fixer group runs once. A second failure blocks Alpha.2.

## Web Unit Remediation Slice

The next Alpha.2 workspace gate passed lint and all Web typechecks, then found
six test-adapter failures while 215 Web tests passed. One separate remediation
commit may change only:

- `frontend/tests/account-page.spec.ts`;
- `frontend/tests/file-media-page.spec.ts`;
- `frontend/tests/w03-shell.spec.ts`;
- `starter/frontend/tests/file-media.spec.ts`;
- `starter/frontend/tests/reference-codes.spec.ts`;
- `starter/frontend/tests/settings.spec.ts`.

The remediation updates stale mocks for the already qualified override Host,
moves deterministic Starter package imports outside the per-test timer, and
may set a local 15-second limit only on the lazy-route test whose dynamic
import is itself the behavior under test. It must not change production code,
global timeouts, assertions, package exports, dependencies, or user-visible
behavior. After an exact write-set check and `git diff --check`, only
`pnpm test:unit` runs once. A second Web unit failure blocks Alpha.2.

## Web Unit Successor Remediation Slice

The blocked Web unit run proved the preceding six-file corrections, with all
original failures removed, and then exposed the same per-test dynamic-import
budget defect in two additional Starter tests. A new fixed-candidate
remediation commit may contain the already reviewed six-file corrections plus:

- `starter/frontend/tests/import-export.spec.ts`;
- `starter/frontend/tests/notification-sms.spec.ts`.

Those two tests must import `createStarterModules` statically, matching the
three corrected Starter package-consumption tests. The complete successor write
set is exactly these two files plus the six paths in the preceding Web Unit
Remediation Slice. Production code, global timeouts, assertions, dependencies,
exports, and user-visible behavior remain unchanged. After `git diff --check`
and an exact write-set review, `pnpm test:unit` runs once against the successor
tree. Failure blocks this successor candidate.

## Final Starter Import Remediation Slice

An exhaustive static scan of `starter/frontend/tests/*.spec.ts` after the first
successor commit found exactly three remaining deterministic imports inside a
per-test timer:

- `starter/frontend/tests/integration-security.spec.ts`;
- `starter/frontend/tests/ops-console.spec.ts`;
- `starter/frontend/tests/task-job.spec.ts`.

One final remediation commit may move only each file's
`createStarterModules` import to module scope. Dynamic imports in reference Host
lazy-route and Mock-order tests remain unchanged because loading order is part
of their assertion. No timeout, assertion, production source, dependency,
export, or behavior may change. After exact write-set review and
`git diff --check`, `pnpm test:unit` runs once. Failure blocks the final Starter
import remediation.

## Lock Evidence Registration Remediation

The first fixed-candidate aggregate qualification stopped before any test
because `docs/content-status.json` registered
`docs/decisions/dependencies/p1-pkg03-lock-evidence.json`. The repository's
documentation manifest intentionally accepts Markdown only, and the historical
P1-PKG02 JSON lock evidence is likewise referenced from Markdown rather than
registered directly.

One metadata-only remediation commit may remove exactly that JSON document
entry from `docs/content-status.json`. The lock evidence file, hashes,
candidate versions, package contents, Runtime, tests, dependencies, and all
Markdown registrations remain unchanged. After `jq empty`, `git diff --check`,
and exact write-set review, the resulting commit becomes the new Alpha.2 fixed
candidate for a separately updated qualification plan.

## Fixed Candidate Qualification

After a separate planning record fixes the exact version-candidate commit, the
qualification owner runs one aggregate candidate gate:

```bash
./scripts/check
```

Passing groups from the fixed candidate are not repeated. A failing group is
diagnosed once and requires an independent remediation contract before one
new fixed candidate may be qualified.

After the aggregate gate passes, qualification performs one content inspection
without publishing:

1. archive the exact `packages/php/` subtree and verify the root manifest,
   Apache-2.0 license, ten runtime PSR-4 roots, expected override contracts, and
   absence of Host, Web, credential, or unrelated monorepo content;
2. run `composer validate --strict` against that projection;
3. pack the exact `packages/web/` subtree and verify metadata, license, all
   existing Admin entries plus `./client`, `./client/nuxt`, and
   `./client/uniapp`, and absence of Host, secrets, and unrelated content;
4. record SHA-256 digests, file counts, tool versions, and the exact candidate
   commit in a new qualification record.

Qualification evidence may change only a new P1-PKG03 review document,
`docs/status/index.md`, and `docs/content-status.json`. Qualification does not
authorize publication.

## External Publication Gates

The historical P1-PKG02 approval record is not authority for Alpha.2. A new
approval record must verify, without exposing credentials:

- administrator ownership of the npm `@peanut-admin` scope;
- Packagist ownership for `peanut-admin/core`;
- a public generated-only `peanut-opensource/peanut-admin-core` split repository
  with protected immutable tags;
- GitHub workflow permission and non-interactive npm/Packagist identities;
- version and tag uniqueness;
- exact projection digests and provenance-capable publication;
- one isolated Composer registry consumer and one isolated npm registry
  consumer after publication.

Only after every gate is verified may a separate execution contract set
`publication_authorized: true` and perform external writes. Published versions
and tags are immutable; correction uses a newer version and deprecation, never
overwrite, retag, or deletion.

## Stop Line

This task does not publish, push a split, create a repository, tag, Release,
registry package, token, secret, or application dependency. Peanut Admin must
not consume a path-mapped or unpublished package. Application migration starts
only after exact registry consumers pass and a downstream decision names the
published version.
