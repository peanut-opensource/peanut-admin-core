# Product 3.1.0 Publication Candidate Contract

Document ID: `core-doc-status-product-310-publication-candidate-contract`

```text
task: CORE-PRODUCT-310-CANDIDATE
mode: Development preparation
state: package identity and qualification resources prepared; immutable candidate not frozen
composer_package: peanut-admin/core@3.1.0
npm_package: @peanut-admin/admin@3.1.0
candidate_commit: pending clean freeze
deferred_verification: CORE-PRODUCT-310-Q01
qualification: pending one immutable-candidate run and fixed-commit nine-role review
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

The pending record is
[`docs/releases/qualifications/3.1.0.json`](../releases/qualifications/3.1.0.json).
Its null identities and evidence fields are deliberate. They must be replaced
only with evidence from the same clean, fixed candidate after Q01 and the
nine-role D05 review pass. The source-tag verifier remains the authority for the
record schema and evidence-only path between candidate and tag.

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

The fixed persistent toolchain remains
`peanut-admin-core-php83-alpha12-qualification`,
`peanut-admin-core-composer-2.10.2-development`,
`peanut-admin-core-node24-pnpm11-development` and
`peanut-admin-core-pnpm-store-development`. Their stable IDs are retained even
where a historical version remains in the ID; their registry environments and
lifecycles explicitly allow the current qualification use.

The physical ports use a dedicated 3.1.0 group because an Alpha.13 port was
still occupied during preparation. The qualification owner proves every new
listener and the 3.1.0 Compose namespace are absent before claiming them. The lease
must include the eleven 3.1.0 stable IDs,
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
