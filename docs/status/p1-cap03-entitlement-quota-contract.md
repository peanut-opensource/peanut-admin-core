# P1-CAP03 Entitlement Quota Contract

## Status

```text
task: P1-CAP03
state: accepted implementation contract
prerequisite_commit: ba707c1b3943ff76620770dbf72413de51f340f6
prerequisite_tree: bcb3014a6af842927857ebdd4ec326c9a9a91a62
public_boundary: peanut-admin/core
target_candidate: peanut-admin/core@0.1.0-alpha.5, Composer-only and unpublished
dependency_change: none
http_api: none; each Host owns routes, OpenAPI and Runtime coverage
test_owner: P1-ENTITLEMENT-QUOTA-001
qualification: deferred to P1-CROSS-PRODUCT-QUALIFICATION-001 / CAP05
downstream_adoption: deferred to CAP06
publication_authorized: false
```

CAP02 passed all six repository checks and merged through Core PR #9. CAP03
starts only from the exact merge commit above. A branch name, the CAP02 source
commit or an unpublished package projection is not a valid prerequisite.

## Objective And Non-Goals

CAP03 adds a product-neutral Tenant quota authority. It evaluates one declared
integer meter, reserves capacity atomically, commits or releases a reservation,
and reports a safe usage summary. It persists the immutable grant/policy
snapshot used by an accepted reservation, UTC period windows, reservation
state and an append-only settlement ledger.

A Host owns plan/catalog presentation, billing, prices, invoices, subscription
lifecycle and the business meaning of a meter unit. A Host registers typed
meters and supplies an already authorized, immutable grant snapshot through a
fail-closed Provider. CAP03 never infers a plan from a route, Role, Tenant name
or absent policy.

CAP03 does not add SaaS billing; commercial-license enforcement; product meter
names; floating-point money or quantities; middleware-wide enforcement; a
super-user/unlimited bypass; HTTP, OpenAPI or Web UI; application consumption;
package publication; or a DCS candidate.

## Existing Owners And Required Reuse

| Concern | Existing authority | CAP03 rule |
| --- | --- | --- |
| Tenant and actor | Kernel `TenantContext` | Tenant/member/account/request come only from `AuthorizedOperationContext`. |
| Authorization | Kernel authorized operation and typed targets | The sole primary target resource/ID must equal the target type/key supplied to CAP03. |
| Time | Kernel `Clock` / `SystemClock` | No request-supplied wall clock. Provider and reservation times are normalized to UTC. |
| Transactions | Kernel `PdoTransactionManager` | Reserve/commit/release, idempotency, ledger and audit share one caller PDO transaction. |
| Idempotency | Kernel `PdoIdempotencyRepository` | Every write uses Tenant/member scope, canonical semantic hashes and replayable receipts. |
| Audit | Kernel `PdoAuditRepository` | Successful writes append redacted Tenant-member evidence in the same transaction. |
| Meter meaning | Host `EntitlementMeterRegistry` | The Host declares the stable meter key, target type and integer unit. Missing declarations deny. |
| Grant source | Host `EntitlementPolicyProvider` | The Provider returns one immutable bounded snapshot. Missing, malformed or unavailable policy denies or fails closed. |

All production persistence adapters expose their PDO. CAP03 opens no second
connection and does not degrade an atomic reservation to best effort.

## PHP API Contract

`EntitlementQuotaService` exposes exactly five operations:

| Operation | Contract |
| --- | --- |
| `check` | Evaluate whether one positive integer amount fits the current immutable grant without writing or promising capacity. |
| `reserve` | Atomically lock the UTC usage window, expire stale pending reservations, prove `committed + pending + amount <= limit`, and create one pending reservation. |
| `commit` | Settle one unexpired pending reservation exactly once and add its amount to committed usage. |
| `release` | Release one pending reservation exactly once so it no longer consumes capacity. |
| `usage` | Read a safe current summary for one declared meter and authorized target without exposing plan/commercial metadata. |

