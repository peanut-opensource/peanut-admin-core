# External Host Consumption Qualification

## Decision

Peanut Admin commit `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`
qualifies for exact-commit private downstream validation by an external host.

This decision extends the qualified P0 internal-alpha foundation with external
Module hosting and isolated Tenant Clients. It is not a production-readiness
statement, public release, tag, package publication, or compatibility promise.

## Fixed Evidence

- Reviewed commit: `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`.
- Reviewed tree: `12fdd00c1d506ca860b76dcc9e2dd796d56b723f`.
- Module manifest schema SHA-256:
  `332e9d8d17c2952194e26f377673da20abc0f2f55835f1416ea1920b4814a030`.
- Composer lock SHA-256:
  `066005bb9d58b059f433e90bb752755e4cc01c92729c05cdb2f465ccc1b44264`.
- pnpm lock SHA-256:
  `0c895209bf3cf54a3e9a55a9dff4ad2691ec3c28eca652b64b56e2f05d5aa5bf`.
- Qualification environment: PHP 8.3.24, Composer 2.10.2, Node 24.13.0,
  pnpm 11.13.0, MySQL 8.4.10, and Chromium through Playwright.
- Qualification command: `./scripts/check` with the recorded toolchain selected
  in the shell environment.
- The reviewed worktree was clean before and after the successful aggregate
  run.

The recorded hashes identify the exact source and dependency inputs. A consumer
must pin the reviewed 40-character commit; a branch name is not a dependency
lock.

## Qualified Capability

- A host can register its own PHP namespace, Module source root, migration
  root, table prefixes, and Kernel-reserved tables without adopting the
  reference host namespace.
- The reusable Module boundary checker validates external host layouts and
  rejects undeclared table ownership or internal cross-Module dependencies.
- Tenant Client keys are selected by trusted server configuration. Login
  challenges, sessions, access tokens, refresh families, menus, and refresh
  cookies remain bound to that Client.
- Independent Clients cannot reuse each other's challenges, tokens, sessions,
  or refresh cookies. Refresh rotation in one Client does not invalidate an
  independent Client session.
- Web consumers can use application-owned OpenAPI paths and API prefixes through
  the reusable protected transport. Refresh coordination is scoped by Client
  and audience across browser instances.
- The fixed internal starter proves an external namespace, real Kernel and
  data-permission migrations, two fictional Clients, MySQL authentication,
  package installation, frontend type checking, tests, production build, and
  HTTP startup from clean generated directories.

## Aggregate Results

| Gate | Result |
| --- | --- |
| Documentation | 35 registered documents, executable examples, documentation site, and internal starter passed |
| Dependency and supply chain | 44 accepted and 7 deferred decisions; lock, license, audit, and secret checks passed |
| Architecture | 0 violations, 0 skipped violations, 0 uncovered dependencies, 0 warnings, and 0 errors |
| OpenAPI and Runtime | Contract valid; 75 P0 operations and 0 P1 operations retained concrete handlers and test owners |
| Unit tests | 129 PHP tests / 2,473 assertions and 27 Web tests passed |
| MySQL integration | 84 tests / 654 assertions passed |
| Security | Dedicated suites passed with 0 skipped security tests; Client, audience, tenant, permission, HTTP, and token boundaries remained fail closed |
| Browser | 26 desktop, mobile, and real full-stack tests passed without API interception |
| Recovery and performance | Clean install, backup/restore, cross-tenant recovery checks, and all seven p95 regression scenarios passed |
| Starter | Reproducible generation, locked installation, external Module host, two-Client authentication, tests, build, and HTTP start passed |
| Workspace | Composer validation, PHPStan, Deptrac, PHP-CS-Fixer, ESLint, TypeScript, Vitest, production builds, and Compose validation passed |

The broad workspace PHPUnit invocation still reports environment-gated tests as
skipped because their dedicated runners already executed them earlier in the
same aggregate gate. The dedicated security qualification reported zero skips.

## Boundary Check

This change adds reusable host and Client contracts only. It does not add a
product-specific team, store, warehouse, supplier, product, inventory, trade,
pricing, or settlement model to the Kernel, reusable packages, starter, or
fictional examples. Downstream business models remain owned by downstream
Modules.

## Residual Risks And Stop Line

- The current aggregate test scripts use fixed local service names, ports, and
  database names. Qualification runs for the same checkout and Compose project
  must therefore execute exclusively rather than concurrently.
- The starter is an internal consumption proof, not a public configurable
  generator or long-term template upgrade contract.
- Package publication, SemVer compatibility, public release, production sizing,
  and deployment hardening remain separate decisions.
- Later Runtime changes are unqualified until a new fixed-commit aggregate gate
  and review are recorded.

The reviewed commit is approved only for pinned private downstream validation.
Do not create a tag, GitHub Release, Composer package, npm package, or production
claim from this record.
