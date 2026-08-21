# P1 Execution Baseline

## Decision

P1 starts from repository commit
`c63e06e25e35855cfefab890d7ee43c6e0cf839d`. That commit records the
qualified P0 foundation and the separately qualified external-host path. It is
the planning input, not a new downstream-consumption lock.

The existing P0 Runtime is inherited. P1 adds market-usable administration and
developer capabilities without renaming, duplicating, or weakening P0 identity,
Tenant, membership, authorization, Module, audit, API, and Admin Web contracts.

At this baseline:

- 75 OpenAPI operations are classified as `p0`;
- no Runtime operation is classified as `p1`;
- no P1 dependency has been approved merely because a capability is listed here;
- no P1 commit is qualified for downstream consumption, package publication, a
  release tag, or a production-readiness claim.

## Current Verification Policy

This document preserves the exact contracts and acceptance evidence used by
the original P1 slices. Those historical task-specific check lists are not a
global requirement to run every repository gate after every later change.

Current Starter v1 development follows the layered policy in
[`docs/guide/testing.md`](../guide/testing.md): ordinary bounded work runs
focused checks; security-sensitive boundaries run risk-proportionate checks in
the task; full regression, browser, install and upgrade, backup and restore,
performance, and fixed-commit review run against a fixed milestone candidate.
No layer may weaken fail-closed behavior or turn an unqualified commit into a
downstream consumption baseline.

## Ownership Boundary

Peanut Admin owns reusable administration infrastructure. Product applications
own their domain entities, workflows, calculations, fulfillment rules, and
operational state. A downstream application may consume the contracts below,
but its domain tables, APIs, permissions, pages, and examples do not move into
the Kernel, reusable packages, starter, or reference Modules.

P1 may provide:

- account self-service, credentials, invitations, and security controls;
- Tenant and platform administration enhancements;
- effective access inspection and time-bounded authorization;
- settings, reference-code, file, notification, job, and import/export
  infrastructure;
- signed integration credentials, Webhooks, extension delivery, generation,
  documentation, packaging, and upgrade tooling.

P1 does not provide application-specific catalogs, stock state, ordering,
fulfillment, settlement, customer programs, or other product-domain Runtime.

## Inherited P0 Capability

The following are complete P0 inputs and are not P1 rebuild tasks:

| Area | Inherited capability |
| --- | --- |
| Identity | Email credential, global uniqueness, account state, secure password hashing |
| Session | Tenant and platform audiences, rotating refresh, revocation, trusted context |
| Tenant | Tenant lifecycle, member lifecycle, explicit tenant selection and switching |
| Organization | Department tree, members, multiple roles |
| Authorization | Functional RBAC, typed targets, data policies, Provider contracts |
| Module | Manifest, migration ownership, installation, Tenant enablement, guards |
| Navigation | Manifest projection, permission-filtered menus, route contribution |
| Audit | Tenant, platform, authentication, and security event persistence and query |
| API | Versioned OpenAPI, Problem Details, request correlation, idempotency contracts |
| Web | Tenant and platform shells, protected transport, context lifecycle |
| Operations | CLI install, upgrade, health, backup, restore, recovery, and qualification gates |

Any P1 task that overlaps this table must identify a concrete missing behavior.
It must extend the current contract instead of creating a parallel model.

## Capability Classification

| Capability group | Classification | Entry condition |
| --- | --- | --- |
| Account profile, password change, device/session view | P1 wave 1 | No new dependency; security event and cross-audience revocation defined |
| Phone and multiple credentials | P1 wave 2 | Verification-channel and identifier-conflict decisions accepted |
| Password recovery | P1 wave 2 | One-time-token, channel, abuse-control, and support policy accepted |
| Member invitations | P1 wave 2 | Invitation state machine and delivery adapter accepted |
| MFA and OIDC | P1 wave 3 | Separate dependency and protocol decisions accepted |
| Tenant domain resolution and custom domains | P1 wave 3 | Verification, certificate, routing, and failure policy accepted |
| Tenant plan and quota | P1 wave 2 | Entitlement authority and enforcement ownership accepted |
| Platform support sessions | P1 wave 3 | Explicit consent, duration, scope, display, revocation, and audit accepted |
| Positions | P1 wave 2 | Display-only versus authorization semantics fixed |
| Effective access preview | P1 wave 1 | Existing RBAC and data-policy semantics remain authoritative |
| Temporary authorization | P1 wave 2 | Expiry, approval, conflict, revocation, and audit semantics fixed |
| Visual menu preferences | P1 wave 2 | Manifest remains the deployable source; tenant preferences cannot invent routes |
| Settings and reference codes | P1 wave 1 | Independent Module ownership and scope precedence fixed |
| File and media management | P1 wave 2 | Storage dependency and malware/content policy accepted |
| Notifications and message center | P1 wave 2 | Channel adapters, template ownership, retry, and retention accepted |
| Application error view | P1 wave 2 | Structured source, redaction, access, and retention fixed |
| Scheduler and queue management | P1 wave 2 | Runtime dependency and trusted task-context reuse accepted |
| Import and export | P1 wave 2 | File, task, authorization, row-error, and retention contracts accepted |
| Backup administration API | P1 wave 3 | External backup authority and restoration verification accepted |
| Extension lifecycle and signing | P1 wave 3 | Artifact, trust, compatibility, rollback, and uninstall contracts accepted |
| Code generator | P1 wave 3 | Generated ownership, upgrade boundary, and fixture contract accepted |
| API tokens and machine identity | P1 wave 2 | Audience, scope, rotation, expiry, and audit semantics accepted |
| Webhooks | P1 wave 2 | Signature, retry, idempotency, ordering, and delivery log accepted |
| Public packages and complete documentation | P1 wave 3 | Versioning, compatibility, release, and support policy accepted |
| Marketplace, fleet control, commercial licensing | P2 | Separate product and operations decision required |
| Product-domain Runtime | Excluded | Owned by downstream application Modules |

## Task Graph

