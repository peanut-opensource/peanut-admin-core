# P0 Candidate

## Status

Peanut Admin has a qualified P0 internal-alpha candidate at:

```text
d26186dfb23af34c62c58b4da94fea77bd63d724
```

The complete evidence and nine-role review are recorded in
[`P0 Runtime Qualification Review`](../reviews/p0-runtime-qualification.md).

This candidate is not production ready and has not been released. A subsequent
2026-07-18 decision approved promotion to `dev` and exact-commit private downstream
validation. It did not approve a `main` branch, tag, GitHub Release, Composer
package, npm package, public stable baseline, or production deployment.

## Candidate Capability

- ThinkPHP 8 reference Runtime and Vue 3 Admin Web using real HTTP and MySQL.
- Separate tenant and platform authentication, sessions, audiences, guards,
  workspaces, RBAC, and audit streams.
- Tenant membership, Department, Role, Permission, TenantModule, typed-target
  data permission, operation cardinality, and shared-master scope contracts.
- Three fictional Modules proving multi-target reads, single-target writes,
  shared-master visibility, Module ownership, and public-contract composition.
- 75 concrete P0 OpenAPI operations and generated TypeScript contracts.
- Reusable PHP and Web packages consumed through a reproducible internal
  starter.
- Executable documentation, security, browser, recovery, performance,
  dependency, license, and architecture gates.

## Qualification Snapshot

- 117 PHP unit tests and 25 Web unit tests passed.
- 82 MySQL integration tests passed.
- G-07 security qualification passed with zero skipped security tests.
- 26 desktop, mobile, and real full-stack browser tests passed.
- Clean install, Alpha/Beta backup and restore, and internal starter passed.
- All seven performance scenarios stayed below their p95 regression limits.
- 75 P0 Runtime operations and 0 P1 Runtime operations were classified.
- 484 third-party package licenses were inventoried; high-risk dependency,
  secret, architecture, and license gates passed.

## Not Included

P0 does not include phone login, invitations, recovery, MFA, SSO, files,
notifications, jobs UI, import/export, plugins, marketplace, public project or
CRUD generators, package publication, commercial control plane, POS/mobile
clients, or product-specific business Modules.

## Internal Downstream Decision

The first approved consumer is a private validation project. It must pin the
exact qualified 40-character commit, generate a formal integration mapping,
and keep all product business Modules outside Peanut Admin. The external-host
path is qualified at `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`; see the
[External Host Consumption Qualification](../reviews/external-host-consumption-qualification.md).
Consuming a branch name, an unqualified later commit, or unpublished package
coordinates is not allowed.

## Next Release Decision

A separate public release decision must define branch/tag policy, package
strategy, production hardening, support expectations, and compatibility rules.
Until then, this candidate must not be represented as a public stable release
or production baseline.
