# Documentation impact

Document ID: `core-doc-governance-docs-impact`

The executable map is `docs/document-impact-map.json`.

| Classification | Required action |
| --- | --- |
| `none` | Record why behavior, contract, owner, command, configuration and architecture did not change. |
| `technical` | Update only the owning Core contract or maintainer explanation. |
| `developer-site` | Update the public developer projection after the upstream fact. |
| `generated` | Regenerate API, type or documentation output; never hand edit it. |
| `architecture-decision` | Accept the package, trust, data-owner or Core/Application decision first. |

## Loop

1. Update the manifest, Schema, OpenAPI, command, decision or other authoritative source.
2. Run `./scripts/core-docs-governance impact --base <base-ref>`.
3. Record the classifications and reason.
4. Update only the named technical pages and projections.
5. Regenerate named outputs.
6. Include every reported `required_targets` path in the same diff. If a named page is semantically unaffected, waive that exact existing path with one non-empty reason; a waiver is not a wildcard.
7. Run `./scripts/core-docs-governance check` and `pnpm docs:build` for a pure documentation change.

Example:

```bash
./scripts/core-docs-governance impact --base origin/dev \
  --classification technical --classification developer-site \
  --waive-target docs/guide/troubleshooting.md \
  --reason "the changed command is not referenced by troubleshooting"
```

`./scripts/check-docs` additionally executes examples and a temporary MySQL workflow; use that aggregate only when its stage owner and resource rules authorize it.