```text
P1-B00 execution baseline
  -> P1-B01 account self-service
  -> P1-B02 effective access preview
  -> P1-B03 settings Module
  -> P1-B04 reference-code Module

P1-B01 -> P1-C01 multiple credentials -> P1-C02 recovery and invitations
P1-B02 -> P1-C03 temporary authorization and support-session design
P1-B03 + P1-B04 -> P1-C04 Admin Web contribution and generation contracts

dependency decisions -> file adapter -> media, import/export, notification attachments
dependency decisions -> task adapter -> scheduler, queue, import/export, delivery retry
credential decisions -> API tokens and machine identity -> Webhooks

all accepted slices -> P1-D01 clean install and upgrade
                   -> P1-D02 recovery, browser, security, and performance
                   -> P1-D03 fixed-commit review
```

Tasks sharing migrations, generated OpenAPI artifacts, Runtime coverage, fixed
database names, service names, or ports are integrated serially. Read-only
design review and disjoint source work may run in parallel, but aggregate gates
run exclusively.

## Required Task Contract

Every Runtime task must include all of the following before implementation:

1. Objective and non-goals.
2. Exact prerequisite commit.
3. Exact file whitelist and files that must not change.
4. Table fields, nullability, defaults, indexes, unique constraints, check
   constraints, state transitions, retention, and deletion behavior.
5. Migration owner and clean-install/upgrade/rollback evidence.
6. API method, path, request, response, OpenAPI schema, headers, and audience.
7. Permission keys and data-policy behavior. Self-only operations must derive
   the account from authenticated context and must not accept an account ID.
8. Audit/security event names, actor, target, before/after policy, and redaction.
9. Idempotency, concurrency, precondition, retry, and duplicate-request behavior.
10. Stable Problem Details codes without secret, token, or account-enumeration leaks.
11. A failing test added before behavior when Runtime behavior changes, a named
    Runtime coverage owner, risk-proportionate focused checks,
    `git diff --check`, and explicit deferred milestone verification. A fixed
    milestone, qualification, or release candidate additionally runs
    `./scripts/check` and its full candidate gate.
12. A single independently reviewable commit and an explicit qualification stop line.

## P1-B01: Account Self-Service Contract

The first Runtime slice is tenant-audience account self-service. It is selected
because it closes a visible market gap, requires no new dependency, and remains
strictly self-scoped.

### Prerequisite Commits

- The original implementation task starts from
  `30f1fb8579d7a5fdec814b71c87b4b9c4617279f`.
- The review-correction task starts from
  `d612a85045e2e9eb017719cd42a2f781d35b1f69` and may only close the
  request-validation, password-verification, rate-limit, tenant-switch, cookie,
  and account-page findings listed below.

### Objective

- Read the authenticated account profile.
- Update `display_name` and `avatar_uri` for the authenticated account.
- Change the active email-password secret after verifying the current secret.
- Revoke all tenant and platform sessions and unconsumed login challenges for
  the account after a secret change.
- Expose the behavior in the existing account page.

Platform-audience self-service, device/session listing, phone credentials,
recovery, invitation, MFA, and external identity providers are later tasks.

### Data Contract

No new table is introduced.

| Table | Change |
| --- | --- |
| `pa_account` | Profile update changes `display_name`, nullable `avatar_uri`, and `updated_at`. Secret change increments `security_revision` and updates `updated_at`. |
| `pa_credential` | Active email credential receives a new `secret_hash`, reset failure state, new `secret_changed_at`, incremented `revision`, and updated timestamp. |
| `pa_tenant_session` | Active sessions for the account become `revoked` with reason `credential_changed`. |
| `pa_platform_session` | Active sessions for the account become `revoked` with reason `credential_changed`. |
| session token tables | Active tokens for revoked sessions become `revoked`; token material is never persisted or audited. |
| `pa_login_challenge` | Active tenant-login and tenant-switch challenges for the account become `revoked`. |
| `pa_tenant_audit_event` | Records `account.profile.changed` and successful `account.password.changed` actions with context-derived actor and target identifiers. |
| `pa_auth_security_event` | Records `password_change_denied`, `password_change_rate_limited`, or `password_changed` with redacted metadata. |

`display_name` is trimmed UTF-8 text from 1 to 120 characters. `avatar_uri` is
either null or an absolute `https` URI no longer than 512 characters. File
upload is not part of this task. A new password is 12 to 128 bytes, differs from
the current password, and is hashed by the existing password service.

### API Contract

| Operation | Contract |
| --- | --- |
| `getMyAccount` | `GET /api/v1/account`; authenticated tenant audience; no permission key; returns account profile and active credential metadata without hashes. |
| `updateMyAccount` | `PATCH /api/v1/account`; authenticated tenant audience; no account ID, no permission key; body contains `display_name` and `avatar_uri`; returns the updated profile. |
| `changeMyPassword` | `POST /api/v1/account/password`; authenticated tenant audience; no account ID, no permission key; body contains `current_password`, `new_password`; returns 204 and clears the current refresh cookie. |

Profile update is naturally repeatable and does not use the generic idempotency
store. Password change is intentionally non-idempotent: replay after success
fails current-secret verification. The service runs each write in one database
transaction. Account identity comes only from validated tenant context.

Current-password verification is limited to five denied attempts per account or
source IP in a rolling 15-minute window. A later attempt in that window does not
perform password hashing, records `password_change_rate_limited`, and returns
`429 PASSWORD_CHANGE_RATE_LIMITED` with `Retry-After: 900`.

The account and source-IP limits are independent buckets. A request is limited
only when either bucket has reached five denied attempts; counts from the two
buckets are never added together. The source-IP decision is serialized across
accounts so concurrent requests cannot all pass the pre-hash check.

Tenant-switch challenge creation must revalidate and lock the source session in
the same transaction that inserts the challenge. A password change either sees
and revokes the challenge or commits first and makes the source session invalid;
an access token validated before the password change cannot create a usable
challenge afterward.

