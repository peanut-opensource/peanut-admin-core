# Peanut Admin Core documentation

This directory is the technical and public developer documentation source for the product-neutral Peanut Admin Core. It describes package contracts and verified behavior; it does not own Peanut Admin Application capabilities, deployment state or product Modules.

## Start here

| Need | Entry | Lifecycle |
| --- | --- | --- |
| Find the fact owner | [Authoritative source map](governance/authoritative-source-map.md) | authoritative |
| Adopt or extend Core | [Developer guide](guide/index.md) | current |
| Understand trust and ownership | [Core concepts](core-concepts/index.md) and [architecture](architecture/index.md) | current |
| Look up schema, targets or packages | `reference/` | current/generated |
| Inspect API | [API contract](api/index.md) | current projection of OpenAPI |
| Inspect qualification/history | `status/`, `reviews/`, `releases/` | plan/evidence; not primary navigation |
| Change documentation | [Lifecycle](governance/document-lifecycle.md) and [docs-impact](governance/docs-impact.md) | authoritative governance |

The generated [document catalog](reference/document-catalog.generated.md) is the complete discoverability layer. `docs/content-status.json` is the only document registry.

## AI reading order

1. Read repository `AGENTS.md` and required status facts.
2. Read `docs/content-status.json`, this index and the authoritative source map.
3. Open only the owning manifest, Schema, OpenAPI, command, decision or fixed evidence.
4. Use `docs/document-impact-map.json` to select the smallest update.
5. Do not infer implementation from plans or qualification from commit subjects.
6. Run `./scripts/core-docs-governance check` and the affected documentation build once.

Search stable IDs in `docs/content-status.json`, then exact operation, module or package names. A path match does not determine lifecycle; check its registered status and content group.

## Commands

```bash
./scripts/core-docs-governance impact --base origin/main
./scripts/core-docs-governance generate
./scripts/core-docs-governance check
pnpm docs:build
```

The broader `./scripts/check-docs` also runs executable examples and temporary database work. It belongs to an explicitly authorized stage, not a pure static documentation change.
