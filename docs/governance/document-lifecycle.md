# Document lifecycle

Document ID: `core-doc-governance-document-lifecycle`

`docs/content-status.json` is the only Core documentation registry. Its document entries provide stable IDs and lifecycle; its groups provide audience, type, upstream, scope, relationships, projection and validation metadata without repeating those fields 100 times.

| Status | Meaning |
| --- | --- |
| `authoritative` | documentation index or governance fact for its declared domain |
| `current` | current explanation or evidence bound to its named upstream |
| `planned` | proposal or task contract; not implementation proof |
| `deprecated` | bounded replacement in progress; `replacement` is required |
| `archived` | historical evidence excluded from current navigation |
| `generated` | deterministic output; edit its source and regenerate |

Plans, evidence and decisions are content types, not synonyms for completion. A current evidence record proves only its fixed candidate and named checks.

New Markdown must be registered with a stable ID and match exactly one metadata group. Moves keep the ID. Superseding content updates inbound links and replacement metadata in the same change. Current developer pages must be reachable from the stable indexes or generated catalog; historical evidence stays out of primary navigation.

Generated files declare their source and command. The application and Core registries remain independent; cross-repository links point to an owner rather than creating a shared dual-write catalog.

Large-scale code comment upgrades are deferred until approved Application-to-Core convergence is complete. Documentation synchronization does not authorize comments that freeze a provisional product boundary.
