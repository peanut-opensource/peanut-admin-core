# P1-WF01 Configurable Workflow Runtime Contract

## Status

```text
task: P1-CAP01 / P1-WF01
state: source-candidate
prerequisite_commit: e911406909710480b59d7332de9bc18a365794fa
contract_history: abeb5afa32dee353b13debe08b23575173979d90, f2f4a21d942f6a24e1ed673c67dfb6a72c531c3d, a2a13b633cfdfdaa14aca5f1d917e4f6865597c2
contract_reconciliation_commit: faa126ebcdb4169ef3f0b623ca959fa742808aa7
result_commit: 3972c9aefcd55ac71d07a47739a99d23bb0ae30c
result_tree: d6dbde37907d1dd43b00057fc16fbd1a8d6dd052
implementation_stage: WF01-I combined source candidate complete
public_boundary: peanut-admin/core
target_candidate: peanut-admin/core@0.1.0-alpha.5, Composer-only and unpublished
dependency_change: none
http_api: none in core; the downstream Host owns routes and OpenAPI
runtime_ledger: no core row; the downstream Host must register each real operation as p1
test_owner: P1-WORKFLOW-RUNTIME-001
qualification: deferred to P1-CROSS-PRODUCT-QUALIFICATION-001 / CAP05
downstream_adoption: deferred to CAP06
publication_authorized: false
```

Integration PR #7 is currently blocked by the final PHP aggregate process
termination. The focused diagnosis, exact repair boundary and single repaired
group allowance are owned by
[P1-CAP01-R01](./p1-cap01-r01-quality-process-termination-contract.md). This
does not change the fixed source candidate or advance CAP05 qualification.

This contract is the first executable decision derived from the read-only
media-resource-management evidence. That evidence establishes a need for
versioned approval graphs, immutable submitted subject revisions, human work
items, attachment references, and an append-only action history. It does not
authorize copying the legacy source, fixed three-review fields, weak access
checks, serialized history payloads, media product names, or customer data.

The existing `peanut-admin/core` package remains the only public PHP boundary.
Workflow is an internal source directory and PSR-4 root inside that package,
not a third Composer package.

## CAP01 Release-Line Reconciliation

Peanut Admin application releases and reusable core package releases have
independent version lines. The application `v1.0.0` tag is not a
`peanut-admin/core@1.0.0` or `@peanut-admin/admin@1.0.0` publication. The exact
baseline consumed by that application release is:

| Boundary | Immutable identity used by Peanut Admin v1.0.0 |
| --- | --- |
| Application source | repository `peanut-business/peanut-admin`; annotated tag object `00e57b09ded1720995cd37398b8e86d7ebbd7e62`; peeled commit `0d3c848b8e2bb622a868924145ce810a8946f173`; tree `dc67489a7bb62b67be7d1702dbb5ace9648c4e83` |
| Composer Runtime | `peanut-admin/core@0.1.0-alpha.2`; monorepo source `b0dc376c2147b98522764486342c9525fe5678ce`; generated split commit and Packagist dist reference `330e76787ba754e1c7c11c2204c1c7f1e9560bb1` |
| Admin Web and PC | `@peanut-admin/admin@0.1.0-alpha.3`; source tag commit `4b197fce32432cd195a63a8dcd4d2b0bc3f11a04`; npm SHA-1 `c80aeccb32aa542f55f01e9d58a61dba8a4b67f5` |
| UniApp/H5 | `@peanut-admin/admin@0.1.0-alpha.4`; source tag commit `7fbd445d8fa547830b7782a7ac147d9ed414e0fd`; npm SHA-1 `b237df40068bc8b6ecf8856a02c54c33e3f231af` |

The core package line therefore remains pre-1.0. `0.1.0-alpha.5` is a valid
next Composer candidate number, not a downgrade from the application version.
It identifies only the current unqualified projection. CAP01 source acceptance
at `3972c9a` did not publish it, move a registry tag, change npm, or move any
application lock. CAP05 qualification and a later independent publication
approval must bind the final source, projection digest and registry action
before an external Alpha.5 publication can occur.

## Objective And Non-Goals

P1-WF01 provides a product-neutral Tenant workflow Runtime that:

- publishes immutable versions of a validated directed workflow definition;
- starts an instance for one Host-owned typed subject and immutable subject
  revision;
- creates Tenant-scoped human work items from explicit assignment rules;
- applies declared transitions with optimistic concurrency and idempotency;
- appends an immutable workflow event and the existing Tenant audit evidence in
  the same transaction;
- snapshots approved File/Media attachment references;
- produces typed notification and asynchronous-task intents that a Host adapter
  must publish through the existing Notification/SMS and Task/Job contracts;
- keeps identity, Tenant, organization, permission, file, task, notification,
  audit, transaction, idempotency, and Problem Details authorities in their
  existing owners.

The Host owns its subject schema, business calculations, content, form rules,
workflow templates, additional permission keys, typed-target providers, pages,
routes, OpenAPI, Runtime coverage rows, notification copy, automation handlers,
and subject-side projection. Like P1-R01, WF01 is a framework-neutral package
Runtime and creates no core HTTP operation. Its PHP API is fixed below. A real
downstream HTTP Host must declare every route, audience, request/response shape,
RFC 9457 mapping and `p1` test owner before exposing it; the Peanut Admin Host
slice may not treat this package contract as an implicit route authorization.

