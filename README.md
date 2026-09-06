# Peanut Admin

Peanut Admin is an open-source, multi-tenant administration foundation built for reusable management applications.

The project provides a P0 candidate foundation with:

- reusable PHP and web packages;
- a ThinkPHP 8 reference backend;
- a Vue 3, TypeScript, Vite, and Element Plus admin shell;
- a fixed internal starter, documentation, fictional examples, and engineering checks.

## Current Status

The original P0 sequence reached the historical D04 qualification commit `f351a21`, but a second-wave review found that contract and fixture evidence did not sufficiently prove a real HTTP Runtime, complete P0 handlers, non-intercepted full-stack E2E, or a consumable internal starter. That commit remains historical evidence and is not a qualified Runtime baseline.

The remediation history contains implementation and evidence for `PA-P0-R00` through `PA-P0-R07`, followed by the revised developer-guide and recovery gates. The aggregate D04 gate and fixed-commit D05 nine-role review qualify the Runtime tree fixed at `d26186dfb23af34c62c58b4da94fea77bd63d724` as a P0 internal-alpha foundation.

On 2026-07-18, the qualified candidate was approved for promotion to `dev` and pinned private downstream validation. A downstream project must record the exact 40-character commit and integration mapping; a branch name is not a dependency lock. This approval is not a production-readiness statement, tag, GitHub Release, or Composer/npm package publication. See `docs/status/index.md` for current implementation evidence.

The external-host consumption path is separately qualified at commit
`0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`. It proves host-owned Module
namespaces and layouts, server-registered Tenant Clients, Client-scoped
authentication and refresh, and the fixed starter as a real downstream
consumer. The same release and production stop lines continue to apply.

P1 execution planning is fixed by commit `957e7b6`. The current P1 candidate
slices add tenant-audience account profile and password self-service plus a
permission-gated, current-state effective-access preview for Tenant members. P1
candidate commits do not move the external-host consumption lock and remain
unqualified for downstream consumption until a new aggregate review approves a
fixed commit. P1-R01 additionally provides unqualified transaction,
idempotency, audit-outcome, savepoint, and failure-atomicity primitives for
external Module commands; the host still owns its domain and outbox schema.
P1-R02 composes those primitives with trusted context, Module availability,
existing functional and typed-target authorization, and stable Problem Details
for a host-owned API. It remains an unqualified candidate and does not make the
fictional example a generic domain engine.
P1-B03 adds unqualified first-party PHP and Web Settings packages, Module-owned
typed definitions, deployment/Tenant/target precedence, encrypted secret
storage, six platform/Tenant operations, and a Tenant settings page. It remains
outside the fixed downstream-consumption lock until a later fixed-commit
qualification and separate consumption decision.

P1-WF01 is the accepted product-neutral configurable Workflow Runtime source
candidate fixed at commit `3972c9aefcd55ac71d07a47739a99d23bb0ae30c` and
tree `d6dbde37907d1dd43b00057fc16fbd1a8d6dd052`. It provides versioned
definitions, Tenant instances, human work items, immutable subject-revision
pins and adapters to the existing authorization, File/Media, Task/Job,
Notification/SMS and audit authorities. It contains no media product model or
realtime editing implementation. Later prereleases incorporated this source;
historical Alpha.5 planning text is not the current package identity. The Core
package version line remains independent of every Peanut Admin application
release.

Starter v1 C02 adds an unqualified first-party File And Media candidate with a
provider-neutral PHP boundary, a local private development adapter,
Tenant-scoped metadata, guarded upload/download/archive operations, and the
existing Admin Web `/app/files` page. It does not claim production object
storage, malware scanning, public delivery, or downstream qualification.

The later Starter v1 development candidates add Task/Job, Notification/SMS,
Import/Export, Integration Security, and a platform Ops Console to the same
reference Host and fixed starter. These capabilities use first-party package
contracts and keep provider-specific production choices outside the starter.
They remain development candidates until a fixed-tree qualification approves
the complete Starter v1; this repository state is not a package publication,
release, or downstream consumption lock.

P1-PKG01 consolidates the installable Runtime surface into exactly two public
package boundaries: Composer package `peanut-admin/core` and npm package
`@peanut-admin/admin`. Coordinated `0.1.0-alpha.12` is published from source
commit `9089516a18f19e19a048683594087e0b4ffc5455` and Composer split commit
`9017212da0da63f445d693be94d533f681c6dc92`; its source/split tags, GitHub
Release, Packagist and npm identities agree, and clean Composer consumption has
been verified. Domain source directories remain private inside the two public
packages and are not independently publishable.

## Principles

- Tenant and platform identities remain separate.
- Functional permission and data authorization remain separate.
- Modules own their data and expose public contracts.
- Missing context, permission, provider, or declaration fails closed.
- Product-specific business logic does not belong in this repository.
- Dependencies require an accepted decision before installation.

## Documentation

Start with [the documentation index](docs/README.md).

## Development

Bootstrap each new worktree once from the shared pnpm content store:

```bash
./scripts/bootstrap-worktree-dependencies
```

Only after an explicit offline cache miss, warm the store and retry:

```bash
./scripts/warm-worktree-dependencies
./scripts/bootstrap-worktree-dependencies
```

The bootstrap is offline and frozen. Repeated focused tests reuse the local
worktree layout; the command rebuilds only when dependency inputs, Node, pnpm,
the operating system, the architecture, or the resolved store path changes.

Ordinary bounded changes run the focused checks that cover their affected
behavior. See [the testing guide](docs/guide/testing.md) for the layered
verification policy and available commands.

The stable aggregate entry point for a fixed milestone, qualification, or
release candidate is:

```bash
./scripts/check
```

The `dev` branch is the collaboration branch. A task is complete only after its
focused acceptance checks pass on a clean commit and deferred milestone checks
are recorded. Commit subjects and historical qualification results are
evidence, not release approval.

## License

Apache License 2.0. See [LICENSE](LICENSE) and [NOTICE](NOTICE).