Problem codes are `ACCOUNT_PROFILE_INVALID`, `AVATAR_URI_INVALID`,
`CURRENT_PASSWORD_INVALID`, `NEW_PASSWORD_INVALID`, `PASSWORD_UNCHANGED`, and
`ACCOUNT_CREDENTIAL_UNAVAILABLE`. Rate limiting uses
`PASSWORD_CHANGE_RATE_LIMITED`. Error responses never return credential
identifiers, hashes, session tokens, or existence information for another
account.

### File Whitelist

The implementation task may change only:

- `packages/php/kernel/src/Identity/SelfService/AccountSelfService.php`;
- `packages/php/kernel/tests/Integration/Identity/AccountSelfServiceIntegrationTest.php`;
- `packages/php/kernel/src/Auth/TenantAuthService.php` only to make source-session
  validation and tenant-switch challenge insertion one transaction;
- `packages/php/kernel/tests/Integration/Auth/TenantAuthServiceIntegrationTest.php`
  only for the password-change/tenant-switch race regression;
- `backend/app/controller/api/v1/AccountController.php`;
- `backend/app/middleware/TenantAccountRuntimeFactory.php`;
- `backend/tests/Integration/AccountSelfServiceHttpIntegrationTest.php`;
- `backend/tests/Contract/OpenApiArtifactTest.php` only for current operation-count
  and account-route assertions;
- `docs/api/openapi.yaml`, `docs/api/schemas/auth.yaml`, and
  `docs/api/index.md`;
- generated OpenAPI route and TypeScript artifacts;
- `docs/status/runtime-operation-coverage.json`;
- `docs/examples/verification.json` and `scripts/verify-doc-examples` only to
  preserve executable documentation checks for 75 P0 and 3 P1 operations;
- `README.md` and `docs/status/index.md` only for current P1 candidate status;
- `docs/guide/troubleshooting.md` only for current operation-availability wording;
- `scripts/check-openapi` only to replace the fixed total with explicit P0/P1 totals;
- `frontend/src/pages/common/AccountPage.vue`;
- `frontend/tests/account-page.spec.ts` and
  `frontend/tests/e2e/full-stack.e2e.ts` only for the account-page flows;
- `vitest.config.ts` only to make the existing aggregate unit command compile Vue SFC tests with the already accepted Vue plugin.

Changing Kernel schema, Module manifests, product profiles, dependency locks,
starter templates, example domain code, or another administration resource is
outside this slice.

### Acceptance

- The three operations are classified as `p1` and have executable test ownership.
- Cross-account IDs cannot be supplied by any request shape.
- Request bodies reject missing required fields and undeclared fields instead of
  silently clearing profile data or ignoring account and Tenant identifiers.
- Wrong current password changes no state and records a denied security event.
- Current-password verification happens before `PASSWORD_UNCHANGED`; equal but
  incorrect values are denied and counted, while equal valid values change no state.
- Repeated wrong-current-password attempts stop before password hashing, return
  a bounded retry interval, and record a redacted rate-limit event.
- Account and source-IP buckets are tested independently, and concurrent
  cross-account attempts from one IP cannot bypass the five-attempt limit.
- Successful profile update exposes no credential secret and records a tenant audit event with redacted evidence.
- Successful secret change revokes tenant and platform sessions, invalidates old
  access and refresh tokens, invalidates active login challenges, and never logs
  either password.
- Tenant-switch challenge creation is atomic with source-session validation, and
  a challenge cannot survive or cross a concurrent password change.
- Refresh-cookie metadata is prepared without database, hash, or other fallible
  Runtime construction after the password transaction commits.
- The profile form stays unavailable until the current profile is loaded, offers
  retry after load failure, and clears stale success feedback when edited.
- Desktop and mobile account-page flows pass against a real backend.
- OpenAPI, Runtime coverage, unit, integration, security, browser, recovery,
  workspace, and aggregate checks pass without weakening P0 assertions.

## P1-B02: Effective Access Preview Contract

This slice adds a current-state, tenant-administration inspection view for one
member. Contract verification against the P0 RBAC repository, resource
operation catalog, effective policy repository, typed-target rules, Provider
contracts, OpenAPI Runtime, Admin Web, and test gates found no dependency or
schema prerequisite.

The preview is an authoritative summary of the inputs that the existing
authorization Runtime will use. It is not an impersonation or object-level
authorization decision. The implementation must not create a synthetic
`ValidatedTenantSession` or `TenantContext` for the target member. A real
resource request remains subject to target cardinality, the owning resolver,
the Module Provider, the tenant hard boundary, and shared-master scope.

### Objective And Non-Goals

- Let an authorized tenant administrator inspect one member in the current
  Tenant.
- Return the member's current active roles and effective functional Permission
  keys using the existing tenant RBAC repository.
- Return a paginated summary of currently available protected-resource
  operations and their current effective data-policy inputs.
- Expose the result from the existing member list through a dedicated,
  responsive read-only Admin Web page.
- Preserve active-role union, condition `AND`, group/role `OR`, Module
  availability, time-window, typed-target, and fail-closed semantics.

This slice does not:

- accept an account ID, Tenant ID, role override, Permission override,
  `as_of` time, hypothetical policy, or target object as request input;
- simulate adding or removing a role, temporary authorization, delegation, or
  a platform support session;
- compile or expose SQL, query constraints, Provider keys, Provider classes,
  target labels, raw target IDs, credentials, sessions, or account identifiers;
- decide whether a concrete row, shared-master record, create descriptor, or
  command target is allowed;
- change Provider, resolver, condition-provider, typed-target, shared-master,
  menu-manifest, or Module contracts;
- add a product-domain permission, resource, object, page, or example.

Concrete object/row simulation is a later design task. It requires an explicit
read-only authorization-subject contract and must not be implemented by forging
a target member session.

### Prerequisite And Data Contract

The exact implementation prerequisite is
`d612a85045e2e9eb017719cd42a2f781d35b1f69`. The planning commit that records
this contract may be the direct parent of the implementation, but it must not
change Runtime behavior.

No table, column, index, unique constraint, check constraint, foreign key,
state transition, retention rule, or deletion rule is added or changed.

