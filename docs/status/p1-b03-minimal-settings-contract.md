# P1-B03 Minimal Settings Module Contract

## Status

```text
state: implementation-ready
task: P1-B03
runtime_prerequisite_commit: 3ab9a7ddf7488a9cc941b4c4f8fa9ba25470a9ad
runtime_prerequisite_tree: b306be362e8e69c12b2d398e6543a582800ab6ec
qualified_downstream_lock: 0ab02a9b735ba9f4c23509cb366b9bf04039ebf8
module_key: peanut.settings
schema_owner: peanut.settings
runtime_test_owner: RUNTIME-SETTINGS-001
dependency_change: no third-party dependency
expected_pure_branch_operations: 75 P0 + 10 P1 = 85
qualification: candidate only
```

This contract is the complete execution input for P1-B03. The aggregate
readiness plan is not an implementation specification and must not be used to
infer behavior that is absent here.

The implementation branch starts from the exact R02 commit above. W03 is an
orthogonal Web Runtime candidate and is integrated later by the single
integrator. B03 does not move or reinterpret the qualified downstream lock.

## Objective And Non-Goals

B03 provides reusable, Module-owned typed setting definitions and effective
deployment, Tenant, and optional typed-target values. An external host consumes
first-party PHP and Web packages and supplies a thin Host Module adapter; it
must not copy the reference host implementation.

B03 does not provide:

- application setting keys, product policy, feature entitlements, environment
  variable management, arbitrary JSON documents, or a universal policy store;
- a generic target-value HTTP endpoint or target editor;
- secret display, secret history, key-management administration, or a default
  production key source;
- hierarchy, approval workflow, release, package publication, or a downstream
  consumption decision.

Application Modules own their keys, schemas, defaults, target declarations,
and decisions about whether a setting is required. Peanut owns only the
reusable storage, validation, resolution, security, API, and Tenant-page
infrastructure.

## Package And Host Boundary

