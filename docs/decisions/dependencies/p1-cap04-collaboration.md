# P1-CAP04 Collaboration Dependency Decision

## Status

```text
decision: P1-CAP04-DEPENDENCIES
state: accepted
reviewed_at: 2026-08-12
prerequisite_commit: c27e03006135adce56627b438a2ac82a4fef5d95
prerequisite_tree: f253b76ca09f056b60e2abd49a43251ba38383ef
public_package: @peanut-admin/admin
runtime_install_authorized: CAP04 only, after its independent contract is accepted
publication_authorized: false
```

The machine-readable decision is
[`p1-cap04-collaboration.json`](./p1-cap04-collaboration.json). This decision
authorizes no manifest, lockfile or Runtime change by itself.

## Accepted Runtime Dependencies

| Package | Exact reviewed version | License | Purpose |
| --- | --- | --- | --- |
| `yjs` | `13.6.32` | MIT | Mature CRDT document/update engine in the public Admin client boundary. |
| `y-websocket` | `3.1.0` | MIT | UI-neutral Yjs WebSocket client provider and protocol adapter. |

Both dependencies belong only to `@peanut-admin/admin`. PHP never interprets
CRDT content and adds no third-party dependency: it persists bounded,
versioned opaque update and snapshot envelopes through the already accepted PDO
boundary. Product document schemas, editors and validation remain Host-owned.

## Host Transport Decision

Core does not ship a WebSocket server or a third public package. A production
Host should run a separately deployed, pinned Hocuspocus server compatible with
the accepted Yjs protocol. `@hocuspocus/server@4.6.0` is the reviewed reference
for CAP06 Host validation, not a Core dependency and not part of either public
package. The Host owns authenticated HTTP/WSS endpoints, origin policy,
capacity, deployment and the internal authenticated load/store bridge.

The official `@y/websocket-server` is rejected as the production reference
because its maintainers describe it as development/prototype infrastructure.
Direct PHP implementation of Yjs sync, awareness, room broadcast or reconnect
semantics is prohibited because it would create a home-grown transport engine.

## Alternatives And Boundary

Automerge core is active and MIT licensed, but its Repo transport and storage
adapters were still on `2.6.0-alpha.3` at review time. Its additional Repo/WASM
runtime surface is not accepted for this stage. A future replacement must keep
the public `CollaborationEngine` and transport ports stable and migrate stored
opaque envelopes explicitly.

Admission, Tenant/member identity, functional and typed-target authorization,
session leases, publish authorization, retention and audit remain PHP
authorities. A CRDT update never grants access or approval. Missing identity,
provider, membership or permission fails closed. CAP04 must bound update,
snapshot and awareness sizes and must not persist access tokens, editor
payload semantics, cursor contents or another Tenant's presence.

## Security, Upgrade And Removal

Official package metadata, licenses, release activity and GitHub security
advisories were reviewed on 2026-08-12. No matching published npm GHSA was
returned for the accepted versions; this is not a no-vulnerability guarantee.
The eventual lock change must pass the existing npm audit, dependency review,
license inventory and fixed-version supply-chain gates.

Stored envelopes include engine name, engine version, sequence, state vector,
payload digest and bounded opaque bytes. An upgrade first replays representative
copies and proves forward compatibility. Removal freezes admission, drains
sessions, writes a final immutable ArtifactRevision and retains the old reader
until all active stored envelopes are migrated or expired.

## Exact Write Set And Stop Line

This decision commit may change only:

- this file;
- `docs/decisions/dependencies/p1-cap04-collaboration.json`;
- `docs/decisions/dependencies/index.md`;
- `docs/content-status.json`;
- `docs/status/index.md`;
- `docs/status/p1-post-q01-cross-product-capability-plan.md`.

It installs nothing, changes no lock, creates no Runtime source, starts no
sidecar and does not qualify, publish or authorize downstream consumption.