| Existing table | P1-B02 behavior |
| --- | --- |
| `pa_permission` | The existing catalog synchronizer upserts `core.member.effective-access.read` as an active `core` API Permission with `sensitive` risk. Existing unique keys, defaults, and lifecycle rules remain authoritative. |
| Tenant, member, role, role-Permission, Module, resource-operation, data-policy, group, condition, and typed-target tables | Read through the existing RBAC, catalog, and effective-policy repositories. Disabled, inactive, unavailable, future, and expired inputs do not become effective. |
| `pa_tenant_audit_event` | Each successful preview appends one `tenant.member.effective-access.viewed` event using the existing schema and retention policy. |
| `pa_auth_security_event` | No row is written. An authorized cross-member administration read is not an authentication or credential event. |

Pending, suspended, and left members remain visible to an administrator who
already holds the preview Permission, but their effective roles, Permissions,
and policy groups are empty. This makes inactive state explicit without
presenting configured assignments as usable access. A member absent from the
current Tenant is indistinguishable from an unknown member.

There is no schema migration. Kernel owns the release catalog synchronization.
Clean install and upgrade evidence must show that the new Permission is
created or updated idempotently and that all migration counts remain unchanged.
A code rollback does not delete catalog data: the additional Permission row may
remain inert because the prior Runtime has no route that consumes it and the
prior tenant-owner catalog does not imply it. No destructive down migration is
introduced.

### API And Response Contract

| Field | Contract |
| --- | --- |
| Operation | `getMemberEffectiveAccess` |
| Method and path | `GET /api/v1/members/{member_id}/effective-access` |
| Audience | Authenticated tenant audience only |
| Permission | `core.member.effective-access.read` |
| Path input | `member_id` is a canonical positive decimal string matching `^[1-9][0-9]*$`, is no greater than `PHP_INT_MAX`, and is resolved only inside the context Tenant |
| Query input | Existing `page` and `page_size`; `page >= 1`, `1 <= page_size <= 100`; pagination applies to resource operations |
| Request body | None |
| Success | `200 application/json`, schema `MemberEffectiveAccessResponse` |
| Success headers | `X-Request-Id` and `Cache-Control: no-store`; no `ETag` or `Set-Cookie` |

`MemberEffectiveAccessResponse.data` contains exactly:

- `preview_kind`, fixed to `authorization_inputs`;
- `evaluated_at`, a server UTC date-time;
- `snapshot_revision`, a lowercase 64-character digest derived from the
  authoritative RBAC revision, catalog revision, and policy revisions included
  in this page;
- `member`: `id`, nullable `display_name`, `status`, nullable
  `primary_department_id`, and boolean `effective`, where `effective` is true
  exactly when `status` is `active`;
- `roles`: sorted active effective role summaries with `id`, `key`, `name`, and
  `is_builtin`;
- `permission_keys`: the sorted, unique keys returned by
  `PdoTenantAuthorizationRepository::permissions()`;
- `resource_operations`: the current page of operation summaries.

Each resource-operation summary contains `resource_key`, `module_key`,
`operation`, `ownership`, `access_mode`, `target_cardinality`,
`permission_match`, sorted `required_permission_keys`, `functional_allowed`,
and `data_access`.

`data_access` contains:

- `mode`: one of `functional_denied`, `tenant_wide`,
  `global_reference_read`, `conditional`, `no_effective_policy`, or
  `tenant_actor_denied`;
- `runtime_decision_required`: true only when a concrete Runtime request still
  requires target, Provider, tenant-boundary, or shared-master evaluation;
- `group_match`, fixed to `any`;
- `groups`: the current effective groups. Each group contains
  `source_role_key`, `condition_match` fixed to `all`, and conditions containing
  only `condition_key`, nullable `target_resource_key`, and `target_count`.

The operation mode mapping is deterministic and follows this priority:

1. A missing functional binding, or a failed `all`/`any` Permission match, is
   `functional_denied`.
2. Otherwise `system_internal` access or `platform_internal` ownership is
   `tenant_actor_denied`.
3. Otherwise `global_reference_read` access is `global_reference_read`.
4. Otherwise `tenant_wide` access is `tenant_wide`.
5. Otherwise one or more effective policy groups is `conditional`.
6. Otherwise the mode is `no_effective_policy`.

`runtime_decision_required` is an informational warning, never an allow/deny
answer. It is true exactly when `functional_allowed` is true, the mode is not
`tenant_actor_denied`, and at least one of these conditions holds:

- `target_cardinality` is not `none`;
- `ownership` is `tenant_owned`, `business_target_owned`, or `shared_master`;
- `access_mode` is `rule_filtered` or `explicit_targets`.

This preserves the fact that target cardinality and resolvers run before the
access-mode branch, while Tenant and shared-master boundaries may still apply
after a Tenant-wide branch. A `global_reference_read` operation is false only
when it has `none` cardinality and `global_reference` ownership. `groups` is
always the redacted projection returned by `PdoPolicyRepository::load()`; the
mode mapping must not hide, add, or reinterpret groups.

The response never returns policy, group, condition, or target-set database IDs
and never returns raw target IDs. `target_count` comes from the authoritative
effective policy repository, including its greater-than-500 normalized target
set behavior. `conditional` means that an effective policy input exists; it is
not a concrete object allow decision.

Response `meta` is the existing page meta with `request_id`, `page`,
`page_size`, `total`, and `total_pages`. Only active catalog operations from
`core` or an installed, enabled, currently effective Tenant Module are counted.

The snapshot digest input is the UTF-8 string joined by `|` in this exact order:
`p1-b02-v1`, context Tenant ID, target member ID, authoritative RBAC revision,
catalog revision, page, page size, then each returned operation's resource key,
operation name, and policy revision in response order. The digest is lowercase
SHA-256. This defines reproducible ordering without exposing the input values.

### Permission And Data-Policy Behavior

The dedicated Permission is required because the response reveals more
sensitive authorization topology than `core.member.read`. It is not implied for
custom roles. The built-in `core.tenant-owner` receives it only through the
existing fixed `CorePermissionCatalog::TENANT` behavior. Tenant Permissions
never enter platform access, and platform Permissions never enter this preview.

