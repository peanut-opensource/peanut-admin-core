# Starter v1 C02 File And Media Core Contract

## Decision

`PA-SV1-C02-S01-file-media-core` starts from
`a979063dd75db6be37bbb366b265df777c629e1b` with tree
`04a7fb688c975bd0908ecbd9dac83621c1b2c8e3`. It adds one bounded,
development-only Tenant-private file vertical slice. The reference Admin Web is
the real development workbench and starter surface; no second demo is created.

The canonical names are:

- PHP package `peanut-admin/file-media`;
- Web package `@peanut-admin/file-media`;
- Module key `peanut.file-media`;
- Tenant page `/app/files`;
- Module-owned table `pa_file_object`.

This slice adds no third-party dependency and does not upgrade any locked
version. PHP `fileinfo`, hashing, PDO, and filesystem primitives are sufficient
for the reference implementation. The local adapter is explicitly a
development and single-node reference adapter, not a production object-storage
claim.

## Objective And Non-Goals

An authenticated Tenant user with the applicable Permission can upload one
private file, list file metadata, view one metadata record, download a ready
file, and archive it. The same capability is wired into the reference Host,
standard Admin Web, and internal starter.

This slice does not provide public URLs, platform or cross-Tenant file access,
thumbnailing, media editing, CDN delivery, remote URL fetching, chunked or
resumable uploads, object-storage SDKs, application attachment relations,
malware scanning, commercial quota logic, import/export, notifications, or a
restore transition. It does not accept arbitrary filesystem paths.

## Data Contract

The `peanut.file-media` Module owns one table:

| Column | Contract |
| --- | --- |
| `id` | unsigned bigint primary key, auto increment |
| `file_key` | `varchar(64)`, non-null, opaque lower-case server-generated key, unique |
| `tenant_id` | unsigned bigint, non-null, FK to `pa_tenant.id` with `RESTRICT` |
| `storage_provider_key` | `varchar(64)`, non-null |
| `storage_key` | `varchar(255)`, non-null, server-derived opaque key only |
| `original_name` | `varchar(255)`, non-null, normalized display metadata only |
| `media_type` | `varchar(127)`, non-null, server-detected MIME type |
| `size_bytes` | unsigned bigint, non-null |
| `sha256` | `char(64)`, non-null, lower-case content digest |
| `status` | `varchar(16)`, non-null, `ready` or `archived` |
| `created_by_member_id` | unsigned bigint, non-null, FK to `pa_tenant_member.id` with `RESTRICT` |
| `revision` | unsigned bigint, non-null, default `1` |
| `created_at` | `datetime(3)`, non-null UTC |
| `updated_at` | `datetime(3)`, non-null UTC |
| `archived_at` | nullable `datetime(3)` UTC; null exactly while `ready` |

Constraints and indexes are fixed as follows:

- unique `file_key`;
- unique `(tenant_id, storage_provider_key, storage_key)`;
- index `(tenant_id, status, id)` for stable Tenant pagination;
- index `(tenant_id, sha256)` for operational duplicate inspection without
  imposing deduplication semantics;
- check `status IN ('ready', 'archived')`;
- check `size_bytes >= 0`, `revision >= 1`, and lower-case SHA-256 shape;
- check the `status`/`archived_at` pair is consistent.

The metadata table never stores file content, an arbitrary business
`owner_id`, a client path, or an absolute local path. File rows are not hard
deleted by this slice. `ready -> archived` is the only state transition;
archive increments `revision`, updates `updated_at`, and sets `archived_at`.
Archived objects remain in private storage for later retention work but cannot
be downloaded and are omitted from the default list.

The additive migration is owned by `peanut.file-media`. Clean install and
focused upgrade must apply it idempotently with the existing Settings and
Reference Codes migrations. Rollback qualification and retention deletion are
deferred to the fixed Starter v1 candidate.

## Storage Provider Contract

The reusable PHP package defines a provider interface with one bounded object
lifecycle: atomically store a readable upload stream at a server-generated
`storage_key`, open the exact stored object for read, and remove an object for
failed-write compensation. Provider selection is Host-owned and fail closed;
an unknown or unavailable provider maps to `FILE_STORAGE_UNAVAILABLE`.

The reference Host and internal starter supply a local adapter with these
rules:

- its configured root must be absolute, must resolve outside every public Web
  root, and is never returned by API, audit, or error output;
- object paths are derived only from validated opaque Tenant, file, and
  provider keys; the request cannot provide `storage_key`, a path segment, or a
  destination filename;
- `..`, absolute paths, path separators, NUL bytes, symlink traversal, and an
  existing symlink anywhere below the root fail closed;
- data is copied to a newly created private temporary file, flushed, and
  atomically renamed into place on the same filesystem;
