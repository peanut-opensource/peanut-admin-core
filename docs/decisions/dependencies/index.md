# P0 Dependency Decisions

This directory is the canonical dependency decision record for Peanut Admin P0.

The machine-readable registry is [`p0-dependencies.json`](p0-dependencies.json). It records every accepted and deferred item with its exact reviewed version, installation constraint, direct-dependency status, license, purpose, alternatives, adapter boundary, exit plan, security status, and official sources.

## Accepted Baseline

| Area | Decision |
| --- | --- |
| PHP host | PHP 8.3, ThinkPHP 8.1.4, Think ORM 4.0.51, Think Migration 3.1.1 |
| PHP quality | PHPUnit 12.5.31, PHPStan 2.2.5, Deptrac 4.6.2, PHP CS Fixer 3.95.15 |
| Structured manifests | Opis JSON Schema 2.6.0; hand-written schema parsing is prohibited |
| Admin Web | Vue 3.5.39, Vite 8.1.4, TypeScript 5.9.3, Element Plus 2.14.3, Pinia 4.0.2, Vue Router 5.2.0 |
| Web quality | Vitest 4.1.10, Playwright 1.61.1, ESLint 10.7.0, vue-tsc 3.3.7 |
| API contract | OpenAPI 3.1 validated by Redocly CLI and consumed through openapi-typescript plus openapi-fetch |
| Documentation | VitePress 1.6.4 on security-patched Vite 6.4.3 and Plugin Vue 5.2.4 |
| Development services | MySQL 8.4.10 and Valkey 9.1.0 Alpine |
| Supply chain | Composer/pnpm audits and license inventories, Gitleaks 8.30.1, GitHub dependency review |

TypeScript intentionally remains on the 5.9 line because the accepted `openapi-typescript` 7.13.0 release declares a TypeScript 5 peer dependency. A newer major number is not, by itself, a valid reason to break a verified toolchain.

The documentation workspace intentionally uses Vite 6.4.3 instead of VitePress 1.6.4's default Vite 5 range. Vite 5 is affected by GHSA-fx2h-pf6j-xcff, while Vite 8 is not compatible with the current VitePress rendering pipeline. This documentation-only pin is separate from the Admin Web Vite 8 baseline.

Valkey is accepted as the P0 development cache because it provides the required open-source RESP-compatible server. Cache access must remain behind Kernel cache ports and cache data must never become authoritative.

P1-B03 adds no third-party dependency. The Settings package reuses the accepted
Opis JSON Schema validator, PHP Sodium extension, PDO, and existing Web
toolchain. Composer and pnpm lock changes record new first-party workspace/path
packages only; they do not expand the accepted external dependency set.
The machine-readable [P1-B03 lock evidence](./p1-b03-lock-evidence.json) records
the reviewed hashes, frozen starter installs, tool versions, and zero-advisory
audit result without treating that evidence as qualification.

P1-B04 also adds no third-party dependency. The Reference Codes PHP and Web
packages reuse the Kernel, Admin Core, Admin Shell, PDO, JSON, and current Web
toolchain already locked by the repository. Its machine-readable
[P1-B04 lock evidence](./p1-b04-lock-evidence.json) records only first-party
path/workspace lock changes and development checks; audit, clean starter
installs, and fixed-candidate qualification remain deferred.

P1-PKG02 retains the two consolidated public package boundaries while updating
three root transitive development resolutions to security-fixed versions. Its
[current lock evidence](./p1-pkg02-lock-evidence.json) records the four exact
lock hashes and preserves the historical P1-PKG01 evidence unchanged.

Starter v1 C02 File And Media also adds no third-party dependency. The bounded
local adapter uses PHP fileinfo, hashing, PDO, and filesystem primitives behind
the first-party storage provider contract. Its machine-readable
[C02 lock evidence](./c02-file-media-lock-evidence.json) records only the new
first-party PHP and Web workspaces; production object-storage selection,
security audit, clean starter installs, and qualification remain deferred.

P1-CAP04 accepts exact `yjs@13.6.32` and `y-websocket@3.1.0` versions for the
`@peanut-admin/admin` collaboration boundary. PHP continues to persist opaque
bounded envelopes through existing PDO contracts. The separately deployed
Host may use the reviewed Hocuspocus server reference, but Core neither ships a
WebSocket server nor creates a third public package. See the
[CAP04 collaboration decision](./p1-cap04-collaboration.md) and its
[machine-readable record](./p1-cap04-collaboration.json). Installation remains
blocked until the independent CAP04 Runtime contract is accepted.

## Explicitly Deferred

P0 does not install or create speculative abstractions for filesystem storage, queue management UI, spreadsheet import/export, notifications, Plugin runtime or marketplace, MFA, or OIDC. Each requires an approved use path and a new dependency decision before installation.

## Installation Boundary

P0-A02 approves decisions only. It does not create `composer.json`, `package.json`, or lockfiles and does not install dependencies. P0-A03 may install only the accepted documentation toolchain. P0-A04 may install the accepted workspace dependencies and must record lock evidence without changing these decisions silently.

Run:

```bash
./scripts/check-dependency-decisions
```

The check fails when a mandatory decision is missing, a P0 deferral is promoted implicitly, or an accepted record contains an unpinned or placeholder version.