Functional Permission output must come from the existing tenant authorization
repository. Active roles union their allows; P0 still has no explicit deny,
role inheritance, member-level policy, or super-user bypass. A resource
operation with `permission_match=all` requires every declared Permission;
`any` requires at least one. An operation with no binding remains fail closed.

Effective data-policy groups must come from `PdoPolicyRepository::load()`.
The preview does not reinterpret core conditions. Active conditions in a group
are an `AND`; active groups across policies and roles are an `OR`. Missing
effective groups never fall back to Tenant-wide access. Requested targets,
target cardinality, target resolvers, Module Providers, the Tenant hard
boundary, and shared-master scope remain authoritative only in the real
Runtime operation.

### Audit, Security, Concurrency, And Retry

The successful audit event uses the request `TenantContext` as actor and the
previewed member as target:

- event type: `tenant.member.effective-access.viewed`;
- action: `core.member.effective-access.read`;
- actor: context Tenant, member, and account;
- target resource type: `member`;
- target resource ID: the path `member_id` after current-Tenant resolution;
- target count: `1`;
- before/after values: absent;
- metadata: `snapshot_revision`, role count, Permission count, total operation
  count, page, and page size only.

Audit metadata must not contain raw Permission keys, role keys, target IDs,
condition configuration, Provider information, SQL, account identifiers, or
session material. A 401, 403, 404, or input-validation failure does not create a
successful preview audit event. Audit persistence failure fails the request;
it does not silently return an unaudited snapshot.

The service reads the member, RBAC result, catalog page, and effective policies
in one database transaction and appends the audit before commit. The database
snapshot is internally consistent; a concurrent authorization change appears
on a later request and changes `snapshot_revision`. No row is locked for
editing, and no optimistic precondition is accepted.

The GET does not use the generic idempotency store or `Idempotency-Key`.
Duplicate successful requests may return the same authorization snapshot but
produce distinct audit events. Clients may retry a network failure or 5xx as a
new GET. They do not automatically retry 401, 403, 404, or 422. This slice has
no 429 or `Retry-After` contract.

### Problem Details

- `AUTH_TOKEN_INVALID` (`401`) covers missing, invalid, or expired
  authentication.
- `AUTH_AUDIENCE_MISMATCH` (`401`) covers a valid credential presented to the
  wrong audience, including a platform credential presented to this tenant
  route.
- `AUTHZ_PERMISSION_DENIED` (`403`) covers the missing dedicated Permission.
- `RESOURCE_NOT_FOUND` (`404`) covers an unknown member and a member outside the
  context Tenant with the same title, detail, and shape.
- `MEMBER_ID_INVALID` (`422`) covers malformed, zero, negative, leading-zero,
  or greater-than-`PHP_INT_MAX` member IDs. The controller validates before any
  `int` conversion and before any preview-service member, catalog, or policy
  read or audit creation; it must not cast an unvalidated path string to `int`.
- `PAGE_INVALID` and `PAGE_SIZE_INVALID` (`422`) use the existing pagination
  validation.
- `INTERNAL_ERROR` (`500`) covers database, catalog, policy, or audit
  inconsistency without exposing SQL, Provider names, or authorization data.

Every error uses RFC 9457 `application/problem+json`, `X-Request-Id`, and
`Cache-Control: no-store`. No new error distinguishes cross-Tenant existence,
inactive assignments, missing Provider internals, or target-set contents.

### Exact Implementation File Whitelist

After the independent planning commit, implementation may change only:

- `packages/php/kernel/src/Authorization/CorePermissionCatalog.php`;
- `packages/php/kernel/src/Authorization/CorePermissionCatalogSynchronizer.php`;
- `packages/php/kernel/src/Authorization/TenantAuthorizationRepository.php`;
- `packages/php/kernel/src/Authorization/PdoTenantAuthorizationRepository.php`;
- `packages/php/kernel/tests/Integration/Authorization/FunctionalAuthorizationTest.php`;
- `packages/php/data-permission/src/Catalog/ResourceOperationCatalog.php`;
- `packages/php/data-permission/src/Catalog/PdoResourceOperationCatalog.php`;
- `packages/php/data-permission/src/Application/EffectiveAccessPreviewService.php`;
- `packages/php/data-permission/tests/Integration/Application/EffectiveAccessPreviewServiceTest.php`;
- `backend/app/controller/api/v1/DataAuthorizationController.php`;
- `backend/config/permission.php`;
- `backend/route/tenant-admin.php`;
- `backend/tests/Integration/EffectiveAccessPreviewHttpIntegrationTest.php`;
- `backend/tests/Contract/OpenApiArtifactTest.php`;
- `backend/tests/Upgrade/UpgradeWorkflowIntegrationTest.php`;
- `packages/php/kernel/tests/Unit/Authorization/AdminRouteContractTest.php`;
- `docs/api/openapi.yaml`;
- `docs/api/responses.yaml`;
- `docs/api/schemas/authorization.yaml`;
- `docs/api/index.md`;
- `docs/guide/authorization.md`;
- `backend/route/openapi-generated.php` generated only by
  `./scripts/check-openapi --write`;
- `packages/web/admin-core/src/generated/api.d.ts` generated only by
  `./scripts/check-openapi --write`;
- `docs/status/runtime-operation-coverage.json`;
- `scripts/check-openapi` and `scripts/verify-doc-examples` only for the exact
  `75` P0, `4` P1, `79` total operation assertions;
- `README.md` and `docs/status/index.md` only for current P1 candidate status;
- `frontend/src/app/router.ts`;
- `frontend/src/app/routes.ts`;
- `frontend/src/pages/common/ResourceCollectionPage.vue`;
- `frontend/src/pages/common/EffectiveAccessPreviewPage.vue`;
- `frontend/tests/effective-access-preview-page.spec.ts`;
- `frontend/tests/routing.spec.ts`;
- `frontend/tests/fixtures/api.ts`;
- `frontend/tests/e2e/tenant-workspace.e2e.ts`;
- `frontend/tests/e2e/full-stack.e2e.ts`.