- a partial temporary file is removed on every failed write;
- if object storage succeeds but the database transaction fails, the Host
  removes the stored object before returning an error;
- the database row is inserted as `ready` only after the atomic storage write
  succeeds, so no pending object is downloadable;
- a missing or unreadable object for an otherwise ready row is an infrastructure
  failure and never returns a local path or storage key.

The default upload policy allows at most `10 MiB` and at least PNG, JPEG, PDF,
plain text, and CSV. The server detects MIME using `fileinfo`, hashes the bytes,
and does not trust the client MIME type or filename extension. The policy is a
central immutable value that a Host can replace only with an equal or smaller
size limit and an allow-list subset. Empty uploads and upload transport errors
are invalid. Original names are reduced to a safe display basename, stripped of
control characters, limited to 255 UTF-8 characters, and never used as a path.

Malware scanning is not silently simulated. Hosts that require it must add a
separate scanning/quarantine contract before production use.

## API Contract

All routes require a validated Tenant audience and derive Tenant, account, and
member identity exclusively from trusted context. Request bodies, query
parameters, multipart fields, and headers containing `tenant_id`, `member_id`,
`storage_key`, or a filesystem path are rejected as undeclared input.

| Operation | Contract |
| --- | --- |
| `listFiles` | `GET /api/v1/files?page=&page_size=&status=`; Permission `peanut.file-media.read`; status defaults to `ready` and is `ready` or `archived`; stable descending `id` pagination |
| `createFile` | `POST /api/v1/files`; Permission `peanut.file-media.create`; `multipart/form-data` with exactly one field named `file`; returns `201` metadata plus `Location` and strong `ETag` |
| `getFile` | `GET /api/v1/files/{file_key}`; Permission `peanut.file-media.read`; returns metadata plus strong `ETag` |
| `downloadFile` | `GET /api/v1/files/{file_key}/content`; Permission `peanut.file-media.read`; ready files only; streams original bytes |
| `archiveFile` | `DELETE /api/v1/files/{file_key}`; Permission `peanut.file-media.delete`; requires one strong `If-Match`; returns archived metadata and a new strong `ETag` |

Metadata responses contain only `file_key`, `original_name`, `media_type`,
`size_bytes`, `sha256`, `status`, `revision`, `created_at`, `updated_at`, and
nullable `archived_at`. They do not contain database IDs, Tenant/member IDs,
provider keys, storage keys, or paths.

List and metadata responses use `Cache-Control: no-store` and
`X-Request-Id`. Downloads additionally use `Cache-Control: private, no-store`,
`X-Content-Type-Options: nosniff`, the detected `Content-Type`, exact
`Content-Length`, and a safe RFC-compatible `Content-Disposition: attachment`
with a quoted ASCII fallback and encoded UTF-8 filename. They never redirect to
a public or signed URL.

`file_key` is validated before lookup. Every lookup includes the context Tenant.
An unknown key, cross-Tenant key, archived content request, or absent row has the
same `404 FILE_NOT_FOUND` problem shape. Metadata detail may return archived
records; content never does. Default listing returns ready records only, while
an explicit `status=archived` lists archived metadata for authorized users.

## Concurrency, Retry, And Compensation

Strong ETags are exactly `"rev-{revision}"`. Archive requires one valid
`If-Match`; a stale revision returns `409 REVISION_CONFLICT`. A repeated archive
by the same Tenant may return the archived terminal record only when the
presented ETag matches its current revision. A cross-Tenant or unknown key is
still `FILE_NOT_FOUND`, regardless of ETag.

Upload intentionally does not use generic idempotency and has no automatic
retry. If a successful response is lost, retrying may create another file row
and object with the same hash. This boundary is documented rather than hidden
behind unsafe request replay. Reads may be retried as new reads. Archive is
optimistically concurrent and terminally repeatable as defined above.

Storage and database effects follow this order:

1. validate trusted context, Permission, multipart shape, size, MIME, name, and
   provider availability;
2. generate opaque keys and atomically store bytes;
3. insert the ready metadata row and append the creation audit in one database
   transaction;
4. on database/audit failure, remove the newly stored object;
5. expose the response only after commit.

Archive updates metadata and appends its audit in one database transaction. It
does not delete storage content. Download appends its audit before opening the
response; audit failure fails closed without returning bytes.

## Audit And Problem Details

Successful actions append Tenant audit events:

- `tenant.file.created` with action `peanut.file-media.create`;
- `tenant.file.downloaded` with action `peanut.file-media.read`;
- `tenant.file.archived` with action `peanut.file-media.delete`.