P1-WF01 does not add:

- a media article, column, newsroom, audio, video, publishing, broadcast,
  copyright, transcoding, or other product model;
- a fixed three-review sequence or hard-coded review count;
- a universal form builder, BPMN interpreter, script engine, arbitrary code
  execution, expression language, or source generator;
- a second account, Tenant, Department, Role, permission, file, task,
  notification, audit, or idempotency table;
- CRDT, OT, WebSocket, document locking, presence, cursor, editor, or realtime
  gateway implementation;
- delegation, proxy approval, escalation, SLA timers, calendar scheduling,
  cross-Tenant workflow, anonymous approval, or AI approval;
- a reference Host API, Admin Web page, npm change, new public package, stable
  compatibility promise, or SaaS entitlement/quota behavior.

## Existing Owners And Required Reuse

| Concern | Existing authority | WF01 rule |
| --- | --- | --- |
| Identity and Tenant | Kernel `TenantContext`, membership and session contracts | Actor and Tenant come only from a trusted context; request data cannot supply them. |
| Organization | Kernel members, Roles and Departments | Assignment resolution uses a Host adapter backed by these authorities; workflow stores only immutable assignee snapshots and identifiers. |
| Functional/data permission | Kernel `PermissionRequirement` and Data Permission typed targets | Host operation authorization happens before WF01; each transition additionally names a declared Permission requirement and subject target contract. Missing declarations fail closed. |
| Transactions/idempotency | Kernel R01/R02 primitives | Definition publication, instance start and transition commands use one caller-owned PDO and existing scoped idempotency records. |
| Files | File/Media `FileObject` and attachment snapshot semantics | WF01 stores file keys plus immutable name/media-type/size/SHA-256 snapshots returned by an attachment resolver; it never reads a Host file table directly. |
| Human work | WF01 | Human review work items are workflow state, not background jobs. They do not duplicate Task/Job execution leases. |
| Background work | Task/Job `TrustedJobPublisher` | Automation is an explicit post-transition task intent with a registered task type; no inline arbitrary handler. WF01 defines the intent port and the Host owns the real publisher adapter. |
| Notification | Notification/SMS `NotificationService` | A definition may declare a template intent; recipient and attachment resolution remain in the existing service. WF01 defines the intent port and the Host owns a separately authorized producer adapter; it must not forge an `AuthorizationDecision`. |
| Audit | Kernel `PdoAuditRepository` | Every successful write appends one redacted Tenant audit record; workflow events provide domain-neutral transition history, not a second security audit authority. |

The package adapter layer may depend on existing internal namespaces because
they are part of the same Composer package. It must not deep-import a Host,
scan `vendor/`, or copy an existing implementation under a Workflow name.

## Definition And Graph Contract

A workflow is identified by `(tenant_id, module_key, workflow_key)`. The Host
declares `module_key`; module/workflow/node/transition/task/template/resource
and operation keys are 1..64 ASCII characters matching
`^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$`. Permission keys are 3..160 ASCII
characters matching the same segmented lowercase form. Display labels are data
and are not identifiers.

Definitions are edited as drafts. Publishing creates an immutable monotonically
increasing version with a canonical JSON graph digest. An active version is
never edited or deleted. Retiring a definition prevents new instances but does
not alter existing instances. New versions affect only subsequently started
instances.

The graph root contains `contract_version=1`, `subject_resource_key`,
`subject_read_operation`, `subject_start_operation`, non-empty
`start_permission_keys`, and the node/transition collections. The graph
contains:

- exactly one `start` node;
- one or more `review` or `action` nodes;
- one or more `terminal` nodes;
- directed transitions with unique keys, explicit source and target nodes,
  action kind, Permission requirement, optional assignment policy, optional
  notification intent, optional task intent, and whether a human decision is
  required;
- no unreachable node, missing target, duplicate edge key, self-loop or
  transition from a terminal node. A back edge is valid only when it declares
  `return_edge=true` and `max_traversals` from 1 through 100; the instance event
  history is the authoritative traversal counter and exceeding the bound makes
  the edge unavailable.

A review node declares `completion_policy=any|all` and one or more assignment
rules. WF01 supports member, Role, Department, initiator, and previous-actor
rules through `WorkflowAssignmentResolver`. Empty resolution fails before the
instance or transition commits. `all` snapshots the resolved member set and
requires every member; `any` closes sibling pending items after one completion.
Role or Department membership changes do not silently rewrite an existing
work-item snapshot.

The assignment adapter output must correspond to one of the target node's
declared assignment rules. Member source keys are canonical positive decimal
strings; Role and Department keys use the bounded segmented lowercase key
syntax; initiator and previous-actor sources use their declared rule kind and
an internal canonical snapshot key. Non-ASCII, undeclared or mismatched source
output fails before any write.

Return and withdrawal are ordinary declared edges. The engine does not infer
“previous review”, author self-review, skip-level approval, or three-review
semantics. A Host template may model those rules explicitly.