No other file may change. In particular, Kernel or data-permission schemas and
migrations, dependency manifests and lock files, Provider/resolver/condition
contracts, Module manifests, product profiles, menu catalogs, starter files,
example product-domain Modules, fixed ports and database names, release records,
and downstream-consumption locks must not change. If this whitelist proves
insufficient, implementation stops and the contract is amended in a separate
planning commit before any additional Runtime file changes.

### Historical Test Ownership And Acceptance

Tests are written to fail for the missing capability before implementation.
`RUNTIME-EFFECTIVE-ACCESS-PREVIEW-001` owns `getMemberEffectiveAccess` and names
the service integration test, HTTP integration test, page unit test, and real
full-stack browser test as executable evidence. Existing
`RUNTIME-TENANT-ADMIN-001`, `RUNTIME-DATA-AUTHORIZATION-001`, and
`RUNTIME-CONTRACT-001` owners remain unchanged.

The original P1-B02 candidate acceptance required:

- multiple active roles union and de-duplicate Permissions, while inactive
  roles, inactive members, platform Permissions, unavailable Modules, and
  future, expired, disabled, or empty policies do not become effective;
- tenant-owner implicit fixed core Permissions use the same repository path;
- policy summaries preserve group `AND`, group/role `OR`, condition type, typed
  target resource type, and target count without exposing raw target IDs;
- tenant-wide, global-reference, conditional, missing-policy, functional-denied,
  and tenant-actor-denied modes are distinguished without claiming a Provider
  decision;
- service-versus-`DataPermissionEngine` parity tests cover empty, `all`, and
  `any` Permission bindings plus system-internal, platform-internal,
  global-reference, tenant-wide, conditional, and missing-policy behavior;
- cross-Tenant and unknown member requests are identical 404 responses, a
  caller without the dedicated Permission receives 403 before preview data, and
  a platform token cannot enter the tenant route;
- malformed, zero, negative, leading-zero, and overflow member IDs return
  `422 MEMBER_ID_INVALID` before any preview-service read or audit event;
- successful reads create exactly one redacted tenant audit event, while denied
  and not-found reads create none and no auth security event is written;
- clean install and repeated upgrade synchronize the new sensitive Permission
  without a migration, dependency, or permission-boundary regression;
- the member-list row action opens
  `/app/members/:member_id/effective-access` only when the dedicated Permission
  is present; direct unauthorized navigation is blocked before the API call;
- the page renders member state, roles, functional Permissions, paginated
  operation scopes, refresh/error/empty states, and long keys without document
  overflow at desktop `1440x900` and mobile `390x844`;
- mock-browser and real-backend desktop/mobile tests pass with no `/api/**`
  interception in the full-stack suite and no console or page errors;
- the new operation is classified `p1`, the ledger reports `75` P0 and `4` P1,
  generated routes and TypeScript types are current, and no P0 assertion is
  removed or weakened;
- focused PHP and Web tests, `./scripts/check-openapi`,
  `./scripts/check-runtime-coverage`, `PEANUT_RUNTIME_STAGE=runtime
  ./scripts/check-architecture`, `./scripts/test-security`, focused desktop and
  mobile browser tests, `./scripts/check`, and `git diff --check` all pass.

Implementation is one independently reviewable commit after the planning
commit. Completion makes P1-B02 only an unqualified candidate. It does not move
`0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`, approve downstream consumption,
publish a package, create a tag or release, claim production readiness, trigger
deployment, or assert that any downstream product can consume the commit.

## P1-R01: Operation Atomicity Primitives Contract

This slice closes the transaction boundary required before an external Module
command can be used as downstream evidence. Peanut Admin already has a PDO
transaction manager, idempotency tables, idempotency repository, and audit
repository, but they are not yet a complete reusable contract: nested
transactions silently share the caller transaction, an abandoned
`processing` record cannot be reclaimed, audit writes expose only a success
outcome, and the reference HTTP middleware acquires idempotency on a separate
PDO from the domain command.

P1-R01 provides the primitives and test harness needed to compose one explicit
transaction on one caller-supplied PDO. P1-R02 remains responsible for the
external HTTP host kit and for wiring trusted context, authorization, and these
primitives together without copying the reference middleware.

### Status, Objective, And Non-Goals

```text
task: P1-R01
state: implementation-ready
prerequisite: 04643b30875febccf46c972fba1d851e02b36612
migration: none
dependency_change: none
openapi_operation: none
qualification: candidate only
```

The objective is to make one PDO and one explicit transaction boundary
authoritative for idempotency acquisition, application-owned domain effects,
audit evidence, an optional application-owned outbox write, and idempotency
completion.

This slice does not:

- define an application's domain command, repository, table, or transaction
  semantics;
- add a Kernel outbox table, event schema, dispatcher, queue, or delivery
  worker;
- add an HTTP route, OpenAPI operation, Permission, data policy, Module,
  product profile, menu, or Web page;
- replace authentication, functional authorization, typed-target resolution,
  Provider execution, or the Tenant hard boundary;
- make the current reference `IdempotencyMiddleware` an external-host API;
- add automatic deadlock retries, distributed transactions, two-phase commit,
  or cross-database atomicity;
- publish a package, stabilize a public version, or move the downstream lock.

### Transaction And Savepoint Contract

`PdoTransactionManager::run()` remains the public transaction entry point.

- When no transaction is active, it begins, commits, or rolls back the outer
  PDO transaction and returns the callable result.
- When a transaction is already active, every nested `run()` creates a unique
  MySQL savepoint on the same PDO.
- Nested success releases only its savepoint. It never commits the caller's
  transaction.
- Nested failure rolls back to and releases only its savepoint, then rethrows
  the original exception. The caller may catch it and continue the outer
  transaction.
- An uncaught nested failure still reaches the outer manager and rolls back the
  entire outer transaction.
- A callable must not commit or roll back the manager-owned boundary. Loss of
  the expected active transaction is an infrastructure error, not successful
  completion.
