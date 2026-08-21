# Starter v1 Stage B File And Media Delivery Handoff

## Fixed Feature Boundary

`PA-SV1-C02-B02-file-media-delivery` starts from Stage A candidate
`46dd08c03bc720aed1a572fe0227af5d5d650333` with tree
`a3ef05fef5172660f533e7803cd978f4fc447df2`. It extends the existing
`peanut.file-media` domain; it does not create a second metadata, Permission,
menu, or Admin Web domain.

The feature branch owns only `packages/php/file-media/**`,
`packages/web/file-media/**`, focused package tests, and these File/Media
documents. Shared Host routes/controllers/config, OpenAPI roots and schemas,
generated artifacts, Runtime coverage, router, manifests, locks, and canonical
starter remain owned by `PA-SV1-C02-I04-stage-b-integration`.

## Implemented Feature-Local Contracts

- `ObjectStorageProvider` extends the existing provider with explicit
  capabilities and `head()` evidence. `PrivateStorageAdapter` preserves local
  development compatibility without claiming public, signed, or CDN delivery.
- `DeliveryService` binds a ready file to trusted Tenant context, a positive
  Permission decision, explicit visibility, replay mode, TTL, and one Host
  adapter. Private delivery requires `single_use`; CDN/public delivery never
  falls back to a local path or unsigned URL.
- `SignedDeliveryTokenService` signs exact Tenant/file/visibility/replay/time
  claims and rejects tampering, wrong Tenant/file, future issue time, expiry,
  excessive lifetime, and consumed single-use IDs. It never signs a provider
  storage key or absolute path.
- `ImageMetadataInspector` recognizes PNG/JPEG bytes and enforces bounded
  geometry. `ImageVariantPlanner` validates unique names and at most 16 bounded
  contain/cover outputs. `ImageVariantProcessor` is the Host rendering boundary
  and `ImageVariantOutputVerifier` validates actual output MIME, geometry, byte
  size, and digest; the feature does not pretend that a plan is a generated
  file.
- `FileAssetSelector` displays only validated image candidates, emits the opaque
  file object selected by the operator, and accepts only same-origin `/api/` or
  credential-free HTTPS delivery URLs.
- CDN grants accept only canonical ASCII HTTPS targets. Backslash/authority
  ambiguity, userinfo, controls, non-canonical host/port/path, traversal and
  encoded separators fail closed before a URL reaches the browser.
- Image inspection and output verification read one bounded byte buffer before
  metadata detection and hashing. The default is 10 MiB and the configurable
  hard maximum is 50 MiB. Variant plan/output value objects self-validate so I04
  cannot persist a forged suffix, geometry, MIME, size, or digest.

## I04 Exact Shared Integration Checklist

1. Register exactly one Host-selected object-storage provider and one delivery
   adapter. Keep `local-private` as the default development provider; reject an
   unknown provider, missing adapter, or unsupported capability.
2. Add an additive Module-owned migration for image metadata, per-file delivery
   visibility/revision, and variant records. Every key and query must include
   `tenant_id`; variant storage keys stay internal. Enforce unique
   `(tenant_id, file_object_id, variant_key)`, ready/failed state checks,
   immutable source linkage, dimensions, byte size, SHA-256, and optimistic
   revision. Do not add business attachment relations.
3. Add Tenant operations for image asset listing and delivery grant issuance.
   Reuse `peanut.file-media.read`; only a separate, explicitly catalogued manage
   Permission may change public visibility or variants. Derive Tenant/member
   only from trusted context and return the same not-found shape cross-Tenant.
4. Keep private bytes behind a first-party endpoint. Validate and atomically
   consume single-use tokens before opening storage. Public/CDN responses may
   redirect only to the validated HTTPS `DeliveryGrant`; never return a local
   root, provider key, storage key, signature, or token in audit metadata.
5. Register a real image processor only when available through an accepted
   dependency decision. Verify rendered bytes with `ImageMetadataInspector`,
   compute size/SHA-256, store atomically, and persist ready metadata only after
   provider success. Failed render/storage/database writes compensate only
   objects owned by that attempt.
6. Wire the asset candidate response to `parseAssetCandidate` and
   `FileAssetSelector` in the existing standard Admin Web. Do not create a
   second Demo or copy File/Media state into another frontend store.
7. Update shared OpenAPI/generated routes and types, Runtime coverage, Module
   permissions/menus, router, source Host, canonical starter, manifests/locks,
   migration owner assertions, and focused install/upgrade evidence in I04.
8. Focus checks on Tenant A/B isolation, Permission denial, private/public
   leakage, path/symlink rejection, URL expiry/tamper/replay, provider failure,
   audit redaction, image geometry/variant uniqueness, Web parsing/selection,
   OpenAPI/generated consistency, and starter parity.

## Deferred And Prohibited

This feature does not implement application attachment relations, remote URL
fetching, chunk/resumable upload, malware or moderation engines, commercial
quota, cloud-vendor SDKs, permanent public URLs, arbitrary transforms, or
retention deletion. Aggregate, browser matrix, performance, clean install,
recovery, and cross-OS qualification remain deferred to the fixed candidate.
Completion is development evidence only; it does not publish a package, move
`dev` or a downstream-consumption lock, create a tag/release, or claim
production CDN or object-storage readiness.
