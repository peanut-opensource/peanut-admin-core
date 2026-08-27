# Peanut Admin Core developer documentation

Peanut Admin Core provides product-neutral PHP and Web packages, a reference Host, Module contracts, multi-tenant identity and authorization primitives, and executable examples. It is not the Peanut Admin product application and does not own product-specific Modules or deployment status.

## Choose a path

- **First adoption:** [install the reference Runtime](./guide/installation.md), then verify the exact package identity you intend to consume.
- **Understand the model:** read [Core concepts](./core-concepts/) and [architecture](./architecture/).
- **Build a Module:** follow [Module development](./guide/module-development.md), including manifest, data owner, Tenant and permission boundaries.
- **Build Admin UI:** use [Admin Web composition](./guide/admin-web.md) after the backend contract is fixed.
- **Validate and deliver:** use [testing](./guide/testing.md), [upgrade](./guide/upgrade.md) and [troubleshooting](./guide/troubleshooting.md).
- **Look up facts:** start at the [reference catalog](./reference/document-catalog.generated.md) and [API contract](./api/).

## Documentation boundary

The site is a developer-friendly projection of manifests, KernelSchema, OpenAPI, package metadata, decisions and fixed evidence. Plans and historical qualification remain discoverable through the catalog but stay out of primary navigation. If a page conflicts with its named upstream, fix the upstream relationship and smallest projection rather than adding another status summary.

## Stable principles

1. Login identity, Tenant membership and Platform identity are separate.
2. Functional permission and data authorization are separate fail-closed checks.
3. A Module owns its schema, migrations, rules, APIs and public contracts.
4. Product applications consume Core through accepted public boundaries; Core does not absorb product logic by implication.

The [authoritative source map](./governance/authoritative-source-map.md) and [documentation impact policy](./governance/docs-impact.md) explain how these pages stay current.
