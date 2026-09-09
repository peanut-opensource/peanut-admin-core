# Authoritative source map

Document ID: `core-doc-governance-authoritative-source-map`

Status: `authoritative`

Owner: documentation architecture

This map identifies the fact owner. Core documentation explains and projects these sources; it does not duplicate their inventories.

| Fact | Authoritative upstream | Documentation projection |
| --- | --- | --- |
| Package identity and dependencies | root/package manifests, lock files and accepted dependency decisions | architecture and package reference |
| Product, Core, Edition, Module and customer Instance versions | [Product version identity](../architecture/product-version-identity.md), exact manifests, immutable tags and consumer locks | architecture and release guides; matching numbers do not prove qualification or deployment |
| Module identity, lifecycle and startup bindings | each `module.json`, `packages/php/kernel/resources/module-manifest.schema.json`, `ModuleProvider` and `ModuleProviderBindings` | Module guide and architecture |
| Kernel data structure | KernelSchema implementation and owned migrations | Kernel Schema reference |
| HTTP contract | `docs/api/openapi.yaml`, route/handler and generated artifacts | API reference |
| Runtime operation coverage | `docs/status/runtime-operation-coverage.json` plus executable test owner | status evidence, never a release claim |
| Commands and configuration | executable `--help`, scripts, profiles and example configuration | installation, testing, upgrade and troubleshooting guides |
| Runtime, toolchain, qualification and publication resources and CI stage triggers | `resources/project-resources.json` plus the consuming workflow event, immutable candidate input and job environment | testing guide |
| Architecture and dependency decisions | accepted records in `docs/decisions/`, manifests and enforced dependency graph | architecture/concepts pages |
| Application/Core PHP Runtime direction | `repo://peanut-admin/docs/architecture/core-thinkphp-runtime-direction-adr.md`, fixed two-repository source snapshots and the accepted product version identity | [Core architecture projection](../architecture/index.md#accepted-thinkphp-8-runtime-direction), Module guide and current PDO migration-before notes |
| Qualification or release evidence | fixed-commit review/release records | evidence pages, not current implementation authority by themselves |
| Source-tag qualification binding | `scripts/check-release-candidate`, `.github/workflows/release.yml`, `docs/releases/qualifications/<version>.json` and its committed evidence files | [Release qualification binding](../guide/release-qualification.md) |
| Historical Alpha.13 publication | immutable source/split tags, Registry metadata and `docs/releases/qualifications/0.1.0-alpha.13.json` | status index; the archived preparation contract retains its original time scope |
| Documentation identity and lifecycle | `docs/content-status.json` | generated catalog and indexes |
| Documentation impact | `docs/document-impact-map.json` | docs-impact policy |

## Application boundary

Core owns product-neutral contracts and reusable packages. Peanut Admin Application owns product Modules, product deployment and product capability status. Cross-repository references link to the owner; neither repository copies the other's status ledger or implies adoption without an immutable accepted identity.

Use `repo://peanut-admin/<path>` in structured metadata when referencing the Application repository. Do not commit personal checkout paths.