Canonical graph JSON contains exactly the root keys named above plus `nodes`
and `transitions`; unknown keys fail validation. Each node contains exactly
`key`, `type`, `completion_policy` and `assignments`. `type` is
`start|review|action|terminal`; the single start and every action/terminal node
use null completion and an empty assignment list; a review uses `any|all` and
one or more rules. Each rule is exactly `{kind,key}` where `kind` is
`member|role|department|initiator|previous_actor`; key is a positive decimal
member id or bounded Role/Department key for the first three and null for the
last two. The start node has exactly one outgoing non-human edge. Terminal
nodes have none.

Each transition contains exactly `key`, `from`, `to`, `operation`,
`action_kind`, `permission_keys`, `human_required`, `return_edge`,
`max_traversals`, `notification_intent` and `task_intent`. Action kind is
`advance|approve|reject|return|withdraw|automate`; human-required edges may
leave only a review node, while `automate` may leave only an action node.
`max_traversals` is null unless `return_edge=true`. A notification intent is
null or exactly `{template_key,recipient_rule}` with recipient rule
`next_assignees|initiator|actor`; a task intent is null or exactly
`{task_type}`. The Runtime supplies only workflow/instance/event keys and the
pinned subject revision to those adapters; graphs cannot embed arbitrary
payload, template text, recipient addresses, expressions or code.

## Data And Ownership Contract

Workflow owns only the following product-neutral MySQL 8 tables. Every string
identifier uses `CHARACTER SET ascii COLLATE ascii_bin`; every timestamp is
UTC `DATETIME(3)` supplied by the Runtime; every JSON value is canonicalized
before persistence. Unnamed columns, nullable shortcuts and alternative enum
states are not permitted by WF01-A.

`pa_workflow_definition` has: `id BIGINT UNSIGNED AUTO_INCREMENT` primary key;
`tenant_id BIGINT UNSIGNED NOT NULL`; `module_key VARCHAR(64) NOT NULL`;
`workflow_key VARCHAR(64) NOT NULL`; `status VARCHAR(16) NOT NULL DEFAULT
'draft'`; `draft_graph_json JSON NOT NULL`; `draft_graph_sha256 CHAR(64) NOT
NULL`; `latest_version INT UNSIGNED NOT NULL DEFAULT 0`; `revision BIGINT
UNSIGNED NOT NULL DEFAULT 1`; `created_by_member_id` and
`updated_by_member_id BIGINT UNSIGNED NOT NULL`; `created_at` and `updated_at
DATETIME(3) NOT NULL`; and `retired_at DATETIME(3) NULL`. It has unique keys on
`(tenant_id,id)` and `(tenant_id,module_key,workflow_key)`, an index on
`(tenant_id,status,id)`, a Tenant foreign key with `ON DELETE RESTRICT`, and
checks for identifier syntax, the three states, 64-lowercase-hex digest,
positive revision and `retired_at` being non-null exactly for `retired`.
Saving a later draft replaces only the draft JSON/digest and increments the
row revision; it never changes an existing version row.

`pa_workflow_definition_version` has: `id BIGINT UNSIGNED AUTO_INCREMENT`
primary key; `tenant_id BIGINT UNSIGNED NOT NULL`; `definition_id BIGINT
UNSIGNED NOT NULL`; `version INT UNSIGNED NOT NULL`; `graph_json JSON NOT
NULL`; `graph_sha256 CHAR(64) NOT NULL`; `published_by_member_id BIGINT
UNSIGNED NOT NULL`; and `published_at DATETIME(3) NOT NULL`. It has unique keys
on `(tenant_id,id)`, `(tenant_id,definition_id,version)` and
`(tenant_id,definition_id,graph_sha256)`, an index on
`(tenant_id,published_at,id)`, and a composite `ON DELETE RESTRICT` foreign key
to the definition. `version >= 1` and the digest must be lowercase SHA-256.
Rows are insert-only; no Runtime update or delete method exists.

`pa_workflow_instance` has: `id BIGINT UNSIGNED AUTO_INCREMENT` primary key;
`instance_key VARCHAR(64) NOT NULL`; `tenant_id BIGINT UNSIGNED NOT NULL`;
`definition_id BIGINT UNSIGNED NOT NULL`; `definition_version INT UNSIGNED NOT
NULL`; `subject_type VARCHAR(160) NOT NULL`; `subject_key VARCHAR(160) NOT
NULL`; `subject_revision_key VARCHAR(160) NOT NULL`;
`subject_revision_sha256 CHAR(64) NOT NULL`; `current_node_key VARCHAR(64) NOT
NULL`; `status VARCHAR(16) NOT NULL DEFAULT 'active'`;
`initiated_by_member_id BIGINT UNSIGNED NOT NULL`; `last_actor_member_id BIGINT
UNSIGNED NULL`; `revision BIGINT UNSIGNED NOT NULL DEFAULT 1`; `created_at` and
`updated_at DATETIME(3) NOT NULL`; `completed_at` and `cancelled_at DATETIME(3)
NULL`; and `active_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status =
'active' THEN 1 ELSE NULL END) STORED`. It has unique keys on `instance_key`,
`(tenant_id,id)`, and `(tenant_id,definition_id,subject_type,subject_key,
active_marker)`; indexes on `(tenant_id,status,updated_at,id)` and
`(tenant_id,subject_type,subject_key,id)`; a Tenant foreign key; and a composite
foreign key to the fixed definition version. Checks require the
`instance_[0-9a-f]{32}` key, positive revision/version, a valid digest, and
exactly one terminal timestamp for `completed|cancelled` and none for `active`.
MySQL's nullable generated marker permits historical terminal instances while
enforcing one active instance for the same definition and subject.

