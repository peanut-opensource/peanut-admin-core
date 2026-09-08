# Alpha.13 Publication Candidate Contract

Document ID: `core-doc-status-alpha13-publication-candidate-contract`

```text
task: CORE-ALPHA13-CANDIDATE
mode: Development
state: coordinated release identity prepared; immutable qualification lock pending
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

The existing license generator encountered a missing pnpm package index for
`@playwright/test@1.61.1` in the registered cache. One focused diagnosis
confirmed that installation prerequisite. The inventory therefore projects only
the Core version row mechanically; unchanged third-party lock entries preserve
the prior inventory. Full regeneration is deferred until exact dependency
installation in fixed qualification; no passing license-generation claim is made.

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

1. Integrate all approved preparation, resolve known blockers and freeze one
   clean source commit/tree. Record its manifest, four lock, generated-artifact
   and package-inventory SHA-256 identities, resource IDs/environment, lease/run
   ID and command in an immutable candidate lock. This repository currently has
   no dedicated candidate-lock generator/verifier; the qualification owner must
   record and compare those exact identities before invoking the existing runner.
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
