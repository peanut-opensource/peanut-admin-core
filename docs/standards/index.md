# Engineering Standards

Peanut Admin treats architecture, security, tests, documentation, and dependency evidence as one implementation surface.

## Non-Negotiable Rules

- Work from the current canonical documents and the assigned task boundary.
- Prefer accepted mature libraries; do not recreate established parsers, protocols, security primitives, or framework infrastructure.
- Add a failing test or gate before runtime behavior, then make the smallest bounded implementation pass.
- Keep tenant and platform audiences separate in sessions, routes, middleware, cache keys, and audit records.
- Treat context, permissions, module state, target resolution, and providers as fail-closed contracts.
- Keep module data ownership explicit; cross-module access uses public contracts.
- Update public documentation in the same task that changes public behavior.
- Never expose credentials, tokens, cookies, secrets, private paths, or personal data in logs, fixtures, commits, or generated documentation.
- Do not weaken checks, swallow exit codes, add test-only production bypasses, or use destructive Git commands.

## Dependency Governance

Read the [Dependency Policy](./dependency-policy.md) before adding or upgrading a library, image, tool, or CI action. The [P0 Dependency Decisions](../decisions/dependencies/) are the approved machine-checked baseline.

## Documentation Status

Every Markdown page is registered in `docs/content-status.json` as:

- `canonical`: current behavior or approved implementation fact;
- `draft`: incomplete and not an implementation fact source;
- `superseded`: historical and excluded from navigation and search;
- `generated`: produced from code or schema and not edited by hand.

Examples and generated API/schema references must be validated against the code or contract that produces them. A page becoming stale is a failed change, not a documentation backlog item.