`pa_workflow_work_item` has: `id BIGINT UNSIGNED AUTO_INCREMENT` primary key;
`work_item_key VARCHAR(64) NOT NULL`; `tenant_id BIGINT UNSIGNED NOT NULL`;
`instance_id BIGINT UNSIGNED NOT NULL`; `node_key VARCHAR(64) NOT NULL`;
`round_no INT UNSIGNED NOT NULL`; `assignment_source_kind VARCHAR(24) NOT NULL`;
`assignment_source_key VARCHAR(160) NOT NULL`; `assignee_member_id BIGINT
UNSIGNED NOT NULL`; `status VARCHAR(16) NOT NULL DEFAULT 'pending'`; `decision
VARCHAR(64) NULL`; `completed_by_member_id BIGINT UNSIGNED NULL`; `revision
BIGINT UNSIGNED NOT NULL DEFAULT 1`; `created_at` and `updated_at DATETIME(3)
NOT NULL`; `completed_at` and `cancelled_at DATETIME(3) NULL`; and
`pending_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN
1 ELSE NULL END) STORED`. It has unique keys on `work_item_key`,
`(tenant_id,id)`, and `(tenant_id,instance_id,node_key,round_no,
assignee_member_id,pending_marker)`; indexes on `(tenant_id,assignee_member_id,
status,created_at,id)` and `(tenant_id,instance_id,status,id)`; and a composite
`ON DELETE RESTRICT` foreign key to the instance. Checks require the
`work_[0-9a-f]{32}` key, `round_no/revision >= 1`, source kind in
`member|role|department|initiator|previous_actor`, state in
`pending|completed|cancelled`, and completion actor/decision/time only for
`completed`, cancellation time only for `cancelled`. Member, Role and
Department identifiers are immutable logical snapshots validated through their
owners before insert; no cross-owner foreign key is added.

`pa_workflow_event` has: `id BIGINT UNSIGNED AUTO_INCREMENT` primary key;
`tenant_id BIGINT UNSIGNED NOT NULL`; `instance_id BIGINT UNSIGNED NOT NULL`;
`sequence_no INT UNSIGNED NOT NULL`; `event_key VARCHAR(96) NOT NULL`;
`transition_key VARCHAR(64) NULL`; `from_node_key VARCHAR(64) NULL`;
`to_node_key VARCHAR(64) NOT NULL`; `actor_type VARCHAR(16) NOT NULL`;
`actor_member_id BIGINT UNSIGNED NULL`; `subject_revision_key VARCHAR(160) NOT
NULL`; `subject_revision_sha256 CHAR(64) NOT NULL`; `comment_text VARCHAR(2000)
NULL`; `comment_sha256 CHAR(64) NULL`; `attachment_snapshots_json JSON NOT
NULL`; `metadata_json JSON NOT NULL`; and `occurred_at DATETIME(3) NOT NULL`.
It has unique `(tenant_id,instance_id,sequence_no)`, indexes on
`(tenant_id,instance_id,occurred_at,id)` and `(tenant_id,event_key,occurred_at,
id)`, and a composite `ON DELETE RESTRICT` foreign key to the instance. Checks
require `sequence_no >= 1`, an event key matching
`^tenant\\.workflow\\.[a-z_]+$`, valid digests, comment and comment digest both
null or both present, and actor shape `member` with a member id or
`tenant_system` without one. Rows are append-only.

Definition state is `draft -> active -> retired`; an active definition may
publish another version and remains active. Instance state is `active ->
completed|cancelled`; a transition never reopens a terminal instance. Work-item
state is `pending -> completed|cancelled`; it never returns to pending. The
engine enforces these transitions under `SELECT ... FOR UPDATE` plus
`expected_revision`; database checks protect stored shape.

The schema is shipped as `PeanutAdmin\\Workflow\\Database\\Schema`, the single
package-owned source used by Host migrations and tests. A Host migration runner
executes `Schema::createSql()` in declared table order and records its digest;
it must not transcribe or fork the schema. Clean install, upgrade from Composer
Alpha.2, idempotent re-entry and exact table/index/check comparison belong to
qualification. `dropSql()` exists for isolated tests only. Production rollback
does not invoke it: adoption leaves inert additive tables and uses forward
recovery. Runtime exposes no physical delete or purge; retention is a later
contract.

## PHP Command And Query Contract

The public Composer projection exposes
`PeanutAdmin\\Workflow\\Application\\WorkflowRuntime`. Every method receives
an existing caller-owned `PDO` through
construction, and every actor-facing method receives a Kernel
`AuthorizedOperationContext`; no scalar Tenant, member or account id is
accepted from a Host request. The exact command surface is:

```text
saveDraft(context, moduleKey, workflowKey, graph, expectedRevision, idempotencyKey): WorkflowReceipt
publishDefinition(context, moduleKey, workflowKey, expectedRevision, idempotencyKey): WorkflowReceipt
retireDefinition(context, moduleKey, workflowKey, expectedRevision, idempotencyKey): WorkflowReceipt
startInstance(context, moduleKey, workflowKey, subjectType, subjectKey,
              subjectRevisionKey, attachmentFileKeys, idempotencyKey): WorkflowReceipt
applyTransition(context, instanceKey, transitionKey, expectedInstanceRevision,
                expectedSubjectRevisionKey, comment, attachmentFileKeys,
                idempotencyKey): WorkflowReceipt
applyAutomation(context, instanceKey, transitionKey, expectedInstanceRevision,
                expectedSubjectRevisionKey, parentJobKey): WorkflowReceipt
```

`saveDraft` creates or replaces only the mutable draft after graph validation;
`expectedRevision` is null only when the definition does not yet exist.
`publishDefinition` locks the definition, requires its expected revision,
inserts `latest_version + 1`, and activates that immutable version.
`retireDefinition` is idempotent only through the supplied key and changes
`active` to `retired`; draft-only definitions cannot retire and retired ones
cannot save or publish. `startInstance` resolves the active version and Host
subject revision, snapshots ready Tenant files and first-node assignees, then
creates the instance, sequence-one event and work items. `applyTransition`
locks the instance and pending work-item set, revalidates both revisions and
dynamic authorization, then closes/creates items and appends the next event.
`applyAutomation` accepts only the Task/Job worker's revalidated
`AuthorizedOperationContext`, a registered non-human edge and a
`job_[0-9a-f]{32}` parent. The original submitting member remains in the
authorization basis, while the workflow event and Tenant audit actor are
`tenant_system`; the worker can never satisfy a review item.

Every command requires an idempotency key, acquires the existing scoped Kernel
idempotency store inside the command transaction, and uses the operation id
`workflow.save-draft`, `workflow.publish-definition`,
`workflow.retire-definition`, `workflow.start-instance`,
`workflow.apply-transition`, or `workflow.apply-automation`. The request hash
is canonical JSON of all semantic inputs excluding request id and the raw key.
The stored `WorkflowReceipt` contains exactly: `operation`, nullable
`definition_id`, nullable `definition_version`, nullable `instance_key`,
nullable `instance_status`, nullable `current_node_key`, nullable
`instance_revision`, nullable `event_sequence`, and the sorted new
`work_item_keys`. It contains no graph, subject key, comment, attachment,
recipient or authorization data. Exact replay returns that receipt and creates
no second version, event, work item, notification, task or audit row; a reused
key with another request hash uses `IDEMPOTENCY_KEY_REUSED`.

`WorkflowQueryService` exposes read-only `definition(context,moduleKey,
workflowKey)`, `definitionDraft(writeContext,moduleKey,workflowKey)`,
`definitions(context,status,page,pageSize)`,
`instance(context,instanceKey)`, `workItems(context,instanceKey,status,page,
pageSize)` and `events(context,instanceKey,afterSequence,pageSize)`. Page is
positive, `pageSize` is 1..100, `afterSequence >= 0`; sort order is stable
`id ASC` except definitions (`id DESC`). Definition reads return identity,
status, draft revision/digest, latest version and version metadata but not raw
graph; only `definitionDraft` returns raw draft graph after a separately
authorized definition-write context. Instance reads
return workflow/version, opaque subject type/key, pinned revision, state,
current node and revision. Work-item and event reads expose comments and
attachment snapshots only after the Host visibility adapter confirms the same
subject target. Cross-Tenant and invisible rows are the same not-found result.
Queries create no workflow or idempotency row; the Host owns any audit required
for a sensitive read operation.

All command effects use one caller-owned PDO and one R01 transaction. A failure
at definition/instance/work-item, event, existing Tenant audit, notification
outbox, task publication or idempotency completion rolls back every effect. A
resolver or publisher on another PDO is rejected before the first write; no
cross-connection atomicity is claimed.

## Permission, Audit, Side Effects And Security

WF01 introduces no super-user switch and no default allow. All requirements use
Tenant audience and `match=all`. The package resource/operation mapping is:

| Runtime call | Resource / operation | Required package key | Typed target |
| --- | --- | --- | --- |
| `definition(s)` | `peanut.workflow.definition/read` | `peanut.workflow.definition.read` | none |
| `saveDraft` | `peanut.workflow.definition/write` | `peanut.workflow.definition.write` | none |
| `publishDefinition` / `retireDefinition` | `peanut.workflow.definition/publish` | `peanut.workflow.definition.publish` | none |
| `instance/workItems/events` | graph `subject_resource_key/read` | `peanut.workflow.instance.read` plus Host read keys | `one_required`, Host subject key |
| `startInstance` | graph `subject_resource_key/start` | `peanut.workflow.instance.start` plus graph `start_permission_keys` | `one_required`, Host subject key |
| `applyTransition` | graph `subject_resource_key/<transition.operation>` | `peanut.workflow.instance.transition` plus the edge permission keys | `one_required`, pinned Host subject key |
| `applyAutomation` | graph subject resource and declared automation operation | edge permission keys and a registered task context; never a human package key | `one_required`, pinned Host subject key |