- Savepoint names are generated internally, contain no request or business
  value, are never supplied by a caller, and are not exposed in an API error.

The manager does not retry the callable. A host that later introduces a
deadlock retry must prove that the whole command, including authorization and
idempotency acquisition, is safe to execute again.

### Idempotency Lease And Outcome Contract

The existing Tenant and platform tables remain authoritative. No table or
column is added. `expires_at`, `status`, request hash, response fields, and the
existing unique scope keys are sufficient for P1-R01.

`beginTenant()` and `beginPlatform()`:

- use their existing scope tuple and store only the SHA-256 idempotency-key
  hash, never the raw header;
- participate in an already active PDO transaction, keeping the acquired row
  lock until the caller commits or rolls back;
- otherwise own a short compatibility transaction so existing reference-host
  callers still receive a concurrency-safe acquisition result;
- atomically insert or lock the unique record before deciding its state;
- determine insert ownership independently of `PDO::MYSQL_ATTR_FOUND_ROWS`;
- require `expires_at` to be later than the comparison time;
- accept an optional explicit comparison time for deterministic tests while
  defaulting to the current UTC time.

State handling is fixed as follows:

| Existing state | Same request hash | Different request hash |
| --- | --- | --- |
| no record | acquire a new `processing` lease | not applicable |
| unexpired `processing` | return not acquired; the host reports `IDEMPOTENCY_REQUEST_PROCESSING` | `IDEMPOTENCY_KEY_REUSED` |
| expired `processing` | return not acquired; the host reports `IDEMPOTENCY_REQUEST_PROCESSING` | `IDEMPOTENCY_KEY_REUSED` |
| `completed` | return a terminal replay | `IDEMPOTENCY_KEY_REUSED` |
| `failed` | return a terminal replay when a safe stored response exists | `IDEMPOTENCY_KEY_REUSED` |

Expiry never permits a different payload to reuse the same scoped key. P1-R01
uses deterministic rejection instead of automatic takeover because the
existing schema has no fencing token and cannot prove an old executor stopped.
Cleanup, retention, and a future fenced recovery design are separate lifecycle
work and must not silently change the request hash binding.

`IdempotencyRecord` distinguishes a newly inserted lease, a non-owned
processing lease, and a terminal replay. Expiry does not create execution
ownership.

`completeTenant()` and `completePlatform()` continue to transition only a
`processing` record to `completed`. New matching `failTenant()` and
`failPlatform()` methods transition only `processing` to `failed` and store a
caller-supplied safe response. A second transition fails with
`IDEMPOTENCY_STATE_CONFLICT`; invalid stored JSON fails closed with
`IDEMPOTENCY_RESPONSE_INVALID`.

The repository does not decide which application errors are terminal. The host
may store only a response that is safe to replay and contains no credential,
secret, SQL, stack trace, hidden target existence, or raw authorization input.

### Audit Outcome Contract

The existing audit tables already permit `success`, `denied`, and `error`; no
migration is required. Kernel adds a type-safe `AuditOutcome` value and optional
outcome argument to the four concrete `PdoAuditRepository` append methods. The
default remains `success`. The existing `AuditRepository` interface remains
unchanged so external implementations stay source-compatible.

- A successful command records `success` inside the domain transaction.
- An expected denial may commit a redacted `denied` audit and a safe failed
  idempotency response without applying a domain effect.
- An unexpected exception rolls back domain, in-transaction audit, outbox, and
  idempotency completion together. A host may record a separate redacted
  `error` outcome only after that rollback in a new explicit transaction.
- A denial or error must omit a target identifier when revealing it would leak
  whether a protected object exists.
- Metadata remains limited to scalar redacted evidence accepted by the current
  repository contract. P1-R01 adds no secret scanner at runtime and does not
  make arbitrary payloads safe.

### Composition Order And Outbox Boundary

An application command that needs generic idempotency composes these steps on
the same PDO:

1. enter `PdoTransactionManager::run()`;
2. acquire the scoped idempotency lease;
3. return a terminal replay or reject a live non-owned lease without running
   the command;
4. execute the application-owned domain callable;
5. append the redacted audit evidence;
6. optionally call the application-owned outbox adapter using the same PDO;
7. complete or deliberately fail the idempotency record;
8. return from the callable so the outer manager commits.

The outbox adapter and schema remain application-owned. Kernel does not define
an outbox interface because a callback using the same caller-owned PDO is
sufficient for this primitive. P1-R02 may provide host composition helpers but
must not move an application outbox into Kernel.

The existing reference `IdempotencyMiddleware` is updated only to understand
new execution ownership and terminal failed replays. It remains a
compatibility adapter and does not prove external Module atomicity because it
creates its own PDO and wraps an HTTP response rather than the domain callable.

### Concurrency, Failure, And Recovery

- Two concurrent acquisitions of one scoped key and request hash yield exactly
  one execution owner. The other observes an unexpired `processing` lease or a
  terminal replay after the owner commits.
- A stale same-hash acquisition is serialized by the idempotency row lock and
  remains non-owned. No caller can race an old executor by taking over its
  lease.
- A transaction rollback after a new acquisition removes the inserted row.
- Failure after domain, audit, outbox, or idempotency completion but before
  commit leaves no partial row from that attempted command.
- A nested failure that is caught by the outer command removes only nested
  effects and leaves earlier and later outer effects available for commit.
- Cross-connection and cross-database commands are not atomic under this
  contract and must not be presented as such.

### Reusable Test Harness

`peanut-admin/testing` adds `OperationAtomicityContractHarness`. A downstream
Module supplies one operation callable that accepts a checkpoint callback and
state probes for its domain, audit, outbox, and idempotency state.

The harness injects one deterministic exception after each stage:

- `idempotency_acquired`;
- `domain_written`;
- `audit_written`;
- `outbox_written`;
- `idempotency_completed`.

After every injection, all probes must equal their pre-operation values. The
harness rejects unknown, duplicate, or out-of-order checkpoints, proves every
required checkpoint was reached in order, executes one non-injected success
path, and requires the supplied probes to show committed state. It rethrows any
exception that is not its own injected failure. It does not create tables,
choose payloads, or hide a transaction left open by the host.

