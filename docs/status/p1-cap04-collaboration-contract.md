# P1-CAP04 Collaboration Contract

## Status

```text
task: P1-CAP04
state: accepted implementation contract
prerequisite_commit: 7105800845e364da9a2fa731b7a1d8cdf6b5163b
prerequisite_tree: f7dfc9f4913dc4d6379516efdfe3a1b98af51571
dependency_decision: P1-CAP04-DEPENDENCIES
public_boundaries: peanut-admin/core and @peanut-admin/admin
target_candidate: 0.1.0-alpha.5, unpublished
http_api: none; each Host owns authenticated HTTP/WSS endpoints
test_owner: P1-COLLABORATION-001
qualification: deferred to P1-CROSS-PRODUCT-QUALIFICATION-001 / CAP05
downstream_adoption: deferred to CAP06
publication_authorized: false
```

Core PR #11 accepted the exact collaboration dependency decision, repaired the
required-workflow path deadlock, passed all six required checks and merged as
the exact commit and tree above. A source branch, dependency source commit or
moving `dev` is not a valid prerequisite.

## Objective And Non-Goals

CAP04 adds a product-neutral collaborative editing authority. PHP owns
Tenant-scoped sessions, short participant leases, ordered opaque update and
snapshot envelopes, retention markers, publish/close state and atomic creation
of one immutable ArtifactRevision. The npm boundary provides a UI-neutral Yjs
engine, y-websocket transport adapter and observable session runtime.

A Host owns product document schemas, editor UI, business validation,
authenticated HTTP/WSS routes, same-origin proxy, Hocuspocus deployment,
capacity planning and product permissions. CAP04 does not add a product editor,
anonymous editing, cross-Tenant rooms, a WebSocket server, production realtime
topology, Workflow approval, billing, public package publication or application
adoption. It does not parse or reimplement Yjs, CRDT, OT, sync or awareness
protocols in PHP.

## Existing Authorities And Required Reuse

| Concern | Existing authority | CAP04 rule |
| --- | --- | --- |
| Tenant, member and target | Kernel `AuthorizedOperationContext` | Every call uses one trusted Tenant/member/account and exactly one primary typed artifact target. |
| Authorization | Kernel operation and typed targets | Transport connectivity never grants read, write or publish authority. |
| Transactions/idempotency/audit | Kernel PDO primitives | Every write, envelope, ArtifactRevision and redacted audit shares one caller PDO transaction. |
| Immutable result | ArtifactRevision | Publish creates exactly one finalized revision through a same-PDO adapter; Collaboration stores only its opaque key/digest. |
| Approval | HumanWorkflow | Workflow may consume the finalized revision later; it never reads mutable sessions, leases, presence or updates. |
| CRDT engine | `yjs@13.6.32` | Only the npm client boundary interprets Yjs updates. |
| Client transport | `y-websocket@3.1.0` | Same-origin secure WebSocket adapter only; no raw access token in URL, storage or logs. |
| Production sidecar | Host | Reviewed Hocuspocus is a deployment reference, not a Core dependency or third public package. |

All production persistence and ArtifactRevision adapters expose the same PDO.
Missing policy, membership, submission or revision adapters fail closed.

## PHP Service Contract

`CollaborationService` exposes exactly eight operations:

| Operation | Contract |
| --- | --- |
| `openSession` | Open one active session for an authorized artifact and pin its current immutable base revision. |
| `joinSession` | Revalidate membership/target access and create one short participant lease for a stable client key. |
| `heartbeat` | Revalidate membership and extend only the caller's active lease within the session policy. |
| `appendUpdate` | Append one digest-verified opaque update at the next locked sequence for the caller's active write lease. |
| `saveSnapshot` | Persist one digest-verified snapshot/state-vector envelope covering an exact sequence. |
| `state` | Return the latest visible snapshot and a bounded ordered update page after a caller-supplied sequence. |
| `publish` | Revalidate publish authority, require a snapshot through the latest sequence, finalize one ArtifactRevision and close the session. |
| `closeSession` | Close without publishing, revoke leases and mark all envelopes for bounded retention. |

Every write requires an `Idempotency-Key`; `state` does not. Heartbeat replays
the same safe lease receipt and never extends twice. Context identity comes only
from trusted Kernel context. Artifact/session/lease existence, cross-Tenant
identity and invisible targets share the same not-found result.

Keys use stable lowercase identifiers: session, lease, update and snapshot keys
are their prefix plus 32 lowercase hex bytes. Artifact types and engine keys use
the existing 64-byte identifier grammar; artifact and client keys are printable
ASCII up to 128 bytes. Sequence/revision values are positive signed 64-bit
integers and overflow is rejected.