The graph therefore declares `subject_resource_key`, a Host read operation,
start operation and package-independent `start_permission_keys`; each edge
declares its Host operation and additional keys. Empty Host operation,
permission or typed-target declarations fail graph validation. The
`WorkflowAuthorizationResolver` must evaluate Kernel functional RBAC first and
Data Permission `decideTargets()` second for exactly the context Tenant and one
subject key, returning an `AuthorizedOperationContext`. Runtime checks Tenant,
resource, operation and target equality. The Host adapter is forbidden to call
`AuthorizationDecision::allow()` directly; qualification inspects the adapter
and requires real evaluator evidence. Runtime rejects an empty target set,
query-only authorization or a context for another instance. Definition
administration is target-free but still receives an authorized package
context. Cross-Tenant subjects, attachments, assignees and targets are
identical non-enumerating failures.

Each successful command appends exactly one existing Tenant audit row:

| Command | Event type | Action | Actor / target |
| --- | --- | --- | --- |
| `saveDraft` | `tenant.workflow.definition.draft_saved` | `peanut.workflow.definition.write` | `appendTenantMember`; target `workflow_definition`, numeric definition id |
| `publishDefinition` | `tenant.workflow.definition.published` | `peanut.workflow.definition.publish` | `appendTenantMember`; target `workflow_definition`, numeric definition id |
| `retireDefinition` | `tenant.workflow.definition.retired` | `peanut.workflow.definition.publish` | `appendTenantMember`; target `workflow_definition`, numeric definition id |
| `startInstance` | `tenant.workflow.instance.started` | `peanut.workflow.instance.start` | `appendTenantMember`; target `workflow_instance`, instance key |
| `applyTransition` | `tenant.workflow.instance.transitioned` | `peanut.workflow.instance.transition` | `appendTenantMember`; target `workflow_instance`, instance key |
| `applyAutomation` | `tenant.workflow.instance.automated` | declared automation operation | `appendTenantSystem`; target identity is represented only by redacted metadata |

Definition audit metadata contains revision, version and graph digest. Instance
audit metadata contains definition id/version, from/to node, transition and a
SHA-256 digest of `subject_type|subject_key|subject_revision_key`; automation
also includes the parent job key digest. `before`/`after` values are absent.
Member commands use `target_count=1`. `applyAutomation` reuses the existing
`appendTenantSystem` authority, which has no target fields and therefore
persists its schema default `target_count=0`; its redacted subject digest
remains in metadata and WF01 does not widen the Kernel audit API. Audit
metadata never includes raw content, comments,
filenames, file keys, recipient addresses, raw subject/target ids,
authorization inputs, credentials, SQL, stack traces or private paths. The
workflow event may retain the bounded human comment and approved attachment
snapshots for business traceability, but serializers apply the Host visibility
decision above. Expected denial is appended only by the Host after the command
transaction rolls back, using `AuditOutcome::Denied`; unexpected error uses a
new explicit post-rollback transaction and `AuditOutcome::Error`. Neither may
commit beside a partial workflow effect.

`WorkflowSideEffectPublisher::publish(PDO, AuthorizedOperationContext,
WorkflowTransitionEffects, parentIdempotencyKey)` receives the same PDO and the
already authorized transition context. Effects are immutable and canonically
ordered. Task child keys are exactly
`wf:<instance_key>:<event_sequence>:task:<zero_based_index>` and notification
child keys replace `task` with `notification`; the corresponding request hash
is canonical JSON of the typed intent. A Task provider must bind to the same
Host subject resource/operation before `TrustedJobPublisher::publish()` accepts
the transition context. A Notification adapter additionally requires a real,
separately evaluated `peanut.notification-sms/manage`
`AuthorizedOperationContext` for the same trusted Tenant; it may not construct
or forge that decision. Until such an adapter is contracted by a real Host, a
definition containing a notification intent fails closed with provider
unavailable. Missing provider, mismatched PDO/context or child-key collision
rolls back the transition. Exact parent replay never calls the publisher.

Human-required transitions accept only an active Tenant member resolved from
trusted context. An AI, service credential, background worker or anonymous
actor cannot approve, reject or satisfy an `all` review item. Explicit
non-approval automation edges may be executed by a registered task handler and
are audited as system actions.

Package exceptions fix a stable code and suggested HTTP mapping; the core
package itself returns no HTTP response. A downstream Host maps them through
the existing `ProblemDetailsAdapter` to RFC 9457
`application/problem+json`, preserves `X-Request-Id`, sends
`Cache-Control: no-store`, and never includes adapter details:

