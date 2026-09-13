# Settings Package

> The current source uses the injected ThinkPHP `PDOConnection`, Query API and
> transaction manager through `SettingStore`. The former
> `PdoSettingRepository` and Settings Runtime `new PDO` assembly have exited.
> See `repo://peanut-admin/docs/architecture/core-thinkphp-runtime-direction-adr.md`.

The Settings namespace inside `peanut-admin/core` provides reusable,
Module-owned typed setting definitions, encrypted values, optimistic
concurrency, and deterministic effective resolution.
`@peanut-admin/admin/settings` provides the Tenant page and host contribution.
Neither public package contains application setting keys or product policy.

## Definition Ownership

A Module declares definitions through its trusted manifest resource:

```json
{
  "backend": {
    "setting_definitions": "Resources/setting-definitions.json"
  }
}
```

The stable identity is `(module_key, setting_key)`. Definitions declare a JSON
Schema draft 2020-12 schema, allowed scopes, secret behavior, and an optional
non-secret default. Target scope additionally names one manifest-owned
single-target operation. Definitions are synchronized during install and
upgrade; removing one retires it without deleting stored values.

## Resolution

Resolution captures one UTC instant and applies this fixed order:

```text
target > tenant > deployment > manifest default
```

Unset, future, and expired rows fall through. Corrupt values, schema mismatch,
secret authentication failure, owner mismatch, or an unavailable Module fail
closed. Target access requires an `AuthorizedExternalOperation` issued by the
complete external Host authorization chain for the exact Tenant, Module,
operation, resource, and target.

Effective metadata and write preconditions are separate. `value`,
`source_scope`, `configured`, and the effective interval describe the resolved
value. `revision` and `etag` describe the row managed by the current endpoint.
A missing managed row uses the positive definition revision and a null ETag;
clients create with `If-None-Match: *`. An existing row always has a strong
ETag, including when it is unset, future, or expired.

## Persistence scope

`SettingStore` and `Schema::createSql()` default to `tenant-scoped`. A Host that stores one
physical application partition may explicitly select `instance-scoped` and supply its fixed logical
Tenant ID when constructing the repository. Only the Tenant ownership columns, indexes, foreign keys
and SQL predicates of the Tenant and target value tables are omitted. Tenant/member authorization,
target authorization, secret context and the public resolver/writer inputs remain unchanged; a
different logical Tenant or a Schema/mode mismatch fails closed.

## Secrets

The Host supplies `SecretProtector`. The reference Host reads a 32-byte active
XChaCha20-Poly1305 key from:

```text
PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID
PEANUT_SETTINGS_SECRET_KEYS
```

The second value is a JSON object from key IDs to base64-encoded keys. Missing,
malformed, short, duplicate, or unknown keys fail closed. Reads expose only
configured state and metadata; plaintext, ciphertext, nonce, key ID, and prior
values are never returned or audited. No production key is included in source
or starter output.

## Host APIs

The reference Host exposes six candidate operations:

```text
GET    /api/platform/v1/settings
PUT    /api/platform/v1/settings/{module_key}/{setting_key}
DELETE /api/platform/v1/settings/{module_key}/{setting_key}
GET    /api/v1/settings
PUT    /api/v1/settings/{module_key}/{setting_key}
DELETE /api/v1/settings/{module_key}/{setting_key}
```

Platform and Tenant audiences remain separate. Tenant identity comes only from
trusted context. Writes require one strong precondition and an
`Idempotency-Key`; definition and owner Module availability are revalidated
inside the same ThinkPHP-owned transaction before replay or mutation. There is no generic
target Settings HTTP API.

The Tenant Web route is `/app/settings` and requires
`peanut.settings.read`. Mutation additionally requires
`peanut.settings.manage`. Unsupported JSON Schema forms remain visible as
read-only definitions instead of breaking the page.

The source convergence is not qualification: the registered Settings database
credential cannot create or access its fixed test schema, so integration,
security, upgrade, and Edition candidate gates remain pending. No fallback
database or historical candidate evidence may be substituted.
