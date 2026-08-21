# P0 Runtime Qualification Review

## Decision

Peanut Admin satisfies the approved P0 engineering qualification at fixed D04
commit `d26186dfb23af34c62c58b4da94fea77bd63d724`.

This is an internal alpha foundation qualification. It is not a production
readiness statement, release approval, package publication, tag, or downstream
consumption approval.

## Fixed Evidence

- Reviewed commit: `d26186dfb23af34c62c58b4da94fea77bd63d724`.
- Qualified tree: `707e52c9dcffc6f690e2a4ba8454331a0de81124`.
- Qualification environment: PHP 8.3.24, Composer 2.10.2, Node 24.13.0,
  pnpm 11.13.0, MySQL 8.4.10, and Chromium through Playwright.
- Qualification command: `PEANUT_BROWSER_FRONTEND_PORT=4273 ./scripts/check`.
- Source and clean-clone worktrees were clean after qualification.

The D04 qualification commit is empty by design. Its tree is identical to the
fully checked `cd983dbb9fb8c8608b8e73583c4e918b50673677` tree, so the commit
records the evidence boundary without changing the qualified files.

## Aggregate Results

| Gate | Result |
| --- | --- |
| Documentation | 33 registered documents; site, links, executable examples, temporary install, Module tutorial, and internal starter passed |
| Dependency decisions | 44 accepted and 7 deferred decisions validated |
| Architecture | 0 violations, 0 skipped violations, 0 uncovered dependencies, 0 warnings, 0 errors |
| OpenAPI and Runtime | Contract valid; 75 P0 operations, 0 P1 operations, concrete handlers and test owners verified |
| Unit tests | 117 PHP tests / 2,453 assertions and 25 Web tests passed |
| MySQL integration | 82 tests / 639 assertions passed |
| Security | G-07 PHP qualification passed with 0 skipped security tests; secret history, dependency audit, restrictive HTTP defaults, tenant isolation, audience separation, and authorization parity passed |
| Browser | 26 desktop, mobile, and real full-stack tests passed; `/api/**` interception and skip markers absent |
| Recovery | Clean install passed; Alpha/Beta backup, corruption rejection, restore, login, and cross-tenant isolation passed |
| Performance | All seven scenarios stayed below the versioned p95 plus 20 percent limit |
| Starter | Reproducible clean-directory create, install, build, start, and test passed |
| Supply chain and license | Composer/pnpm high-risk audit passed; 484 dependency licenses inventoried; Apache-2.0 text and package declarations passed |
| Workspace | Composer validation, PHPStan, Deptrac, PHP-CS-Fixer, ESLint, TypeScript, Vitest, production builds, and Compose validation passed |

The broad PHPUnit invocation inside `check-workspace` reports 83 environment-
gated tests as skipped because it does not enable the dedicated integration
runners. Those tests already ran through `test-integration`, `test-recovery`,
and the security runners earlier in the same aggregate gate. G-07 security
qualification itself had zero skipped tests. The duplicate skip noise remains
a non-blocking engineering cleanup item; it is not used as qualification
evidence.

## Finding Closed During Review

`D05-P0-001` found that the installation guide did not name the exact Composer
2.10.2 version enforced by `check-workspace`. A clean-clone run reproduced the
failure with Composer 2.8.10. Commit
`cd983dbb9fb8c8608b8e73583c4e918b50673677` corrected the installation and
troubleshooting guides. The complete D04 gate was then rerun successfully and
fixed at `d26186d`.

No P0 finding remains open.

## Nine-Role Adversarial Review

### 1. Business And Product

- Attack question: Can a normal SaaS team understand the Tenant, Member,
  Department, Role, Module, and target-selection workflow without importing a
  product domain into the foundation?
- Verdict: PASS.
- Evidence: `docs/core-concepts/index.md`, `docs/guide/admin-web.md`,
  `docs/status/index.md`, and the 26 browser tests.
- Closed issue: the Admin Shell uses real Tenant and platform workspaces rather
  than contract-only placeholders.
- Residual risk: LikeAdmin-level convenience features remain P1/P2 and must not
  be advertised as P0.

### 2. SaaS And Tenant Architecture

- Attack question: Can a tenant identifier, platform operator, typed target, or
  future cross-tenant feature bypass the Tenant isolation root?
- Verdict: PASS.
- Evidence: `KernelSchemaConstraintTest`, `HttpRuntimeTest`,
  `AuthorizationPathParityTest`, separate platform APIs/sessions/audits, and the
  Alpha/Beta recovery fixture.
- Closed issue: platform governance authority does not grant tenant business
  record access.
- Residual risk: tenant groups, delegation, franchise collaboration, and
  cross-tenant business access are intentionally absent and require a later
  threat model and contract.

### 3. Identity Security

- Attack question: Can credentials, refresh rotation, audience selection,
  tenant switching, rate limits, or asynchronous context be confused or
  replayed?
- Verdict: PASS.
- Evidence: tenant and platform auth integration tests, fixed `__Host-` cookies,
  refresh-family reuse revocation, login lock/rate tests, signed async envelope
  tests, and real full-stack login tests.