`CollaborationPolicyProvider` returns a bounded immutable policy for the exact
Tenant/target: session TTL 300-86400 seconds, lease TTL 30-300 seconds, update
limit 1-262144 bytes, snapshot limit 1-8388608 bytes, at most 1000 unsnapshotted
updates and 30-7776000 retention seconds. Core may impose stricter hard maxima.
The Provider also revalidates active membership and requested capability at
join, heartbeat and publish. Absence, exception or malformed policy denies or
returns provider unavailable; there is no cached allow or unlimited fallback.

`CollaborationSubmissionProvider` validates the Host-owned document at publish
and returns the finalized ArtifactRevision envelope fields without exposing
payload bytes to audit. `CollaborationRevisionPublisher` uses the existing
ArtifactRevision service on the same PDO. Publish locks the session, proves the
snapshot covers its latest update, invokes the Provider and publisher, stores
the returned immutable revision key/digest, closes the session and revokes all
leases in one transaction. Any failure rolls back all of those effects.

## Data And State Contract

Collaboration owns exactly four MySQL 8 tables with Tenant-first indexes,
binary identifier collations and UTC `DATETIME(3)` timestamps.

`pa_collaboration_session` stores Tenant/artifact identity, session key, engine
name/version, base ArtifactRevision key/digest, latest sequence, optimistic
revision, `active|published|closed|expired` state, an active marker that permits
only one active session per Tenant/artifact, actor IDs, expiry, close/publish
timestamps, published revision key/digest and `retain_until`. It has no foreign
key to the ArtifactRevision owner.

`pa_collaboration_participant_lease` stores Tenant/session, lease and client
keys, member/account identity, `read|write` capability, authorization-basis
digest, `active|revoked|expired` state, optimistic revision and issued,
heartbeat, expiry and revoke times. It stores no cookie, bearer, refresh token,
WebSocket URL or Provider credential.

`pa_collaboration_update_envelope` is append-only. It stores Tenant/session,
monotonic sequence, update/client/lease keys, engine name/version, byte length,
lowercase SHA-256, opaque binary payload, author and occurrence time. The same
Tenant/session/update key or sequence is unique. Updates are never mutated into
snapshots or business documents.

`pa_collaboration_snapshot_envelope` is append-only. It stores Tenant/session,
snapshot key, covered sequence, engine name/version, bounded opaque snapshot
and state-vector bytes, lengths and SHA-256 digests, author, creation time and
`retain_until`. A snapshot cannot cover a future or missing update sequence.

An active session may become published, closed or expired exactly once. A lease
may become revoked or expired exactly once. Published/closed sessions never
reopen; a later session uses a new key and current immutable base revision.
Retention deletion is a later maintenance operation and cannot delete the
published ArtifactRevision or its Host-owned payload.

## Ordering, Backpressure And Presence

Append locks the session row, expires stale leases, verifies the caller lease,
checks the client update digest and assigns exactly `latest_sequence + 1`.
Idempotent replay returns the original sequence. Concurrent writers therefore
cannot create duplicates or gaps. A client-supplied base sequence ahead of the
session is invalid; a stale base is accepted only because Yjs updates are
commutative and the server still applies the next authoritative sequence.

When unsnapshotted count or bytes reaches policy bounds, append returns bounded
backpressure and accepts no new envelope until a covering snapshot is stored.
Reads are paginated and never return an unbounded room history. Presence and
awareness stay ephemeral in the Host transport, are limited to 8192 bytes per
participant, and never enter PHP tables, idempotency responses or audit.

## npm Client Contract

`@peanut-admin/admin/collaboration` exports:

- a `CollaborationEngine` port and Yjs implementation for applying updates,
  encoding state vectors/snapshots, convergence and deterministic disposal;
- a `CollaborationTransport` port and y-websocket implementation for
  connect/disconnect/status/update delivery;
- a framework-neutral `CollaborationRuntime` that composes Host HTTP admission
  and state APIs with engine/transport adapters;
- typed session, lease, update, snapshot, status and safe error contracts.

It exports no product editor, document schema or server. Production transport
requires `wss:` behind the Host's same-origin proxy; only loopback development
may use `ws:`. The adapter does not accept bearer/refresh tokens or arbitrary
secret query parameters. HttpOnly same-site Host authentication and the
server-side internal bridge remain outside browser code. Reconnect always
revalidates through Host admission and never treats cached presence as access.

## Errors, Audit And Security Results

Stable errors are `COLLABORATION_INVALID` (422),
`COLLABORATION_NOT_FOUND` (404), `COLLABORATION_DENIED` (403),
`COLLABORATION_CONFLICT` (409), `COLLABORATION_LEASE_EXPIRED` (409),
`COLLABORATION_PAYLOAD_TOO_LARGE` (413), `COLLABORATION_BACKPRESSURE`
(429), `COLLABORATION_PROVIDER_UNAVAILABLE` (503),
`COLLABORATION_INTEGRITY_FAILURE` (500) and
`COLLABORATION_INTERNAL_ERROR` (500). Kernel idempotency errors remain
unchanged. Errors expose no room membership, document content, token, SQL,
stack trace, other Tenant sequence or hidden artifact existence.

