# P1-PKG03 Alpha.2 Qualification Contract

## Status

```text
state: accepted
package_candidate_commit: b0dc376c2147b98522764486342c9525fe5678ce
composer_package: peanut-admin/core@0.1.0-alpha.2
npm_package: @peanut-admin/admin@0.1.0-alpha.2
qualification_status: pending
publication_authorized: false
```

## Objective

Qualify the exact Alpha.2 candidate after its workspace gate passed. The
candidate contains exactly two public packages, the PHP and Admin Web override
Host chains, and the UI-neutral `./client`, `./client/nuxt`, and
`./client/uniapp` npm subpaths. This task does not change package source,
publish a registry version, create a split repository, tag, Release, or approve
downstream application consumption.

## Fixed Environment

The qualification owner runs from the planning commit above the fixed
candidate with no package or Runtime source changes. These six host ports were
confirmed free before this contract:

```bash
export COMPOSE_PROJECT_NAME=peanut-admin-pkg03-q01
export MYSQL_PORT=33432
export CACHE_PORT=36432
export BACKEND_PORT=38132
export FRONTEND_PORT=35232
export PEANUT_BROWSER_BACKEND_PORT=38232
export PEANUT_BROWSER_FRONTEND_PORT=35332
export MYSQL_DATABASE=peanut_admin_pkg03_qualification
export DB_HOST=127.0.0.1
export DB_PORT=33432
export PATH=/opt/homebrew/opt/php@8.3/bin:$PATH
export PEANUT_COMPOSER=/tmp/peanut-composer-2.10.2-wrapper
```

Immediately before the gate, PHP must report `8.3.24`, Composer `2.10.2`, Node
`24.13.0`, and pnpm `11.13.0`. The owner then runs exactly once:

```bash
./scripts/check
```

A failure receives one read-only diagnosis and stops qualification. A fix
requires an independent remediation contract and a new fixed candidate.

## Q01 Documentation Manifest Stop And Q02 Qualification

Q01 stopped in `check-doc-content-status` before any test or service start
because the candidate incorrectly registered JSON lock evidence in the
Markdown-only documentation manifest. The remediation contract at `6837570`
and candidate commit `ad8fc0bc06fa40c36c43ab47c61437dd99c68b59`
remove only that registration. Package source, locks, Runtime, dependencies,
tests, and projection contents are unchanged.

All six fixed ports were confirmed free again after the remediation. Q02 may
run `./scripts/check` once with the unchanged Fixed Environment above against
the new candidate. Q01 provides no retained passing test evidence because it
stopped before the first test. A Q02 failure receives one read-only diagnosis
and stops qualification.

## Q02 Secret Fixture Stop And Remediation

Q02 passed documentation, reproducible Starter, dependency decisions,
architecture, OpenAPI, Runtime coverage, Composer and pnpm audits, and license
inventory. It then stopped in the gitleaks history scan on one redacted
`generic-api-key` finding from test-fixture commit
`a4e1ca9d20296b2ec1bd42dfea82eeefe225e27e` at
`backend/tests/Smoke/ServiceOverrideHostWiringTest.php:23`. The value is a
literal test envelope key, not a production credential, but current source and
history must both be handled explicitly.

One independent remediation commit may change only:

- `backend/tests/Smoke/ServiceOverrideHostWiringTest.php`, replacing the
  literal value with a runtime-constructed 32-byte non-secret placeholder;
- new `.gitleaksignore`, containing only this exact historical fingerprint:
  `a4e1ca9d20296b2ec1bd42dfea82eeefe225e27e:backend/tests/Smoke/ServiceOverrideHostWiringTest.php:generic-api-key:23`.

No wildcard, rule suppression, path exclusion, secret-scanner configuration,
production credential, assertion, Runtime behavior, dependency, lock, or
history rewrite is allowed. After exact write-set review and
`git diff --check`, `./scripts/check-secrets` runs once. A failure blocks the
remediation. A passing remediation becomes a new fixed candidate for a
separate no-repeat qualification continuation beginning after the retained
license-inventory result.

## Q03 No-Repeat Qualification Continuation

The remediation's one `check-secrets` run passed both Git history and current
working-tree scans. Candidate
`b0dc376c2147b98522764486342c9525fe5678ce` is therefore the new fixed
Alpha.2 candidate. Q03 retains every Q02 result through third-party license
inventory plus the remediation secret-scan result. It must not rerun those
groups.