Reserve/commit/release require an idempotency key; check/usage do not. Every
call requires positive trusted Tenant/member/account IDs, non-empty request,
resource and operation, and exactly one primary target. Unknown, cross-Tenant,
mismatched and invisible targets use the same not-found result.

Meter, target-type, unit, grant and policy identifiers match
`[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*` and are at most 64 characters. Target keys
are 1-128 printable ASCII bytes. Amounts and limits are positive signed
64-bit integers; addition is overflow-checked. No float, decimal string,
negative credit or implicit unlimited value is accepted.

`EntitlementMeterRegistry` returns one immutable declaration for a meter key
and target type. `EntitlementPolicyProvider` returns a snapshot containing
exactly grant key, policy revision key, meter key, unit key, limit amount,
period kind, effective interval and reservation TTL. Period kind is one of
`lifetime`, `utc_day`, or `utc_month`. TTL is 30-86400 seconds and is capped by
the current window end. The canonical sorted snapshot and lowercase SHA-256
are persisted on first successful reservation; reuse of the same revision key
with different bytes is an integrity failure.

Check and usage are advisory reads. Only reserve promises capacity. Commit and
release settle the immutable snapshot already pinned by the reservation so a
temporary Provider outage cannot strand or double-count accepted work.
Provider availability is required for check, usage and reserve; there is no
silent zero, unlimited or last-known-policy fallback.

Reservation keys are `reservation_<32 lowercase hex>`. A pending reservation
may transition once to `committed`, `released`, or `expired`. Committed and
released rows never return to pending. Expiry never commits usage and is
processed under the same window lock before a new reservation decision.

## Data And Ownership Contract

EntitlementQuota owns exactly five MySQL 8 tables. Identifiers use ASCII binary
collation and timestamps are UTC `DATETIME(3)`.

`pa_entitlement_grant` stores Tenant/grant identity, state `active|suspended`,
current immutable policy revision pointer, optimistic revision, actor IDs and
timestamps. It has unique Tenant/id and Tenant/grant keys plus Kernel Tenant
and member foreign keys with delete restriction.

`pa_entitlement_policy_revision` stores Tenant/grant, policy revision key,
meter/unit keys, positive limit, period kind, effective interval, bounded TTL,
canonical snapshot JSON/SHA-256 and creation evidence. Revisions are immutable
and unique by Tenant/revision key and Tenant/grant/revision key.

`pa_entitlement_usage_window` stores Tenant/policy/meter/target identity, UTC
window start/end, committed amount, optimistic revision and timestamps. Its
identity is unique for Tenant, policy revision, meter, target and window start.

`pa_entitlement_reservation` stores Tenant/window, reservation key,
meter/target, positive amount, `pending|committed|released|expired`, optimistic
revision, creator/settler IDs and reserve/expiry/settlement timestamps. Exact
state/timestamp null coherence is enforced.

`pa_entitlement_usage_ledger` is append-only. It stores Tenant/window and
reservation, a unique event key, `reserved|committed|released|expired`, amount,
actor and occurred time. It never stores prices, plan names, invoice IDs,
payloads, secrets or another Tenant's totals.

The repository locks one window for every capacity-changing operation. It
expires stale pending rows, sums only live pending reservations, checks integer
overflow, and uses optimistic predicates for every transition. Every read is
scoped by Tenant, meter and target.

## Errors, Audit And Security Results

Stable errors are `ENTITLEMENT_QUOTA_INVALID` (422),
`ENTITLEMENT_QUOTA_NOT_FOUND` (404), `ENTITLEMENT_QUOTA_DENIED` (403),
`ENTITLEMENT_QUOTA_EXCEEDED` (409), `ENTITLEMENT_QUOTA_CONFLICT` (409),
`ENTITLEMENT_QUOTA_PROVIDER_UNAVAILABLE` (503),
`ENTITLEMENT_QUOTA_INTEGRITY_FAILURE` (500) and
`ENTITLEMENT_QUOTA_INTERNAL_ERROR` (500). Kernel idempotency errors keep their
existing codes.