| Code | Status / header rule |
| --- | --- |
| `WORKFLOW_DEFINITION_INVALID` | 422; no retry header |
| `WORKFLOW_PRECONDITION_REQUIRED` | 428; missing expected revision |
| `WORKFLOW_DEFINITION_CONFLICT` | 409; current revision may be returned only as a quoted `ETag` after same-Tenant authorization |
| `WORKFLOW_DEFINITION_RETIRED` | 409; no retry header |
| `WORKFLOW_INSTANCE_CONFLICT` | 409; same safe `ETag` rule |
| `WORKFLOW_TRANSITION_UNAVAILABLE` | 409; no graph internals |
| `WORKFLOW_ASSIGNMENT_DENIED` | 403 for a visible instance; otherwise the not-found shape |
| `WORKFLOW_SUBJECT_NOT_FOUND` | 404; identical for unknown, invisible and cross-Tenant subjects |
| `WORKFLOW_SUBJECT_REVISION_CONFLICT` | 409; no raw revision or document digest |
| `WORKFLOW_ATTACHMENT_UNAVAILABLE` | 404; identical for archived, unknown and cross-Tenant files |
| `WORKFLOW_PROVIDER_UNAVAILABLE` | 503; `Retry-After` only when the Host has a bounded provider retry policy |

Existing authorization and idempotency codes remain unchanged. Unknown adapter,
PDO, JSON or database exceptions become `INTERNAL_ERROR` 500 after rollback;
they never reveal SQL, table names, graph JSON, target existence or stack
traces. Every public command and query entry point enforces this mapping;
persisted graph corruption is an internal error rather than a caller's 422
definition error.

## Realtime Collaboration Interface Freeze

WF01 does not select or implement CRDT or OT. It freezes only the boundary
between collaborative editing and approval:

- `WorkflowSubjectRevisionResolver` returns a Host-issued immutable revision
  key and digest for a typed subject;
- starting or transitioning an instance pins that revision;
- a human decision supplies the expected pinned revision and fails if the Host
  says the subject has advanced;
- a transition receipt returns the accepted subject revision and workflow
  instance revision;
- collaboration sessions, mutable drafts, presence and merge state never enter
  Workflow tables or audit metadata.

This permits a later collaboration implementation to choose CRDT or OT without
changing workflow authority. The Host remains responsible for creating an
immutable submitted revision before approval.

## Exact Write Sets

This CAP01 reconciliation commit may change only:

- this file;
- `docs/status/p1-post-q01-cross-product-capability-plan.md`;
- `docs/status/p1-execution-and-post-q01-roadmap.md`;
- `docs/status/index.md`;
- `README.md`.

After the contract commit, development uses one independently reviewable
`WF01-I` implementation commit. The former A Runtime classes require the B
adapter interfaces, while the B value objects require the A exception and
instance types, so neither set is an independently loadable projection. They
remain separate review groups below but form one combined exact whitelist. The
integration owner may narrow but must not silently widen the combined set.
The accepted source candidate contains exactly forty paths and is fixed by
commit `3972c9aefcd55ac71d07a47739a99d23bb0ae30c` and tree
`d6dbde37907d1dd43b00057fc16fbd1a8d6dd052`. No status, release, guide or
generated-license document belongs to WF01-I.

### WF01-A — schema, definition and instance core

- `packages/php/workflow/src/Database/Schema.php`;
- `packages/php/workflow/src/Package.php`;
- `packages/php/workflow/src/Application/WorkflowException.php`;
- `packages/php/workflow/src/Application/WorkflowQueryService.php`;
- `packages/php/workflow/src/Application/WorkflowReceipt.php`;
- `packages/php/workflow/src/Application/WorkflowRuntime.php`;
- `packages/php/workflow/src/Definition/WorkflowDefinition.php`;
- `packages/php/workflow/src/Definition/WorkflowDefinitionVersion.php`;
- `packages/php/workflow/src/Definition/WorkflowGraph.php`;
- `packages/php/workflow/src/Definition/WorkflowNode.php`;
- `packages/php/workflow/src/Definition/WorkflowTransition.php`;
- `packages/php/workflow/src/Instance/WorkflowEvent.php`;
- `packages/php/workflow/src/Instance/WorkflowInstance.php`;
- `packages/php/workflow/src/Instance/WorkflowWorkItem.php`;
- `packages/php/workflow/src/Persistence/WorkflowRepository.php`;
- `packages/php/workflow/src/Persistence/PdoWorkflowRepository.php`;
- `packages/php/workflow/tests/Unit/Definition/WorkflowGraphTest.php`;
- `packages/php/workflow/tests/Unit/Database/SchemaTest.php`;
- `packages/php/workflow/tests/Integration/Persistence/PdoWorkflowRepositoryTest.php`;
- `packages/php/workflow/tests/Integration/Application/WorkflowRuntimeTest.php`;
- `packages/php/composer.json` to register the Workflow PSR-4 root and set the
  source candidate to `0.1.0-alpha.5`; root `composer.json`, `deptrac.yaml` and
  `phpunit.xml` only to register the new namespace/source/test layer;
- `scripts/check-alpha5-package-projection` and
  `.github/workflows/alpha5-composer-projection-preflight.yml` for the exact
  Composer-only candidate projection;
- `scripts/check-workspace` only to include the new internal directory in
  `peanut-admin/core` and align its exact public Composer candidate assertion
  from `0.1.0-alpha.2` to `0.1.0-alpha.5`;

### WF01-B — existing-capability ports and failure atomicity

