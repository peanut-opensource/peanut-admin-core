# Authoritative source map

Document ID: `core-doc-governance-authoritative-source-map`

Status: `authoritative`

Owner: documentation architecture

This map identifies the fact owner. Core documentation explains and projects these sources; it does not duplicate their inventories.

| Fact | Authoritative upstream | Documentation projection |
| --- | --- | --- |
| Package identity and dependencies | root/package manifests, lock files and accepted dependency decisions | architecture and package reference |
| Module identity, lifecycle and startup bindings | each `module.json`, `packages/php/kernel/resources/module-manifest.schema.json`, `ModuleProvider` and `ModuleProviderBindings` | Module guide and architecture |
| Kernel data structure | KernelSchema implementation and owned migrations | Kernel Schema reference |
| HTTP contract | `docs/api/openapi.yaml`, route/handler and generated artifacts | API reference |
| Runtime operation coverage | `docs/status/runtime-operation-coverage.json` plus executable test owner | status evidence, never a release claim |
| Commands and configuration | executable `--help`, scripts, profiles and example configuration | installation, testing, upgrade and troubleshooting guides |
| Runtime, toolchain and publication resources | `resources/project-resources.json` plus the consuming workflow job environment | testing guide |
| Architecture and dependency decisions | accepted records in `docs/decisions/`, manifests and enforced dependency graph | architecture/concepts pages |
| Qualification or release evidence | fixed-commit review/release records | evidence pages, not current implementation authority by themselves |
| Documentation identity and lifecycle | `docs/content-status.json` | generated catalog and indexes |
| Documentation impact | `docs/document-impact-map.json` | docs-impact policy |

## Application boundary

Core owns product-neutral contracts and reusable packages. Peanut Admin Application owns product Modules, product deployment and product capability status. Cross-repository references link to the owner; neither repository copies the other's status ledger or implies adoption without an immutable accepted identity.

Use `repo://peanut-admin/<path>` in structured metadata when referencing the Application repository. Do not commit personal checkout paths.
