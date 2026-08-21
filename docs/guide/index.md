# Developer Guide

This guide describes the P0 runtime that exists in this repository. It separates reusable package contracts, the reference host, and the fictional example modules so downstream projects can adopt only the layers they need.

## Recommended Reading Order

1. [Install the reference runtime](./installation.md).
2. Verify package consumption with the fixed [internal starter](./internal-starter.md).
3. Review [authentication and trusted context](./authentication.md).
4. Learn [functional and data authorization](./authorization.md).
5. Understand [typed targets](../reference/typed-targets.md) and [shared master scope](../reference/shared-master.md).
6. Build a capability with the [Module tutorial](./module-development.md).
7. Compose routes with the [Admin Web guide](./admin-web.md).
8. Configure the development-only [Tenant-private file adapter](./file-media.md).
9. Use the [testing](./testing.md), [upgrade](./upgrade.md), and [troubleshooting](./troubleshooting.md) runbooks.

## Runtime Boundary

P0 is a reusable foundation and reference implementation. It provides identity, tenant membership, platform identity, RBAC, data permission contracts, Module composition, typed targets, an Admin Shell, installation and local upgrade workflows, and executable examples.

P0 does not provide domain-specific application modules. The Runtime and external-host consumption paths have passed fixed-commit internal qualification, but Peanut Admin does not claim public production release qualification. Package publication, a public generator, compatibility guarantees, and production hardening remain separate work.

Every operation currently published in the P0 OpenAPI document has a concrete handler and typed success contract. The OpenAPI gate still treats a generated operation as insufficient unless the handler signature, metadata, schema, and generated PHP/TypeScript artifacts agree.
