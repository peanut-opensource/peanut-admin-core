# Product 3.1.0 Publication Candidate Contract

Document ID: `core-doc-status-product-310-publication-candidate-contract`

```text
task: CORE-PRODUCT-310-CANDIDATE
mode: Development remediation complete; replacement candidate not yet qualified
state: supply-chain root cause repaired in Development; Q01/D05 and publication remain pending for a new clean candidate
composer_package: peanut-admin/core@3.1.0
npm_package: @peanut-admin/admin@3.1.0
candidate_commit: b51c7ce64fdf5bdf4c6295e098a938869c7ffd53 (failed Q01; not the later verifier repair)
deferred_verification: CORE-PRODUCT-310-Q01
qualification: Q01 failed; nine-role D05 passed for the named candidate
publication: authorized only after fresh Q01/D05 qualification and immutable registry preflight
downstream_adoption: false
```

## Boundary

This contract prepares the first Core candidate using the coordinated Peanut
Admin product version. It does not qualify or publish the candidate. The
historical `0.1.0-alpha.13` source tag, GitHub Release, Composer split, npm
package, Packagist identity, qualification record and Application 3.0.14 lock
remain unchanged.

The public Composer and npm package manifests and their repository consumers use
`3.1.0`. Module versions remain independent. A matching Application/Core product
number does not replace the immutable source, package projection, qualification
or downstream-consumption identities.

The failed qualification record is
[`docs/releases/qualifications/3.1.0.json`](../releases/qualifications/3.1.0.json).
It preserves the exact failed candidate, both aggregate attempts and the
separate passing nine-role review. It must not be changed to pass using the
single successful Composer diagnostic. The source-tag verifier remains the
authority for the record schema and evidence-only path between candidate and tag.

## Development supply-chain remediation

The 2026-09-11 Development diagnosis retained the original failed Q01 identity
and found the actual failing command: pnpm audit reported high advisory
`GHSA-2883-xcg3-v3hh` through
`openapi-typescript -> @redocly/openapi-core -> js-yaml@4.3.1`.
The root workspace major-scoped override and lock now resolve `js-yaml@4` to
`4.3.2`; no direct dependency, Starter lock, package export, Runtime behavior,
audit threshold or supported-provider contract changed. The root lock change
was generated offline from a CR02-exclusive store after a separately recorded
controlled public-registry preparation. The preparation store is not a release
registry credential and does not relax offline validation.

The current Development lock digests are `composer.lock`
`eeef7fc7dd8adaeb774e79cb692dab8f2dd6bbed5ff74032e166b029a767a00e`,
`pnpm-lock.yaml`
`6fe0c15cbb1799548bceeb1dee3b4237cc17de024cc39269aefbbce47ec2e0f4`,
`starter/backend/composer.lock`
`9afb7a1fc51e618067fe42eb762bcbd49692934bb3d9e6ee4a8bf28a402590c1`, and
`starter/pnpm-lock.yaml`
`39f184f2d70344f0e4389fd68f5503f129667e1f2fe675dce646d0980c1bbae6`.
Focused evidence is pnpm audit with zero high/critical findings, the regenerated
license inventory, secret scan and `tests/supply-chain` (`2 tests, 17
assertions`). Three moderate pnpm findings remain; they are not described as a
zero-vulnerability result and require ordinary candidate risk review.

This Development result is not Q01, D05, a package publication, a tag, or
permission for Application lock adoption. A new clean candidate still must
repeat the existing fixed-candidate contract with its registered qualification
resources and immutable registry preflight.

## Historical Q01 failures and current recovery condition

The initial `ff3a58088d93ba08a3382dfdc941a92b22ba02ce` attempt and the permitted
retry at the candidate above are historical failures that both stopped in
`check-supply-chain`. The old gate removed its temporary audit JSON, so their
failing subcommand and root cause remain unknown. The 2026-09-11 Development
diagnosis found a current pnpm advisory but does not retroactively prove that it
caused either historical failure. Earlier Composer/network attribution remains
withdrawn. Unit, integration, security, browser, recovery, performance,
workspace and final repository groups did not start in those historical runs.

The two public PHP package version constants and their existing assertion were
corrected before the retry; Module manifests remained unchanged. All nine D05
roles passed the fixed three-file delta review. This is not Q01 qualification.

The gate now emits the failed audit name, original exit status and report before
cleanup; thresholds and failure semantics are unchanged. The current advisory
was repaired and focused Development checks passed. A fresh Q01 on candidate
`1061dddd87d246f25791c6870905f247d58e5552` then reached the final workspace
group and failed because Composer 2.10.2 strict validation rejects the root
workspace's exact `peanut-admin/core` constraint. Its earlier passing groups are
not inherited. The exact `3.1.0` constraint remains a deliberate first-party
candidate invariant and is separately asserted for all three Composer
consumers. Strict validation now accepts only that one known Composer advisory;
any other warning or error still fails. No package projection or Runtime
behavior changes. A new Q01 and D05 remain required on one replacement candidate
with healthy, exclusively leased resources.

All newly claimed 3.1.0 containers, networks, volumes, eight listeners and three
fixed output paths were removed and checked absent; the lease was released.
Historical Alpha.13 resources and preserved evidence were retained. Application
3.1.0 lock adoption and the coordinated release remain blocked on real Core
qualification and publication. Prepared development source may be integrated
only with this failed/unpublished status intact.

The historical Alpha.13 lock evidence remains immutable and is no longer used as
the hash authority for current workspace locks. `scripts/check-workspace` still
checks manifest/lock consistency and exact first-party identities. The clean
3.1.0 freeze must additionally record the four current lock digests and compare
them before Q01; the candidate commit/tree and projection digests then bind the
qualified source. Removing the historical hash comparison does not waive lock
integrity or permit a third-party resolution change.