- Closed issue: credential identifiers and refresh-family behavior were
  corrected during Runtime remediation.
- Residual risk: phone credentials, recovery, MFA, and OIDC are P1 and need
  separate security qualification.

### 4. Functional And Data Authorization

- Attack question: Do list, detail, create, update, command, aggregate, export,
  and background paths all fail closed under the same functional and data
  permission contracts?
- Verdict: PASS.
- Evidence: `DataPermissionEngineTest`, `AuthorizationPathParityTest`,
  `FunctionalAuthorizationTest`, typed-target cardinality tests, shared-master
  scope tests, and G-07 controls `PERM-001` through `PERM-039`.
- Closed issue: production handlers now use the Runtime data-permission chain;
  missing provider, target category, context, or policy denies.
- Residual risk: every future business Module must implement and test its own
  provider and repository constraints; the Kernel cannot infer them.

### 5. Database And Performance

- Attack question: Are tenant joins, constraints, large target sets, shared
  master scope, migration ownership, and concurrent session paths bounded and
  measurable?
- Verdict: PASS.
- Evidence: schema constraint and migration inventory tests, 82 MySQL
  integration tests, and versioned 10/500/5,000 target performance scenarios.
- Closed issue: the 5,000-target path uses a bounded JSON parameter shape.
- Residual risk: the current baseline is a local reference regression baseline,
  not a production SLO or capacity claim.

### 6. Backend Module Architecture

- Attack question: Can a Module reach another Module's internals or private
  tables, bypass manifests, or smuggle product models into the Kernel?
- Verdict: PASS.
- Evidence: manifest schema/compiler, owned migration ledger, Module guard,
  boundary checker, public contracts, and fictional target/reference/work-item
  Modules.
- Closed issue: Runtime guard order, authorization catalog synchronization, and
  contract conformance were completed during remediation.
- Residual risk: service extraction and external package compatibility are not
  promised in P0.

### 7. Frontend And Admin UX

- Attack question: Do platform/tenant audience separation, zero/one/many target
  states, tenant switching, stale responses, errors, and mobile layouts work
  against real APIs?
- Verdict: PASS.
- Evidence: desktop/mobile shell suites plus four real full-stack login and
  protected-flow tests, all 26 passed without API interception.
- Closed issue: browser qualification ports are configurable, preventing an
  unrelated local service from invalidating the evidence run.
- Residual risk: bundle-size warnings and full commercial-admin UX breadth are
  optimization work, not a P0 correctness blocker.

### 8. Open Source Maintenance

- Attack question: Can the repository be published and maintained without
  private paths, legacy ownership, unreviewed dependencies, or license drift?
- Verdict: PASS.
- Evidence: Apache-2.0 hash checks, NOTICE, 484-package generated license
  inventory, Composer/pnpm audits, secret history scan, lock evidence, and
  clean internal starter output.
- Closed issue: exact Composer qualification requirements are now public in the
  developer guide.
- Residual risk: no package has been published and no compatibility promise has
  been made; publication remains a separate P1/release decision.

### 9. Low-Context Delivery

- Attack question: Can a constrained implementation agent install, verify,
  extend, and diagnose the foundation without guessing the toolchain, package
  boundary, operation contract, or business model?
- Verdict: PASS.
- Evidence: `AGENTS.md`, executable developer guides, OpenAPI, Runtime operation
  ownership, stable scripts, dependency decisions, and the fixed internal
  starter.
- Closed issue: `D05-P0-001` removed the hidden Composer-version prerequisite.
- Residual risk: the P0 starter is fixed; configurable generation, codemods, and
  long-term template upgrades remain P1.

## Product Boundary Check

The Kernel, reusable packages, starter, examples, and public documentation do
not contain product-specific team, store, warehouse, supplier, product,
inventory, procurement, sales, delivery, pricing, or settlement models. The
fictional Project, Queue, Reference, and Work Item examples exercise contracts
only. Product business models must remain in product-owned Modules.

## Residual P1 And Release Risks

- Public generator, CRUD generator, package publication, SemVer compatibility,
  and source-upgrade tooling are not implemented.
- Phone credentials, invitations, password recovery, MFA, and OIDC are not
  implemented.
- Files, notifications, jobs UI, import/export, plugins, and marketplace are
  not implemented.
- Production sizing, observability, cloud backup/PITR adapters, deployment
  hardening, and business acceptance remain product/deployment responsibilities.
- The duplicated generic PHPUnit skip output should be made less confusing in a
  later engineering cleanup while preserving dedicated gate ownership.

## Subsequent Internal Baseline Decision

At review completion, the fixed P0 tree was qualified but not approved for
downstream consumption. On 2026-07-18, a separate decision approved promotion
to `dev` and exact-commit private downstream validation. The downstream
integration mapping must pin the resulting 40-character commit and keep product
business outside the Peanut Admin repository.

## Final Stop Line

The fixed P0 tree is qualified for pinned internal-alpha validation. It is not
production ready, not released, not tagged, and not published. Public release,
package publication, production deployment, and later Runtime changes require
independent qualification and approval.
