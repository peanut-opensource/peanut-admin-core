# P1-ED01-R01 Settings persistence scope contract

Status: approved application prerequisite; implementation candidate, not published

Prerequisite: `fc5a2181830d73e9cb5a2f6c813ccb819395aaee`

## Objective and non-goals

Extend the product-neutral `tenant-scoped` / `instance-scoped` persistence contract from P1-ED01
to the existing Settings repository and Schema exporter. The default remains `tenant-scoped`.
`instance-scoped` omits `tenant_id` only from `pa_setting_tenant_value` and
`pa_setting_target_value`; trusted logical Tenant input, member authorization, target
authorization, secret context, caching, optimistic concurrency and resolution precedence remain
unchanged.

This task does not add product Edition names, remove Tenant context from public operations, change
deployment-scoped settings, convert data between modes, add a fallback, change another Core
repository, or publish a package.

## Data and behavior contract

- `pa_setting_tenant_value`: instance scope omits `tenant_id`; uniqueness becomes
  `definition_id`. Definition and globally unique member foreign keys remain.
- `pa_setting_target_value`: instance scope omits `tenant_id`; uniqueness becomes
  `(definition_id, target_resource_key, target_id)`. Definition and globally unique member foreign
  keys remain.
- The repository keeps all existing logical Tenant and member inputs. In instance scope the Tenant
  must equal the fixed logical Tenant ID supplied at construction. A missing fixed ID, a different
  logical Tenant, or a Schema/mode mismatch fails closed before Settings storage is read or changed.
- Tenant membership authorization still checks `(tenant_id, member_id)` in the identity authority;
  target writes still require the existing authorized Host operation. Instance scope is a storage
  optimization, not an authorization bypass.
- Target → Tenant → deployment → default resolution, strong ETags, replace/unset, intervals,
  secrets, cache keys, transactions and conflict mapping do not change.

There is no migration in Core. The consuming Host owns Edition-specific fresh Schema and upgrade
migrations. Cross-mode conversion and downgrade remain outside this task.

## Exact file whitelist

- `packages/php/settings/src/Database/Schema.php`
- `packages/php/settings/src/Persistence/PdoSettingRepository.php`
- `packages/php/settings/tests/Integration/Schema/SettingsMigrationRunner.php`
- `packages/php/settings/tests/Integration/Schema/SettingsMigrationTest.php`
- `packages/php/settings/tests/Integration/Application/SettingResolverTest.php`
- `packages/php/settings/tests/Security/SettingsIsolationTest.php`
- `docs/architecture/edition-persistence-scope.md`
- `docs/reference/packages/settings.md`
- `docs/content-status.json`
- `docs/reference/document-catalog.generated.md`
- `docs/status/p1-ed01-r01-settings-persistence-scope-contract.md`
- `docs/status/index.md`

Package versions, dependency locks, identity/RBAC Schema, HTTP/OpenAPI, Web, starter, Module
manifests and unrelated status files must not change.

## Acceptance and verification owner

The Core integration owner runs the Settings migration, resolver and isolation owners once against
the registered MySQL integration resource. The group proves both Schema shapes, no Tenant column,
index or foreign-key component in instance scope, unchanged replace/unset and precedence behavior,
fixed logical Tenant rejection, member/target authorization and fail-closed Schema mismatch.

Then run `git diff --check` and Core docs governance. Full `./scripts/check`, publication and
downstream qualification are deferred to the fixed release candidate. A second failure of the
consolidated Settings group blocks this task. The implementation is one reviewable PR to `dev`; its
merge alone is not a published dependency lock.