The isolated Q02 MySQL 8.4.10 container remains healthy on port `33432`; the
cache and all application/browser ports remain free. With the unchanged Fixed
Environment, Q03 runs the remaining aggregate commands once, in this order:

```bash
php vendor/bin/phpunit tests/supply-chain
./scripts/test-unit
./scripts/test-integration
./scripts/test-security --php-only
./scripts/test-browser
./scripts/test-recovery
./scripts/test-performance
./scripts/verify-internal-starter
./scripts/check-workspace
```

After those commands pass, Q03 performs the unchanged repository tail guards
from `scripts/check`: approved Apache-2.0 root license hash, forbidden private
or product-specific documentation tokens, required/deferred directory state,
and `git diff --check`. A failure receives one read-only diagnosis and stops;
repair requires another independent contract and fixed candidate.

## Q04 Performance Toolchain Environment Retry

Q03 retained the passing supply-chain, unit, integration, security, browser,
recovery, internal Starter, and workspace evidence. Its only remaining group,
`./scripts/test-performance`, stopped in the PHP environment preflight before
executing a performance scenario because the invoking shell resolved PHP
8.1.33 instead of the fixed PHP 8.3.24 toolchain. No package, Runtime, test,
threshold, fixture, dependency, lock, or committed file changed.

Q04 authorizes one new invocation of `./scripts/test-performance` against the
unchanged candidate `b0dc376c2147b98522764486342c9525fe5678ce`. It uses the
complete Fixed Environment above and must additionally verify immediately
before the invocation that `command -v php` resolves below
`/opt/homebrew/opt/php@8.3/bin`, PHP reports `8.3.24`, and
`$PEANUT_COMPOSER --version` reports Composer `2.10.2`. These are environment
preflight facts, not qualification groups.

Q04 must not rerun any passing group, change a source or test file, lower a
performance threshold, select another database, or use a compatibility
runtime. If the performance group passes, qualification proceeds directly to
the unchanged repository tail guards and Package Content Inspection below. A
failure receives one read-only diagnosis and stops; a source repair requires
another independent contract and fixed candidate.

## Q05 Pinned pnpm Projection Retry

Q04 passed all seven performance scenarios and the focused performance
contract. The repository tail guards also passed. Package inspection then
validated the Composer projection and its ten Runtime PSR-4 roots; Composer
reported only the retained recommendation about the contractually fixed
manifest `version` field. The npm projection stopped before packing because a
temporary directory outside the monorepo resolved the global pnpm 9.15.6
instead of the fixed pnpm 11.13.0 toolchain, and that older executable rejected
the declared dry-run option.

Q05 retains the Composer projection result and authorizes only the unfinished
npm projection inspection. Immediately before packing, `corepack
pnpm@11.13.0 --version` must report `11.13.0`; both the dry-run and tarball
commands must use that exact executable. Q05 must not rerun the Composer
projection, a qualification group, or a repository tail guard, and must not
change package source, manifests, locks, exports, tool versions, or registry
state. A failure receives one read-only diagnosis and stops; a source repair
requires another independent contract and fixed candidate.

## Package Content Inspection

After the aggregate gate passes, one read-only package inspection must:

1. archive the exact `packages/php/` subtree with deterministic ordering and
   record its SHA-256 digest and file count;
2. verify its root manifest, Apache-2.0 license, exactly ten Runtime PSR-4
   roots, override contracts, and absence of Host, Web, secrets, credentials,
   and unrelated monorepo content;
3. run Composer strict validation against that projection;
4. pack the exact `packages/web/` subtree with pnpm 11.13.0, record its
   SHA-256 digest and file count, and verify all fourteen declared export
   subpaths including the three client entries;
5. verify the npm projection excludes Host applications, environment files,
   secrets, credentials, and unrelated monorepo content.

The inspection does not contact npm, Packagist, or GitHub and does not mutate a
registry, repository, tag, Release, or application lock.

## Qualification Write Set

Only the following evidence may change after all gates pass:

- new `docs/reviews/p1-pkg03-alpha2-publication-qualification.md`;
- `docs/status/index.md` for the exact result;
- `docs/content-status.json` to register the review.

The evidence must record the candidate commit, planning commit, toolchain,
aggregate result, both projection digests and file counts, public package and
export boundaries, retained warnings, and remaining publication gates.

## Stop Line

Qualification does not authorize publication or Peanut Admin application
migration. Alpha.2 remains unpublished until a separate approval verifies npm
scope ownership, Packagist ownership, the generated Composer split repository,
workflow identities and credentials, immutable version uniqueness, provenance,
and isolated registry consumers.