The reusable PHP package is `peanut-admin/settings` with namespace
`PeanutAdmin\Settings\`. The reusable Web package is
`@peanut-admin/settings`. Neither package is published by this task.

The Host Module key is exactly `peanut.settings`. Its provider must remain in
the configured Host namespace so the current Module boundary remains intact.
The provider delegates to the PHP package and owns no parallel setting model.
The reference host uses `PeanutAdmin\App\Modules\Peanut\Settings\ModuleProvider`.

The Module manifest schema adds one optional backend resource:

```json
{
  "backend": {
    "setting_definitions": "Resources/setting-definitions.json"
  }
}
```

Every enabled Module may declare zero or more definitions in that trusted
resource. Definitions cannot be created, renamed, or deleted through an API.
The loader rejects a missing resource, invalid JSON, duplicate key, duplicate
owner, owner mismatch, unsupported schema, or target declaration that is not
owned by the declaring Module.

Each definition has exactly these fields:

```text
key: local lower-case slug, maximum 64 characters
name: non-empty display name, maximum 160 characters
description: non-empty text, maximum 500 characters
schema: JSON Schema draft 2020-12 object for one value
required: boolean
secret: boolean
allowed_scopes: non-empty unique subset of deployment|tenant|target
target_resource_key: null unless target is allowed
target_operation: null unless target is allowed
default: absent or a schema-valid non-secret value
```

The stable identity is the pair `(declaring Module key, local setting key)`;
the API never accepts another owner for that pair. The local key matches
`^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$`. A target definition is valid only when the
same manifest declares the target resource and an operation whose target
cardinality accepts one explicit target. The operation is the authorization
boundary for target writes and reads.

Secret definitions accept only a string schema with `minLength >= 1` and
`maxLength <= 4096`; they cannot declare `default`. Definition digest is the
SHA-256 of canonical JSON and changes only when the trusted manifest changes.

## Schema And Migration Contract

The `peanut.settings` migration sequence is fixed and ordered:

```text
20260719030101_create_setting_definitions.php
20260719030102_create_setting_deployment_values.php
20260719030103_create_setting_tenant_values.php
20260719030104_create_setting_target_values.php
```

All four migrations implement `OwnedMigration`, report module key
`peanut.settings`, and are additive. They do not alter a P0 table. They are
reversible only for an empty clean-install test database; operational rollback
keeps the tables and returns to old code.

`pa_setting_definition` contains:

```text
id BIGINT UNSIGNED primary key
module_key VARCHAR(96) ASCII BINARY not null
setting_key VARCHAR(64) ASCII BINARY not null
name VARCHAR(160) not null
description VARCHAR(500) not null
schema_json JSON not null
required_flag TINYINT UNSIGNED not null
secret_flag TINYINT UNSIGNED not null
deployment_scope_flag TINYINT UNSIGNED not null
tenant_scope_flag TINYINT UNSIGNED not null
target_scope_flag TINYINT UNSIGNED not null
target_resource_key VARCHAR(160) ASCII BINARY null
target_operation VARCHAR(96) ASCII BINARY null
default_json JSON null
definition_digest CHAR(64) ASCII BINARY not null
status VARCHAR(16) ASCII BINARY not null: active|retired
revision BIGINT UNSIGNED not null default 1
created_at DATETIME(3) not null
updated_at DATETIME(3) not null
unique (module_key, setting_key)
```

`pa_setting_deployment_value` contains exactly:

```text
id BIGINT UNSIGNED primary key
definition_id BIGINT UNSIGNED not null, foreign key pa_setting_definition(id) RESTRICT
value_state VARCHAR(16) ASCII BINARY not null: set|unset
value_json JSON null
ciphertext VARBINARY(8192) null
nonce BINARY(24) null
key_id VARCHAR(64) ASCII BINARY null
revision BIGINT UNSIGNED not null default 1
effective_at DATETIME(3) not null
expires_at DATETIME(3) null
updated_by_operator_id BIGINT UNSIGNED not null, foreign key pa_platform_operator(id) RESTRICT
created_at DATETIME(3) not null
updated_at DATETIME(3) not null
unique (definition_id)
```

`pa_setting_tenant_value` contains exactly:

```text
id BIGINT UNSIGNED primary key
tenant_id BIGINT UNSIGNED not null, foreign key pa_tenant(id) RESTRICT
definition_id BIGINT UNSIGNED not null, foreign key pa_setting_definition(id) RESTRICT
value_state, value_json, ciphertext, nonce, key_id, revision, effective_at, expires_at
updated_by_member_id BIGINT UNSIGNED not null, foreign key pa_tenant_member(id) RESTRICT
created_at DATETIME(3) not null
updated_at DATETIME(3) not null
unique (tenant_id, definition_id)
```

`pa_setting_target_value` contains exactly:

```text
id BIGINT UNSIGNED primary key
tenant_id BIGINT UNSIGNED not null, foreign key pa_tenant(id) RESTRICT
definition_id BIGINT UNSIGNED not null, foreign key pa_setting_definition(id) RESTRICT
target_resource_key VARCHAR(160) ASCII BINARY not null
target_id VARCHAR(128) ASCII BINARY not null
value_state, value_json, ciphertext, nonce, key_id, revision, effective_at, expires_at
updated_by_member_id BIGINT UNSIGNED not null, foreign key pa_tenant_member(id) RESTRICT
created_at DATETIME(3) not null
updated_at DATETIME(3) not null
unique (tenant_id, definition_id, target_resource_key, target_id)
```

The abbreviated value columns in the Tenant and target blocks have the exact
types and constraints fixed by the deployment block. Sodium's authentication
tag is part of `ciphertext`; it is not stored in a fourth secret column.

For all value tables:

- `value_state` is exactly `set` or `unset`;
- non-secret rows store schema-valid `value_json` and null secret columns;
- secret rows store null `value_json` and non-empty authenticated-encryption
  columns;
- `expires_at`, when present, is later than `effective_at`;
- revision starts at 1 and increments by one on every replace or unset;
- foreign keys use `RESTRICT`; values are never cascaded across Tenants;
- deployment, Tenant, and target scope never share a nullable Tenant column or
  a magic Tenant/target identifier.

Definition synchronization runs during install and upgrade after manifests are
compiled and before a Tenant Module can be enabled. A changed digest updates
the trusted definition and increments its revision. Removing a manifest
definition marks it `retired`; it does not delete values. A second upgrade on
the same tree applies zero migrations and makes zero definition changes.

## Effective Resolution

Resolution uses one UTC instant and the fixed precedence:

```text
target > tenant > deployment > manifest default
```

A row is effective when `value_state = set`, `effective_at <= as_of`, and
`expires_at` is null or `as_of < expires_at`. `unset`, future, and expired rows
fall through to the next allowed scope. A corrupt JSON value, schema mismatch,
secret authentication failure, unknown key ID, duplicate row, or owner mismatch
fails closed and does not fall through.

If no effective value exists, a non-required setting resolves to `null`; a
required setting raises `SETTING_REQUIRED_VALUE_MISSING`. It must never return
an empty string, a fabricated default, or a stale value.

Target resolution requires an R02 `AuthorizedExternalOperation` for the exact
declaring Module, target resource, operation, Tenant, and one target ID. The
package rejects a generic caller, a body-supplied Tenant, a cross-Tenant target,
or a mismatched Module/operation before reading whether a value exists.

## Secret And Cache Contract

The PHP package defines a host-supplied `SecretProtector`. The reference host
adapter uses libsodium XChaCha20-Poly1305 with a 32-byte key selected by
`PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID` from the JSON object in
`PEANUT_SETTINGS_SECRET_KEYS`. Values are base64-encoded keys. Missing,
duplicate, malformed, short, or unknown keys fail closed. No fallback key is
embedded in source, starter output, logs, HTTP responses, or committed tests.

Admin reads return only `configured: true|false`, source scope, revision, ETag,
and effective interval for a secret. They never return plaintext, ciphertext,
nonce, key ID, previous values, or secret-schema validation detail. Replacing a
secret requires a new non-empty value; omitting the field preserves it only
when the operation is not a replace.

The package cache contract is revision-addressed. A cache key includes the
definition revision and every candidate value revision used by resolution.
The resolver first reads the authoritative revision vector from the same
Tenant-scoped repository; after a committed write an old cache key is
unreachable. Cache absence or failure falls back to the database, while a
database or secret failure never falls back to cache.

## API Contract

B03 adds exactly six P1 Runtime operations:

| Method and path | Operation ID | Permission | Behavior |
| --- | --- | --- | --- |
| `GET /api/platform/v1/settings` | `listDeploymentSettings` | `platform.settings.read` | list deployment definitions and redacted effective state |
| `PUT /api/platform/v1/settings/{module_key}/{setting_key}` | `replaceDeploymentSetting` | `platform.settings.manage` | create or replace one deployment value |
| `DELETE /api/platform/v1/settings/{module_key}/{setting_key}` | `unsetDeploymentSetting` | `platform.settings.manage` | write an explicit unset revision |
| `GET /api/v1/settings` | `listTenantSettings` | `peanut.settings.read` | list Tenant definitions, precedence source, and redacted state |
| `PUT /api/v1/settings/{module_key}/{setting_key}` | `replaceTenantSetting` | `peanut.settings.manage` | create or replace one Tenant value |
| `DELETE /api/v1/settings/{module_key}/{setting_key}` | `unsetTenantSetting` | `peanut.settings.manage` | write an explicit Tenant unset revision |

Platform routes use platform trusted context and never accept a Tenant.
Tenant routes derive the Tenant only from trusted context. All six responses
include `ETag` for a single resource or a collection revision. Lists are
ordered by `module_key ASC, setting_key ASC` and support no arbitrary SQL sort.

Every PUT requires `Idempotency-Key`. Creation requires `If-None-Match: *`;
replacement requires the current strong `If-Match`. DELETE requires strong
`If-Match` and `Idempotency-Key`. R02 composes trusted context, Module
availability, permission, the R01 same-PDO transaction, value mutation,
redacted audit, and idempotency completion. Exact replay returns the stored safe
response; another body with the same key is `409`.

Stable errors are:

```text
401 unauthenticated
403 permission denied
404 unknown/retired definition, unavailable owner Module, or hidden target
409 idempotency or concurrent unique conflict
412 stale ETag
422 invalid schema, scope, value, effective interval, or secret input
428 missing precondition
503 secret protector or Module deployment unavailable
500 generic redacted infrastructure failure
```

No response discloses SQL, stack traces, private paths, key material, secret
data, cross-Tenant existence, target IDs, or Provider classes. Audit metadata
contains only module key, setting key, scope, changed-field names, revision,
and redacted actor/request evidence.

There is no generic target HTTP API in B03. An owning application Module calls
the PHP target writer only after its own R02 operation obtains one authorized
typed target.

## Tenant Web Contract

`@peanut-admin/settings` exports a Tenant-audience Module contribution and page
for `/app/settings`. Deployment management remains API-only because the current
Web contribution contract permits `/app/**` Tenant routes only; B03 does not
weaken that boundary.

The page groups definitions by Module, shows type, source scope, configured
state, effective interval, and revision, and permits editing only supported
boolean, number, string, and enum schemas. Unsupported compound schemas are
read-only with an explicit state. Secret fields are write-only. Save and unset
use the current ETag, surface `412` with an explicit reload action, and never
optimistically present a secret value.

The page clears records, forms, errors, ETags, requests, and caches on Tenant
switch or Module disposal. Permission denial prevents the page chunk from
loading. The route is absent when `peanut.settings` is disabled. Desktop
`1440x900` and mobile `390x844` tests require no horizontal overflow, usable
labels, focus order, and non-overlapping dialogs.

## Required R02 Hardening

B03 may apply only the following narrow hardening to the inherited R02 host
primitives. `AuthorizedExternalOperation` becomes an unforgeable host-issued
value that carries the exact authorized operation. The atomic command adapter
accepts a host guard that runs inside the command transaction and before an
idempotent replay can return. The Settings guard locks the relevant Module
availability rows so a concurrent disable cannot race a value mutation or a
replay. These changes do not alter any other R01 or R02 authorization,
idempotency, transaction, response, or error semantics.

The two listed R02 test files own regression evidence for the unforgeable
authorization value, guard ordering, locked availability read, and unchanged
existing host behavior. No other R01 or R02 source or test file may change in
B03.

## Exact Implementation File Whitelist

After this contract commit, the B03 implementation commit may add or modify
only the following files. A needed file that is absent from this list requires
a separate contract amendment before editing.

```text
composer.json
composer.lock
backend/composer.json
deptrac.yaml
phpunit.xml
pnpm-lock.yaml
README.md
packages/php/kernel/resources/schemas/module-manifest.schema.json
packages/php/kernel/src/Host/AtomicOperationAdapter.php
packages/php/kernel/src/Host/AuthorizedExternalOperation.php
packages/php/kernel/src/Host/ExternalOperationHost.php
packages/php/kernel/src/Host/TypedTargetAdapter.php
packages/php/kernel/src/Module/Persistence/PdoModuleRuntimeRepository.php
packages/php/kernel/tests/Integration/Host/ExternalOperationHostIntegrationTest.php
packages/php/kernel/tests/Unit/Host/ExternalOperationHostTest.php
packages/php/kernel/tests/Unit/Module/ModuleRegistryCompilerTest.php
packages/php/settings/LICENSE
packages/php/settings/composer.json
packages/php/settings/src/Package.php
packages/php/settings/src/Application/EffectiveSetting.php
packages/php/settings/src/Application/SettingAdminService.php
packages/php/settings/src/Application/SettingException.php
packages/php/settings/src/Application/SettingResolver.php
packages/php/settings/src/Application/TargetSettingWriter.php
packages/php/settings/src/Cache/RevisionedSettingCache.php
packages/php/settings/src/Cache/ArrayRevisionedSettingCache.php
packages/php/settings/src/Database/Schema.php
packages/php/settings/src/Definition/SettingDefinition.php
packages/php/settings/src/Definition/SettingDefinitionLoader.php
packages/php/settings/src/Definition/SettingDefinitionRegistry.php
packages/php/settings/src/Persistence/PdoSettingRepository.php
packages/php/settings/src/Secret/SecretProtector.php
packages/php/settings/src/Secret/SecretStorageContext.php
packages/php/settings/src/Secret/SodiumSecretProtector.php
packages/php/settings/tests/Unit/Definition/SettingDefinitionLoaderTest.php
packages/php/settings/tests/Unit/Secret/SodiumSecretProtectorTest.php
packages/php/settings/tests/Integration/Application/SettingAdminServiceTest.php
packages/php/settings/tests/Integration/Application/SettingResolverTest.php
packages/php/settings/tests/Integration/Support/SettingsDatabaseTestCase.php
packages/php/settings/tests/Integration/Schema/SettingsMigrationRunner.php
packages/php/settings/tests/Integration/Schema/SettingsMigrationTest.php
packages/php/settings/tests/Security/SettingsIsolationTest.php
backend/app/Modules/Peanut/Settings/module.json
backend/app/Modules/Peanut/Settings/ModuleProvider.php
backend/app/Modules/Peanut/Settings/Database/Migrations/20260719030101_create_setting_definitions.php
backend/app/Modules/Peanut/Settings/Database/Migrations/20260719030102_create_setting_deployment_values.php
backend/app/Modules/Peanut/Settings/Database/Migrations/20260719030103_create_setting_tenant_values.php
backend/app/Modules/Peanut/Settings/Database/Migrations/20260719030104_create_setting_target_values.php
backend/app/Modules/Peanut/Settings/Resources/menus.json
backend/app/Modules/Peanut/Settings/Resources/permissions.json
backend/app/Modules/Peanut/Settings/Resources/protected-resources.json
backend/app/Modules/Peanut/Settings/Resources/setting-definitions.json
backend/app/Modules/Example/Target/module.json
backend/app/Modules/Example/Target/Resources/setting-definitions.json
backend/app/controller/api/v1/SettingsController.php
backend/app/controller/api/platform/v1/PlatformSettingsController.php
backend/app/setting/SettingsRuntimeFactory.php
backend/app/command/InstallProductProfileApplier.php
backend/app/command/UpgradeWorkflow.php
backend/config/modules.php
backend/tests/Architecture/ModuleManifestValidationTest.php
backend/tests/Contract/OpenApiArtifactTest.php
backend/tests/Http/SettingsApiTest.php
backend/tests/Integration/SettingsModuleIntegrationTest.php
backend/tests/Security/SettingsSecurityTest.php
backend/tests/Upgrade/SettingsUpgradeTest.php
backend/tests/Install/InstallWorkflowTest.php
backend/tests/Upgrade/UpgradeWorkflowTest.php
backend/tests/Install/ProductProfileTest.php
backend/tests/Install/InstallWorkflowIntegrationTest.php
backend/tests/Upgrade/UpgradeWorkflowIntegrationTest.php
profiles/reference-admin.json
packages/web/settings/LICENSE
packages/web/settings/package.json
packages/web/settings/tsconfig.json
packages/web/settings/src/contracts.ts
packages/web/settings/src/index.ts
packages/web/settings/src/runtime.ts
packages/web/settings/src/SettingsPage.vue
packages/web/settings/tests/contracts.spec.ts
packages/web/settings/tests/page.spec.ts
frontend/package.json
frontend/src/app/routes.ts
frontend/src/modules/peanut-settings/index.ts
frontend/src/modules/peanut-settings/routes.ts
frontend/tests/e2e/settings.e2e.ts
frontend/tests/e2e/full-stack.e2e.ts
frontend/tests/fixtures/api.ts
frontend/tests/fixtures/full-stack-setup.php
frontend/tests/fixtures/full-stack.ts
frontend/tests/fixtures/full-stack-vite.config.ts
docs/api/openapi.yaml
docs/api/schemas/settings.yaml
backend/route/openapi-generated.php
packages/web/admin-core/src/generated/api.d.ts
docs/status/runtime-operation-coverage.json
docs/api/index.md
docs/decisions/dependencies/index.md
docs/decisions/dependencies/p1-b03-lock-evidence.json
docs/reference/packages/settings.md
docs/guide/module-development.md
docs/content-status.json
docs/status/index.md
docs/status/p1-b03-minimal-settings-contract.md
scripts/check-openapi
scripts/check
scripts/check-workspace
scripts/create-internal-starter
scripts/verify-doc-examples
scripts/verify-internal-starter
scripts/test-unit
scripts/test-integration
scripts/test-security
starter/backend/composer.json
starter/backend/composer.lock
starter/backend/config/modules.php
starter/backend/src/Modules/Example/Greeting/module.json
starter/backend/src/Modules/Example/Greeting/Resources/setting-definitions.json
starter/backend/src/Modules/Peanut/Settings/module.json
starter/backend/src/Modules/Peanut/Settings/ModuleProvider.php
starter/backend/src/Modules/Peanut/Settings/Database/Migrations/20260719030101_create_setting_definitions.php
starter/backend/src/Modules/Peanut/Settings/Database/Migrations/20260719030102_create_setting_deployment_values.php
starter/backend/src/Modules/Peanut/Settings/Database/Migrations/20260719030103_create_setting_tenant_values.php
starter/backend/src/Modules/Peanut/Settings/Database/Migrations/20260719030104_create_setting_target_values.php
starter/backend/src/Modules/Peanut/Settings/Resources/menus.json
starter/backend/src/Modules/Peanut/Settings/Resources/permissions.json
starter/backend/src/Modules/Peanut/Settings/Resources/protected-resources.json
starter/backend/src/Modules/Peanut/Settings/Resources/setting-definitions.json
starter/backend/tests/settings.php
starter/.env.example
starter/README.md
starter/package.json
starter/pnpm-lock.yaml
starter/frontend/package.json
starter/frontend/src/app/modules.ts
starter/frontend/src/modules/peanut-settings.ts
starter/frontend/tests/settings.spec.ts
tests/recovery/RecoveryAcceptanceTest.php
tests/security/g07-evidence.json
```

The implementation must not modify the qualified lock, R01/R02 primitives
other than the exact hardening files and behavior above, W03 files,
`example.reference`, a product repository, a parent-repository Patch, a release
file, or an unlisted generated artifact.

## Test Ownership And Acceptance

`RUNTIME-SETTINGS-001` owns all six operations. Tests are written failing
before source implementation and must prove:

- manifest ownership, duplicate rejection, schema validation, definition
  synchronization, retired definitions, and idempotent upgrade;
- four-table ownership, constraints, clean install, migration order, and no P0
  table change;
- deployment, Tenant, and target precedence at exact inclusive/exclusive time
  boundaries, explicit unset fall-through, and required fail-closed behavior;
- two Tenant isolation, trusted-context-only scope, owner Module disablement,
  typed-target operation matching, and non-enumerating failures;
- JSON Schema errors, secret encryption/authentication, key selection, redacted
  reads/audit/errors, and no committed fixture key;
- creation, replace, unset, exact idempotent replay, request-hash conflict,
  `If-None-Match`, stale/missing `If-Match`, and two-connection concurrency;
- one-PDO atomic rollback after value, audit, and idempotency checkpoints;
- revision-addressed cache behavior and no stale value after commit;
- Tenant page permission/module guards, secret write-only UI, explicit 412
  reload, Tenant-switch cleanup, desktop/mobile layout, and real backend flow;
- old commit `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8` can use an upgraded database for
  health, login, all P0 routes, and the qualified external-host path; rollback
  restores the pre-upgrade backup and does not run destructive down migrations.

The isolated namespace is fixed:

```text
compose_project: peanut-admin-p1-b03
mysql_port: 33393
cache_port: 36393
backend_port: 38093
frontend_port: 35193
browser_backend_port: 38193
browser_frontend_port: 35293
database: peanut_admin_p1_b03_settings_test
```

Focused package, Host, HTTP, Web, upgrade, and security tests run first. Final
acceptance requires generated artifacts to be clean, `75 P0 + 10 P1 = 85`
operations on the pure B03 branch, four security JUnit groups with zero skips,
desktop and mobile E2E, clean install, upgrade, old-code compatibility,
recovery, unchanged P0 performance gates, starter consumption, architecture,
dependency, license, secret, documentation, `./scripts/check`, and
`git diff --check`.

## Integration And Stop Line

B03 contract and implementation are separate commits based on the fixed R02
tree. The single integrator first reviews the B03 contract, then the
implementation, then serializes migration inventory, manifest compilation,
OpenAPI, generated route/type artifacts, Runtime coverage, package locks,
starter, and integration onto the current `dev` tree. Counts are regenerated
from that resulting tree and never copied from another branch.

Completion makes B03 only an unqualified P1 candidate. It does not move
`0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`, authorize any downstream
consumer, publish a package, create a tag or release, claim production
readiness, start Q01, or add application-specific business logic to Peanut.
