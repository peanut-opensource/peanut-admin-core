# P1-PKG13 Alpha.12 Publication Candidate Contract

Document ID: `core-doc-status-p1-pkg13-alpha12-publication-candidate-contract`

```text
task: P1-PKG13
state: fixed candidate prepared
prerequisite: 32314b10fa7a01d8e60b91323c4a9de4b969f738
composer_package: peanut-admin/core@0.1.0-alpha.12
npm_package: @peanut-admin/admin@0.1.0-alpha.12
candidate_commit: the commit containing this contract
qualification: pending one immutable-candidate run with the P1-PKG14 bundle
publication_authorized: true only after successful qualification and publication preflight
downstream_adoption: false
```

## Objective

Freeze one coordinated Alpha.12 source candidate containing the product-neutral Module provider
binding contribution, transaction-ownership correction, locked external-channel read contract,
root shadow-tree prevention and directly related qualification wiring already merged to `dev`.
Alpha.12 remains a prerelease and does not add product business semantics, a second composition
root, a Runtime locator or a compatibility field.

This freeze changes only the minimum candidate-status projection. It does not create a split
commit, Tag, GitHub Release, npm or Packagist version, qualification claim, downstream lock,
application adoption or deployment.

## Exact Write Set

- `README.md`;
- `backend/composer.json`;
- `composer.json` and `composer.lock`;
- `package.json` and `pnpm-lock.yaml`;
- `packages/php/composer.json`;
- `packages/php/artifact-revision/src/Package.php`;
- `packages/php/entitlement-quota/src/Package.php`;
- `packages/php/entitlement-quota/tests/Integration/Application/EntitlementQuotaServiceTest.php`;
- `packages/web/package.json`;
- `starter/backend/composer.json` and `starter/backend/composer.lock`;
- `starter/frontend/package.json` and `starter/pnpm-lock.yaml`;
- `scripts/check-workspace`;
- `tests/starter/assert-generated-starter.php`;
- `resources/project-resources.json`;
- `docs/decisions/dependencies/p1-pkg03-lock-evidence.json`;
- `docs/status/index.md` and this contract;
- `docs/content-status.json`;
- `docs/governance/authoritative-source-map.md`;
- `docs/guide/testing.md`;
- generated `docs/reference/document-catalog.generated.md` and
  `docs/reference/third-party-licenses.generated.md`.

No Runtime implementation, schema, migration, OpenAPI, Module manifest, route, workflow, public
contract or third-party dependency may change. An insufficient write set stops this task.

## Development Acceptance

1. Every active package, workspace, starter and executable identity is exactly
   `0.1.0-alpha.12`; historical Alpha.11 evidence remains unchanged.
2. Composer 2.10.2 regenerates both path-package locks; Node 24.13.0 and pnpm 11.13.0 own the
   workspace lock identities. Third-party resolutions do not move.
3. The focused identity check validates every manifest/lock version and digest, both Composer
   manifests with Composer 2.10.2, both pnpm locks offline with pnpm 11.13.0, documentation
   governance and `git diff --check` once. The aggregate `./scripts/check-workspace` remains part of
   fixed-candidate qualification because it also runs PHP, Web, build and Docker groups.
4. Alpha.12 source Tag, split Tag, GitHub Release, npm and Packagist versions remain absent.
5. The worktree is clean after one reviewable commit. Aggregate Runtime qualification is deferred
   because release identity changes do not change Runtime behavior and the fixed qualification
   resource allocation is not yet registered.

Docs-impact targets that only explain installation, Module development, core concepts or generic
troubleshooting are explicitly waived: the candidate changes no command usage, Module public
contract, Runtime behavior or installation flow. The release identity and resource boundary are
closed by this contract, the status index, testing guide, source map and generated catalog.

## Qualification And Publication Stop Lines

Before qualification, Core must register exact, exclusively claimable MySQL, cache, browser,
listener, output and container resources for one fixed Alpha.12 commit. The qualification owner then
runs `./scripts/check` once from that immutable candidate and proves zero residual resources.

The user authorized completing the Alpha.12 publication and application-adoption chain on
2026-09-03. That authorization is conditional on successful qualification and the immutable
publication preflight. The Composer split projection must be exactly `packages/php/` from the
qualified tree and receive its immutable annotated Tag before the source Tag. The source Tag
workflow may then publish npm with provenance and create the GitHub prerelease; Packagist remains
an explicit authenticated refresh. Clean Composer and npm consumers must resolve the same
immutable version before any downstream application may adopt it.

Failure or missing authority blocks only its direct step. No existing Tag, Registry version or
Release is moved, overwritten, unpublished or deleted.