- `packages/php/workflow/src/Adapter/WorkflowAssignmentResolver.php`;
- `packages/php/workflow/src/Adapter/WorkflowAuthorizationResolver.php`;
- `packages/php/workflow/src/Adapter/WorkflowSubjectRevisionResolver.php`;
- `packages/php/workflow/src/Adapter/WorkflowAttachment.php`;
- `packages/php/workflow/src/Adapter/WorkflowAttachmentResolver.php`;
- `packages/php/workflow/src/Adapter/WorkflowNotificationIntent.php`;
- `packages/php/workflow/src/Adapter/WorkflowTaskIntent.php`;
- `packages/php/workflow/src/Adapter/WorkflowSideEffectPublisher.php`;
- `packages/php/workflow/src/Adapter/WorkflowTransitionEffects.php`;
- `packages/php/workflow/tests/Unit/Adapter/WorkflowAttachmentTest.php`;
- `packages/php/workflow/tests/Integration/Application/WorkflowCapabilityCompositionTest.php`;
- `packages/php/testing/src/Workflow/WorkflowAtomicityContractHarness.php`;
- `packages/php/testing/tests/Unit/Workflow/WorkflowAtomicityContractHarnessTest.php`;
- root `composer.json`, `packages/php/composer.json` and `phpunit.xml` only if
  required to autoload the exact new files above;

WF01-B does not change Kernel, File/Media, Task/Job or Notification/SMS source.
If a real Host cannot implement a safe adapter using their public contracts, a
separate integration contract must name the exact missing source files and
security behavior before any existing package changes.

### CAP05 qualification and separate publication

WF01-I creates only a source candidate. Its executable evidence remains owned
by `P1-WORKFLOW-RUNTIME-001`, but the run is deferred to the fixed aggregate
CAP05 contract and record owned by `P1-CROSS-PRODUCT-QUALIFICATION-001`.
Qualification must not add missing Runtime behavior or silently widen the
candidate.

After CAP05 and CAP06, a separate publication contract may select Alpha.5 or a
later valid version, bind the exact source/projection commits and digests, and
set `publication_authorized: true`. Only then may an external action mutate
Packagist, the generated Composer split repository, an immutable tag and a
GitHub prerelease. Qualification and private adoption do not authorize that
action.

No npm manifest, version, content, dist-tag or projection may change unless a
separate Web Workflow contract is accepted.

If implementation needs a file outside the selected set, it stops for an
independent contract correction before that file changes.

## Test Ownership And Qualification

`P1-WORKFLOW-RUNTIME-001` owns all executable evidence. WF01-I must add the
complete focused test corpus named by the acceptance list; CAP05 records and
runs that fixed corpus but is not authorized to add missing behavior tests.
Under the repository policy, development runs only static review, exact
write-set inspection and `git diff --check`. One fixed-candidate qualification owner then
runs each group once:

1. Workflow definition/graph and state-machine unit tests;
2. MySQL clean install, Alpha.2 upgrade, idempotent migration and Tenant
   isolation integration tests;
3. R01/R02 transaction, idempotency, permission, typed-target and audit
   composition tests with failure injection at every write checkpoint;
4. File snapshot, human assignment, Notification/SMS outbox and Task/Job intent
   adapter tests, including missing-provider fail-closed behavior;
5. package projection and isolated PHP 8.3 registry-consumer tests;
6. the existing fixed-candidate aggregate, security, recovery, performance,
   documentation, license and repository-tail guards exactly once.

Acceptance requires at least:

- sequential, return, withdrawal and `any|all` review graphs without a fixed
  number of stages;
- immutable active versions and old instances remaining pinned after a new
  version publishes;
- one winner under concurrent transition attempts and no duplicate side
  effects under exact replay;
- cross-Tenant subject, actor, Role, Department, attachment and target denial;
- stale workflow or subject revision rejection before any state change;
- human-only approval and a negative AI/service-actor case;
- one-PDO rollback after every domain/event/audit/notification/task/idempotency
  checkpoint;
- no product-specific term, table, permission, page, route or example in the
  package projection;
- a clean Composer consumer installing the immutable candidate projection;
  registry installation is a later publication probe only.

## Stop Line

Reconciliation commit `faa126ebcdb4169ef3f0b623ca959fa742808aa7` supplied the
precise contract used by the implementation candidate. The coupled former A/B
groups landed as the single WF01-I source commit
`3972c9aefcd55ac71d07a47739a99d23bb0ae30c`.
Development completion is not qualification. CAP05 qualification does not
publish, and CAP06 private adoption does not publish. Publication of
`peanut-admin/core@0.1.0-alpha.5` or another later approved candidate, its
tag/Release and Packagist update occur only after an explicit publication
record binds the exact source/projection commits and digests.

The Peanut Admin application may move from its fixed Alpha.2 lock only during
CAP06, using the exact CAP05-qualified projection or a later immutable registry
artifact bound to the same qualified tree.
It must provide a real ThinkPHP Host, one product-owned example definition,
documentation and one minimum business acceptance while deleting any duplicate
Workflow Runtime. The media project source snapshots remain read-only, its
planned code repository remains `exists: false`, realtime collaboration stays
interface-only, entitlement/quota/cost attribution stays deferred, and SaaS01
does not start under this contract.