Successful writes audit `tenant.entitlement_quota.reserved`, `.committed`, or
`.released` with the authorized action, meter/target, reservation key, amount,
state, policy digest and safe committed/reserved/limit summary only. Provider
credentials, commercial plan metadata and other Tenant usage are excluded.

## Exact Write Sets

This contract commit may change only this file, `docs/content-status.json`,
`docs/status/index.md`, `docs/status/p1-execution-and-post-q01-roadmap.md` and
`docs/status/p1-post-q01-cross-product-capability-plan.md`.

After it commits, one CAP03 implementation candidate may change only:

### CAP03-A — schema, models and persistence

- `packages/php/entitlement-quota/src/Database/Schema.php`;
- `packages/php/entitlement-quota/src/Model/EntitlementGrant.php`;
- `packages/php/entitlement-quota/src/Model/EntitlementPolicyRevision.php`;
- `packages/php/entitlement-quota/src/Model/EntitlementUsageWindow.php`;
- `packages/php/entitlement-quota/src/Model/EntitlementReservation.php`;
- `packages/php/entitlement-quota/src/Persistence/EntitlementQuotaRepository.php`;
- `packages/php/entitlement-quota/src/Persistence/PdoEntitlementQuotaRepository.php`;
- `packages/php/entitlement-quota/tests/Unit/Database/SchemaTest.php`;
- `packages/php/entitlement-quota/tests/Integration/Persistence/PdoEntitlementQuotaRepositoryTest.php`.

### CAP03-B — declarations, Provider and service

- `packages/php/entitlement-quota/src/Package.php`;
- `packages/php/entitlement-quota/src/Contract/EntitlementMeter.php`;
- `packages/php/entitlement-quota/src/Contract/EntitlementMeterRegistry.php`;
- `packages/php/entitlement-quota/src/Contract/EntitlementGrantSnapshot.php`;
- `packages/php/entitlement-quota/src/Contract/EntitlementPolicyProvider.php`;
- `packages/php/entitlement-quota/src/Application/EntitlementQuotaException.php`;
- `packages/php/entitlement-quota/src/Application/EntitlementQuotaDecision.php`;
- `packages/php/entitlement-quota/src/Application/EntitlementQuotaReceipt.php`;
- `packages/php/entitlement-quota/src/Application/EntitlementQuotaUsage.php`;
- `packages/php/entitlement-quota/src/Application/EntitlementQuotaService.php`;
- `packages/php/entitlement-quota/tests/Integration/Application/EntitlementQuotaServiceTest.php`.

### CAP03-C — existing projection wiring only

- root `composer.json` for EntitlementQuota source/test dev autoload;
- `packages/php/composer.json` for the thirteenth Alpha.5 PSR-4 root;
- `deptrac.yaml` for EntitlementQuota depending only on Kernel;
- `phpunit.xml` for EntitlementQuota source/tests;
- `scripts/check-workspace` for the internal directory/thirteenth root;
- `scripts/check-alpha5-package-projection` for the thirteenth root, isolated
  autoload, product-term guard and `psr4_roots=13`.

No Kernel, ArtifactRevision, Workflow, File/Media, backend/frontend/npm,
lockfile, migration, OpenAPI, generated artifact, dependency decision, CI or
application file may change. An insufficient whitelist requires an independent
contract correction.

## Test Ownership And Stop Line

`P1-ENTITLEMENT-QUOTA-001` owns schema install/rerun, Tenant/target isolation,
provider and registry failures, UTC windows, overflow, atomic competing
reservations, expiry, idempotent settlement, immutable policy digest,
optimistic conflicts, rollback, audit redaction and isolated Alpha.5 consumer
evidence.

Implementation tasks use static review, exact-write-set inspection and
`git diff --check`; repository PR automation remains required. Complete fixed
candidate evidence stays deferred to CAP05. CAP03 does not qualify or publish
Alpha.5, move application locks, nominate DCS or start CAP04. CAP04 begins only
from the final CAP03 merge commit.