## Registered Qualification Resources

The candidate selects these `qualification` resources from
`resources/project-resources.json`:

- `peanut-admin-core-mysql84-310-qualification` — MySQL 8.4.10 at
  `127.0.0.1:33434`, database `peanut_admin_310_qualification` plus exact
  script-owned qualification databases.
- `peanut-admin-core-valkey91-310-qualification` — Valkey 9.1.0 at
  `127.0.0.1:36434`.
- `peanut-admin-core-compose-310-qualification` — Docker Compose project
  `peanut-admin-core-310-q01`.
- `peanut-admin-core-playwright-chromium-310-qualification` — worktree-local
  Playwright 1.61.1 Chromium.
- `peanut-admin-core-310-qualification-output` —
  `/private/tmp/peanut-admin-core-310-q01` and the serially leased fixed outputs
  `/tmp/peanut-admin-playwright-results` and
  `/tmp/peanut-admin-recovery-report.json`.
- `peanut-admin-core-310-compose-backend-port` — `127.0.0.1:38134`.
- `peanut-admin-core-310-compose-frontend-port` — `127.0.0.1:35234`.
- `peanut-admin-core-310-browser-backend` — `127.0.0.1:38234`.
- `peanut-admin-core-310-browser-frontend` — `127.0.0.1:35334`.
- `peanut-admin-core-310-starter-backend` — `127.0.0.1:38334`.
- `peanut-admin-core-310-starter-frontend` — `127.0.0.1:35434`.
- `peanut-admin-core-cr02-pnpm-store-310-qualification` — CR02-exclusive
  `/private/tmp/peanut-cr02-q01-pnpm-store.Ggvf86` for the exact frozen root and
  Starter pnpm locks; all qualification installs and lock checks use it offline.

The fixed persistent toolchain remains
`peanut-admin-core-php83-alpha12-qualification`,
`peanut-admin-core-composer-2.10.2-development`,
`peanut-admin-core-node24-pnpm11-development` and the dedicated
`peanut-admin-core-cr02-pnpm-store-310-qualification`. The existing shared
`peanut-admin-core-pnpm-store-development` is not selected for this candidate.
Stable tool IDs are retained where their registry environments and lifecycles
explicitly allow qualification use.

The physical ports use a dedicated 3.1.0 group because an Alpha.13 port was
still occupied during preparation. The qualification owner proves every new
listener and the 3.1.0 Compose namespace are absent before claiming them. The lease
must include the eleven existing 3.1.0 stable IDs plus the dedicated CR02 pnpm
qualification-store ID,
all eight `port=<number>` resources, the Compose project, database, three output
paths, worktree and fixed candidate. A conflict blocks Q01; it never selects a
replacement port, database, cache, output or service.

## Fixed Environment

Preparation found historical browser and recovery files at the two fixed output
paths. The recovery report SHA-256 exactly matches the Alpha.13 Q01 record.
The fact convergence owner preserves those bytes under the registered
`peanut-admin-core-historical-evidence-preserved-20260909` resource before a new
qualification lease can use the original paths. This is historical preservation,
not new passing evidence; the existing Alpha.13 cache container is retained.

After the clean candidate is frozen and the complete resource lease succeeds,
the qualification owner uses:

```bash
export COMPOSE_PROJECT_NAME=peanut-admin-core-310-q01
export MYSQL_PORT=33434 DB_HOST=127.0.0.1 DB_PORT=33434 CACHE_PORT=36434
export BACKEND_PORT=38134 FRONTEND_PORT=35234
export PEANUT_BROWSER_BACKEND_PORT=38234 PEANUT_BROWSER_FRONTEND_PORT=35334
export PEANUT_STARTER_BACKEND_PORT=38334 PEANUT_STARTER_FRONTEND_PORT=35434
export MYSQL_DATABASE=peanut_admin_310_qualification
export TMPDIR=/private/tmp/peanut-admin-core-310-q01
export PEANUT_COMPOSER=/private/tmp/peanut-admin-core-tools/composer-2.10.2
export PNPM_STORE_DIR=/private/tmp/peanut-cr02-q01-pnpm-store.Ggvf86
```

Before dependency installation or any stateful gate, verify the registered PHP,
Composer, Node and pnpm versions; confirm every port and output path is absent;
confirm `peanut-admin-core-310-q01` has no container, network or volume; and
confirm the candidate checkout is clean. Create only the registered writable `TMPDIR`
after the lease is active.

## Freeze, Qualification And Stop Line

1. Freeze one clean commit after all authorized preparation and documentation
   impact changes are integrated. Run
   `node scripts/check-release-candidate --identity FULL_CANDIDATE_SHA` and
   record the candidate tree and package projection digests without claiming a
   pass.
2. In a detached worktree of that exact commit, claim the complete resource
   bundle, install the exact locks with the registered toolchain and caches, and
   run `./scripts/check` once for `CORE-PRODUCT-310-Q01`.
3. Inspect both package contents and complete the fixed-commit nine-role D05
   review for the same candidate. A source, test, manifest, lock, workflow or
   verifier repair creates a new candidate identity.
4. Commit the passing Q01 and D05 evidence plus the completed qualification
   record only after both pass. Before any later authorized publication, validate
   the clean annotated source tag with
   `node scripts/check-release-candidate v3.1.0`.

The user has authorized a new immutable 3.1.0 publication after fresh Q01/D05
qualification and the immutable registry preflight pass. This preparation is
not qualification evidence and does not satisfy those gates by itself.
Application lock movement, deployment and production claims remain separate
work. Cleanup after any qualification outcome removes only the claimed 3.1.0
project, volumes, output paths and owned child processes, then proves the
listeners and namespace are absent. Persistent tools and caches are retained.