The actor comes from trusted context. The target type is `file`, and the target
identifier is `file_key`. Metadata is limited to media type, byte size, file
count `1`, revision, and status where applicable. It excludes content,
original path, absolute path, provider/storage key, token, IP address, user
agent, SQL, and stack details. Denied, invalid, not-found, and failed storage
requests do not create successful audit events.

Stable file-specific problems are:

- `FILE_UPLOAD_INVALID` (`422`) for missing, duplicate, transport-failed,
  empty, malformed multipart, unsafe name, or undeclared file input;
- `FILE_TOO_LARGE` (`413`) for a stream over the effective limit;
- `FILE_MEDIA_TYPE_DENIED` (`415`) for a detected MIME outside policy;
- `FILE_STORAGE_UNAVAILABLE` (`503`) for provider, atomic-write, open, or
  compensation failures without infrastructure disclosure;
- `FILE_NOT_FOUND` (`404`) for unknown, cross-Tenant, or non-downloadable file;
- `REVISION_CONFLICT` (`409`) for a valid but stale archive precondition.

Existing `AUTH_TOKEN_INVALID`, `AUTH_AUDIENCE_MISMATCH`,
`AUTHZ_PERMISSION_DENIED`, pagination errors, `PRECONDITION_REQUIRED`, and
`INTERNAL_ERROR` remain authoritative. Every Problem Details response is
redacted and uses `Cache-Control: no-store` plus `X-Request-Id`.

## Exact Implementation Write Set

After this independent contract commit, implementation may change only:

- `packages/php/file-media/**` and `packages/web/file-media/**`;
- `backend/app/Modules/Peanut/FileMedia/**`;
- `backend/app/controller/api/v1/FileController.php`;
- `backend/app/filemedia/**` for the Host factory and local storage adapter;
- additive entries in `backend/config/modules.php`, the Tenant route/OpenAPI
  generated route, product-profile install/upgrade paths, and focused Host
  tests under `backend/tests/{Unit,Integration,Http,Security,Upgrade}/**`;
- additive manifest, Permission, menu, profile, migration, OpenAPI schemas,
  generated TypeScript, Runtime coverage, and module-owner assertions;
- `frontend/src/modules/peanut-file-media/**`, additive `frontend` route/package
  entries, and focused page tests;
- additive internal starter backend/frontend module, adapter/config, package,
  smoke, test, and generation/verification assertions;
- root/backend/starter Composer or pnpm manifests and locks only for the new
  first-party workspaces, with no third-party version change;
- `deptrac.yaml`, `phpunit.xml`, `scripts/check-workspace`,
  `scripts/check-openapi`, `scripts/create-internal-starter`, and
  `scripts/verify-internal-starter` only for additive first-party integration;
- current capability documentation under `docs/api`, `docs/guide`,
  `docs/reference`, `docs/status`, and `docs/decisions/dependencies`, plus
  `docs/content-status.json`, `README.md`, and the generated lock evidence.

The implementation must not change `dev`, the external-host consumption lock,
release/tag metadata, any downstream product, CompanyOS/Runtime content, old
WIP/Q01 branches, existing account/authorization semantics, third-party
versions, or create another demo. A required file outside this set requires a
separate contract amendment before editing.

## Test Ownership And Acceptance

`RUNTIME-FILE-MEDIA-001` owns all five new P1 operations. Before acceptance,
focused evidence must cover:

- PHP syntax and package unit/integration behavior;
- clean migration plus focused install and repeated upgrade coexistence;
- Tenant A/B isolation, permission denial, wrong audience, and same-shape 404;
- path, absolute-path, `..`, NUL, and symlink traversal rejection;
- MIME detection, allow-list narrowing, maximum size, exact hash and byte count;
- temporary/atomic storage, storage failure, database failure compensation, and
  redacted infrastructure errors;
- archive ETag conflict, terminal repeat, default-list exclusion, and archived
  download denial;
- download headers, byte fidelity, safe filename, no-store behavior, and
  created/downloaded/archived audit redaction;
- real Host multipart HTTP behavior and undeclared Tenant/input rejection;
- OpenAPI/generated route consistency and Runtime coverage;
- Web package/page unit tests, package and frontend type checks, and frontend
  production build;
- static internal starter generation, package/module/owner-map assertions, and
  the smallest focused smoke that does not install a clean starter;
- explicit staged paths, `git diff --cached --check`, and a clean final
  worktree.

The development budget excludes `./scripts/check`, aggregate qualification,
the full browser matrix, performance, clean install, complete recovery,
`verify-internal-starter` clean installation, and cross-OS CI. Those checks are
deferred to the fixed Starter v1 candidate and must be recorded as deferred,
not implied by this slice.

Completion is `development-complete` only. It does not qualify production
object storage, malware policy, downstream consumption, package publication,
release, tag, deployment, or a stable compatibility promise.
