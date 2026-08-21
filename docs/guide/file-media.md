# Tenant-Private Files

Enable `peanut.file-media` in the product profile and grant the smallest of:

- `peanut.file-media.read` for list, metadata, and private download;
- `peanut.file-media.create` for one-file multipart upload;
- `peanut.file-media.delete` for optimistic archive.

The reference configuration is `backend/config/file-media.php`. Set
`FILE_MEDIA_STORAGE_ROOT` to an absolute private directory outside every Web
root. The API never accepts or returns that path. Unknown providers, invalid
roots, symlinks, traversal, failed atomic writes, and missing ready objects fail
closed as `FILE_STORAGE_UNAVAILABLE`.

The Admin Web uses the existing `/app/files` route. There is no separate Demo.
Archived rows remain metadata-only retention records, are excluded from the
default list, and cannot be downloaded.

## Object Storage And Delivery

Production Hosts implement `ObjectStorageProvider` and a `DeliveryAdapter`.
Provider capability declarations are authoritative: the existing local adapter
declares private storage only, and unknown public/signed/CDN capability fails
closed. Never reuse the provider's storage key as a browser URL.

Delivery policy is explicit per trusted file record. Private delivery uses a
single-use signed token with a short expiry. Public delivery may use a bounded
replay window, but still requires a signed HTTPS adapter response. CDN is a
delivery adapter, not another metadata or Permission domain. Persist the token
replay guard in shared atomic storage before using single-use grants across
multiple processes.

`FileAssetSelector` is the standard Admin Web material picker. Its candidates
contain only opaque file keys, display names, image dimensions, bounded variant
metadata, and ephemeral same-origin `/api/` or HTTPS delivery URLs. The parser
rejects duplicate variant keys, oversized geometry, insecure URLs, embedded URL
credentials, fragments, and internal provider/storage fields.