### Error And API Boundary

P1-R01 creates no HTTP operation and no Runtime ledger row. Existing stable
idempotency codes remain authoritative:

| Code | Meaning |
| --- | --- |
| `IDEMPOTENCY_KEY_INVALID` | The raw key is missing or invalid before hashing. |
| `IDEMPOTENCY_KEY_REUSED` | The scoped key is bound to another request hash. |
| `IDEMPOTENCY_REQUEST_PROCESSING` | A matching unexpired execution lease is owned elsewhere. |
| `IDEMPOTENCY_STATE_CONFLICT` | A terminal transition no longer matches `processing`. |
| `IDEMPOTENCY_RESPONSE_INVALID` | A stored terminal response cannot be decoded safely. |

Transaction/savepoint failures are infrastructure exceptions. P1-R02 owns
their eventual Problem Details mapping and must not expose SQL, savepoint names,
connection details, or stack traces.

### Exact Implementation File Whitelist

After this contract is committed independently, implementation may change only:

- `packages/php/kernel/src/Persistence/Pdo/PdoTransactionManager.php`;
- `packages/php/kernel/src/Idempotency/IdempotencyRecord.php`;
- `packages/php/kernel/src/Idempotency/PdoIdempotencyRepository.php`;
- `packages/php/kernel/src/Audit/AuditOutcome.php`;
- `packages/php/kernel/src/Persistence/Pdo/PdoAuditRepository.php`;
- `backend/app/middleware/IdempotencyMiddleware.php`;
- `packages/php/kernel/tests/Integration/Persistence/PdoTransactionManagerTest.php`;
- `packages/php/kernel/tests/Integration/Idempotency/IdempotencyRepositoryTest.php`;
- `packages/php/kernel/tests/Integration/Operation/OperationAtomicityPrimitivesTest.php`;
- `packages/php/kernel/tests/Unit/Idempotency/IdempotencyContractTest.php`;
- `packages/php/testing/src/Operation/InjectedOperationFailure.php`;
- `packages/php/testing/src/Operation/OperationAtomicityContractHarness.php`;
- `packages/php/testing/tests/Unit/Operation/OperationAtomicityContractHarnessTest.php`;
- `docs/guide/module-development.md`;
- `README.md` and `docs/status/index.md` only for candidate status.

No schema, migration, dependency manifest, lockfile, OpenAPI, generated route,
generated TypeScript, Runtime coverage ledger, authorization engine, Provider,
resolver, Module manifest, product profile, starter, frontend, release, or
downstream-lock file may change. If this whitelist is insufficient, work stops
and a separate planning correction is committed before expanding it.

### Historical Test Ownership And Acceptance

`P1-OPERATION-ATOMICITY-001` owns the package and integration evidence. It has
no OpenAPI operation and therefore no Runtime coverage ledger entry.

Tests are written to fail before the Runtime edit and must prove:

- nested success, caught nested failure, uncaught nested failure, and an
  externally owned transaction all preserve the specified savepoint boundary;
- new acquisition, completed replay, failed replay, same-hash stale rejection,
  different-hash rejection, invalid expiry, duplicate terminal transition,
  `PDO::MYSQL_ATTR_FOUND_ROWS` independence, compatibility-path recovery
  rejection, and rollback of a new lease;
- two connections cannot both own one live or stale lease;
- `success`, `denied`, and `error` audit outcomes persist only when their
  transaction commits;
- a successful fictional operation commits exactly one domain, audit, outbox,
  and completed idempotency row;
- injected failure at every required checkpoint leaves all four state probes
  unchanged;
- the reusable testing harness detects a deliberately non-atomic operation;
- the reusable testing harness rejects duplicate or out-of-order checkpoints
  and proves one successful state transition;
- no raw idempotency key, secret, target set, SQL, or stack trace is persisted
  by the fixture evidence.

The original P1-R01 candidate ran focused Kernel and testing unit/integration
gates. Its historical final-acceptance contract also required architecture,
dependency, supply-chain, clean-install, upgrade, security, recovery,
performance, starter, workspace, `./scripts/check`, and `git diff --check` on an
isolated Compose project. Later Starter v1 tasks do not repeat this historical
aggregate list unless their risk scope requires it; the fixed milestone
candidate reruns the aggregate evidence.

Implementation is one independently reviewable commit after this planning
commit. Completion makes P1-R01 only an unqualified candidate. It does not move
`0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`, qualify an external Module, publish
a package, create a tag or release, claim production readiness, or authorize a
downstream project to consume the resulting commit.

## Dependency Gates

No dependency is added by P1-B01, P1-B02, or P1-R01. Every later direct
dependency starts with a decision that records exact version, license, official
sources, alternatives, adapter boundary, removal plan, and current security
status.

The following capabilities are blocked until their decisions are accepted:

- phone delivery, email delivery, MFA, and external identity protocols;
- filesystem and object-storage abstraction;
- queue and scheduling Runtime;
- spreadsheet parsing and generation;
- notification transport and templating;
- extension signature verification and package installation.

No placeholder schema, adapter, package, or directory is reserved while a
capability is blocked.

## Qualification Stop Line

- Completing a slice proves only that slice on its resulting commit.
- The existing P0 downstream-consumption lock does not move.
- Downstream consumers must not adopt a P1 commit until a fixed-commit P1
  aggregate gate and independent review explicitly approve it.
- P1 work does not authorize package publication, a tag, a release, automatic
  deployment, production sizing, or a stable compatibility promise.
- A failed security, isolation, upgrade, recovery, browser, performance, or
  supply-chain gate blocks the qualification, release, or downstream-lock
  movement that depends on it; checks are fixed rather than skipped or
  weakened. The failure does not freeze unrelated ordinary candidate work.
- P1-B01 adds no schema migration. A code rollback does not restore an old
  password or revoked session; recovery uses a forward credential reset and new
  authentication, while the database transaction rolls back any failed write
  before commit.
