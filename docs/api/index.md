# API Contract

[`openapi.yaml`](./openapi.yaml) is the OpenAPI 3.1.2 fact source for Peanut Admin Runtime operations. It defines separate tenant and platform audiences, typed target requests, stable operation identifiers, pagination, ETags, idempotency, and RFC 9457 Problem Details.

TypeScript declarations are generated at `packages/web/admin-core/src/generated/api.d.ts`. Backend routes are generated from each operation's `x-handler` and `x-permission`; both generated artifacts are checked for drift by `./scripts/check-openapi`.

All BIGINT identifiers cross the API boundary as decimal strings. Tenant requests obtain `tenant_id` from the validated session context, never from a request body.

## Implementation Status

All 75 P0 operations in the current OpenAPI document bind to concrete
reference-host handlers. Sixteen additional P1 operations provide account
self-service, tenant-member effective-access inspection, six Settings
operations, and six Tenant Reference Codes operations. These remain candidate
capabilities outside the qualified downstream-consumption baseline.
`./scripts/check-openapi` verifies that each handler exists, accepts the
declared path parameters, returns `think\Response`, and carries success status,
body, and header metadata matching the generated route.

Settings writes require host-owned atomic idempotency and a strong resource
precondition. The generated route therefore does not attach the generic
idempotency middleware a second time. Settings responses keep resolved value
metadata separate from the managed-scope revision and ETag, redact every secret
value, and allow null only when no non-required effective value exists.

Reference Codes writes also use host-owned atomic idempotency and strong
preconditions. Set ownership comes only from compiled Module declarations;
Tenant ownership comes only from trusted context. Generated routes preserve
the declaration Module guard, separate read/manage Permissions, immutable code
identity, versioned values, and non-enumerating cross-Tenant failures.

`GET /api/v1/members/{member_id}/effective-access` requires the tenant-audience
`core.member.effective-access.read` Permission. It pages the member's current
resource-operation authorization inputs and returns `X-Request-Id` with
`Cache-Control: no-store`. The response is deliberately not an impersonation or
an object-level allow decision: it never exposes Provider details, SQL, policy
record identifiers, account identifiers, or raw target IDs. Concrete Runtime
requests still apply target cardinality, resolvers, Providers, the Tenant hard
boundary, and shared-master scope.

This statement applies only to operations classified in the current Runtime coverage ledger. A future operation is unavailable until its OpenAPI schema, concrete handler, authorization metadata, classification, and automated evidence land together. The unused fail-closed contract fallback classes do not publish an operation.