Successful writes audit session opened/joined/heartbeat/update appended/
snapshot saved/published/closed with actor, authorized target, public keys,
sequence, byte count, digest, capability and final revision digest only.
Opaque bytes, state vectors, awareness, Provider inputs and credentials are
never audited.

## Exact Write Sets

This contract commit may change only this file,
`docs/content-status.json`, `docs/status/index.md`,
`docs/status/p1-execution-and-post-q01-roadmap.md` and
`docs/status/p1-post-q01-cross-product-capability-plan.md`.

After it merges, one CAP04 candidate may change only the following groups.

### CAP04-A — PHP schema and persistence

- `packages/php/collaboration/src/Database/Schema.php`;
- `packages/php/collaboration/src/Model/CollaborationSession.php`;
- `packages/php/collaboration/src/Model/CollaborationParticipantLease.php`;
- `packages/php/collaboration/src/Model/CollaborationUpdateEnvelope.php`;
- `packages/php/collaboration/src/Model/CollaborationSnapshotEnvelope.php`;
- `packages/php/collaboration/src/Persistence/CollaborationRepository.php`;
- `packages/php/collaboration/src/Persistence/PdoCollaborationRepository.php`;
- `packages/php/collaboration/tests/Unit/Database/SchemaTest.php`;
- `packages/php/collaboration/tests/Integration/Persistence/PdoCollaborationRepositoryTest.php`.

### CAP04-B — PHP policies, service and ArtifactRevision adapter

- `packages/php/collaboration/src/Package.php`;
- `packages/php/collaboration/src/Contract/CollaborationPolicy.php`;
- `packages/php/collaboration/src/Contract/CollaborationPolicyProvider.php`;
- `packages/php/collaboration/src/Contract/CollaborationSubmission.php`;
- `packages/php/collaboration/src/Contract/CollaborationSubmissionProvider.php`;
- `packages/php/collaboration/src/Contract/CollaborationRevisionPublisher.php`;
- `packages/php/collaboration/src/Application/CollaborationException.php`;
- `packages/php/collaboration/src/Application/CollaborationReceipt.php`;
- `packages/php/collaboration/src/Application/CollaborationState.php`;
- `packages/php/collaboration/src/Application/CollaborationService.php`;
- `packages/php/collaboration/src/ArtifactRevision/ArtifactRevisionCollaborationPublisher.php`;
- `packages/php/collaboration/tests/Integration/Application/CollaborationServiceTest.php`;
- `packages/php/collaboration/tests/Integration/ArtifactRevision/ArtifactRevisionCollaborationPublisherTest.php`.

### CAP04-C — npm UI-neutral client

- `packages/web/collaboration/src/contracts.ts`;
- `packages/web/collaboration/src/engine.ts`;
- `packages/web/collaboration/src/transport.ts`;
- `packages/web/collaboration/src/runtime.ts`;
- `packages/web/collaboration/src/index.ts`;
- `packages/web/collaboration/tests/engine.spec.ts`;
- `packages/web/collaboration/tests/transport.spec.ts`;
- `packages/web/collaboration/tests/runtime.spec.ts`;
- `packages/web/collaboration/tsconfig.json`.

### CAP04-D — dependency and two-package projection wiring

- root `composer.json`, `package.json`, `pnpm-lock.yaml`, `deptrac.yaml` and
  `phpunit.xml`;
- `packages/php/composer.json` and `packages/web/package.json`;
- `starter/frontend/package.json` and `starter/pnpm-lock.yaml`;
- `scripts/check-workspace` and `scripts/check-alpha5-package-projection`;
- `docs/reference/third-party-licenses.generated.md` only through its existing generator.

No Kernel, Workflow, ArtifactRevision implementation, EntitlementQuota,
backend/frontend Host, OpenAPI, generated route, product Module, deployment,
application repository or extra package manifest may change. An insufficient
whitelist requires a separate contract correction.

## Test Ownership And Stop Line

`P1-COLLABORATION-001` owns reentrant schema, Tenant/target isolation, active
session uniqueness, lease expiry/revocation, membership revalidation, ordered
concurrent updates, digest/size checks, idempotent replay, snapshots,
backpressure, publish rollback, ArtifactRevision pinning, audit redaction, Yjs
convergence, secure transport configuration, package exports and isolated
Composer/npm consumers.

Implementation tasks perform static review, exact-write-set inspection and
`git diff --check`. One integration owner runs the repository PR automation on
the final tree. Full fixed-tree cross-capability qualification remains CAP05.
CAP04 does not deploy Hocuspocus, publish Alpha.5, move application locks,
nominate DCS or begin CAP06.
