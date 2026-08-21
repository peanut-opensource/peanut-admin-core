# File And Media Package

The File And Media namespace inside `peanut-admin/core` owns Tenant-private
file metadata and neutral storage and delivery contracts.
`@peanut-admin/admin/file-media` contributes the guarded `/app/files`
administration page and a reusable image asset selector to the existing Admin
Web.

The reference Host uses `local-private`, which stores opaque objects below an
absolute private root outside Web roots. It is a development and single-node
adapter. Production object storage, malware scanning, public delivery,
retention deletion, attachments, thumbnails, and resumable uploads are not
claimed by Starter v1.

The Stage B delivery package adds an `ObjectStorageProvider` capability
boundary without invalidating the existing `StorageProvider`. The
`PrivateStorageAdapter` lets the local development provider participate while
continuing to advertise private objects only. A Host must explicitly register
public, signed, and CDN capabilities; CDN delivery cannot be advertised without
signed delivery.

Delivery grants are Tenant- and Permission-bound, apply a configured private or
public policy, and validate the adapter response. Private grants are single-use
and default to at most five minutes. Public grants may use bounded replay and
default to at most one hour. Adapter URLs must be HTTPS without embedded
credentials or fragments. Audit metadata contains only adapter key,
visibility, replay mode, and expiry; it never includes the URL, signature,
token ID, provider storage key, or local path.

`SignedDeliveryTokenService` supplies a provider-neutral HMAC token contract
for a first-party delivery endpoint. Tokens bind Tenant, opaque file key,
visibility, replay mode, issued-at, expiry, and token ID. Private single-use
verification requires an atomic `ReplayGuard`; the included in-memory guard is
development/test only, not a multi-process production implementation.

Image support detects PNG/JPEG dimensions from bytes, rejects symlinks and
invalid or oversized image geometry, and builds up to 16 deterministic variant
plans. `ImageVariantProcessor` is the rendering boundary and
`ImageVariantOutputVerifier` proves the resulting MIME, dimensions, byte count,
and SHA-256 before persistence. A plan is not proof that pixels were
transformed; a Host must register a real processor and persist only verified
output metadata during Stage B integration. No image library or cloud SDK is
silently added.

Delivery URLs use one canonical ASCII HTTPS form and reject userinfo, explicit
ports, fragments, controls, backslashes, dot segments, encoded or duplicate
path separators, and ambiguous percent encoding. Image inspection
reads at most the configured byte cap (10 MiB by default, 50 MiB hard maximum)
before metadata detection and hashing. Variant plans and output evidence
validate their own key, suffix, geometry, MIME, size, and digest even when
constructed directly.

The five Tenant operations require `peanut.file-media.read`,
`peanut.file-media.create`, or `peanut.file-media.delete`. Tenant identity is
accepted only from trusted context; metadata and storage queries are always
Tenant-scoped. API and audit output never include provider keys, storage keys,
paths, content, account/member IDs, tokens, or infrastructure errors.

Hosts may narrow the 10 MiB upload limit and the default PNG, JPEG, PDF, plain
text, and CSV allow-list. They must not broaden either through untrusted input.
The server detects MIME and SHA-256 from the bytes and treats the original name
only as normalized display metadata.

See the [C02 File And Media contract](../../status/starter-v1-c02-file-media-contract.md)
for the exact schema, concurrency, compensation, API, and development stop
lines.
