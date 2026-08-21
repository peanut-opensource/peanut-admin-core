# P1-PKG01 Public Package Boundary Contract

## Status

```text
state: approved-planning-contract
prerequisite_commit: e863fdf42263ffd8074c571231651847d944042f
composer_runtime_package: peanut-admin/core
npm_runtime_package: @peanut-admin/admin
target_version: 0.1.0-alpha.1
integration_owner: P1-PKG01-INTEGRATION
deferred_verification: P1-PKG01-CONSUMER-001
```

This task replaces the current public surface of eleven Composer packages and
eleven npm packages with one package in each ecosystem. Source modules remain
separate directories for ownership and maintenance, but they cease to be
independently installable or publishable package boundaries.

The task changes dependency resolution only. It must not change product
behavior, PHP namespaces, database schemas, migrations, Runtime operations,
OpenAPI, generated routes or types, authorization, Tenant isolation, audit,
idempotency, status transitions, or user-visible results.

## Public Boundary

### Composer

`packages/php/composer.json` becomes `peanut-admin/core`. Its production
autoload maps the existing runtime namespaces to their existing source
directories:

- `PeanutAdmin\Kernel\`;
- `PeanutAdmin\DataPermission\`;
- `PeanutAdmin\Settings\`;
- `PeanutAdmin\ReferenceCodes\`;
- `PeanutAdmin\FileMedia\`;
- `PeanutAdmin\TaskJob\`;
- `PeanutAdmin\NotificationSms\`;
- `PeanutAdmin\ImportExport\`;
- `PeanutAdmin\OpsConsole\`;
- `PeanutAdmin\IntegrationSecurity\`.

`PeanutAdmin\Testing\` and all test namespaces remain development-only. The
repository-root development Host, reference backend, and generated internal
starter require only `peanut-admin/core` through one `packages/php` path
repository; no application may require an internal module package.

The package is published from an exact-commit split of `packages/php/` so a
standard Composer VCS/Packagist consumer sees `composer.json` at the published
repository root. The split repository is a generated release projection, not
a second development source of truth. Publishing the monorepo root as
`peanut-admin/core` is forbidden because it would mix Host and Web contents
into the package and would not preserve the installed-package path contract.

The eleven child `composer.json` files are deleted. No Composer `replace`,
`provide`, alias, metapackage, or dual-name compatibility layer is permitted.

### npm

`packages/web/package.json` becomes the only publishable Web manifest and is
named `@peanut-admin/admin`. Existing module directories remain in place and
are exposed through explicit subpath exports:

- `@peanut-admin/admin/core`;
- `@peanut-admin/admin/shell`;
- `@peanut-admin/admin/settings`;
- `@peanut-admin/admin/reference-codes`;
- `@peanut-admin/admin/file-media`;
- `@peanut-admin/admin/task-job`;
- `@peanut-admin/admin/notification-sms`;
- `@peanut-admin/admin/import-export`;
- `@peanut-admin/admin/ops-console`;
- `@peanut-admin/admin/integration-security`.

Testing helpers remain an internal development export and are not a runtime
dependency. The reference frontend and generated internal starter install only
`@peanut-admin/admin`. The eleven child `package.json` files are deleted. No
npm alias, re-export package, duplicate package name, or compatibility package
is permitted.

## Invariants And Owners

| Boundary | Owner | Required invariant |
| --- | --- | --- |
| Schema and migrations | existing module schema owners | no file changes |
| PHP API and namespaces | existing module API owners | namespaces and public signatures unchanged |
| OpenAPI and generated clients | Runtime contract owner | no file changes |
| Authorization and Tenant isolation | security owner | no semantic or source changes |
| Audit, idempotency and state transitions | operation owners | no semantic or source changes |
| Composer package resolution | P1-PKG01-PHP | one runtime package resolves every existing namespace |
| npm package resolution | P1-PKG01-WEB | one runtime package resolves every existing public Web entry |
| Locks, starter and workspace guards | P1-PKG01-INTEGRATION | one fixed tree and one consolidated verification round |

## P1-PKG01-PHP Write Set

Prerequisite: the independent commit containing this contract.

Only these files may change:

- `composer.json`;
- `backend/composer.json`;
- `starter/backend/composer.json`;
- new `packages/php/composer.json`;
- new `packages/php/LICENSE` copied verbatim from the repository root;
- `packages/php/kernel/composer.json` (delete);
- `packages/php/data-permission/composer.json` (delete);
- `packages/php/testing/composer.json` (delete);
- `packages/php/settings/composer.json` (delete);
- `packages/php/reference-codes/composer.json` (delete);
- `packages/php/file-media/composer.json` (delete);
- `packages/php/task-job/composer.json` (delete);
- `packages/php/notification-sms/composer.json` (delete);
- `packages/php/import-export/composer.json` (delete);
- `packages/php/ops-console/composer.json` (delete);
- `packages/php/integration-security/composer.json` (delete).

The following package identity and install-path files may change only to point
at `peanut-admin/core` and, where an installed module directory is required,
to append its existing directory below the installed core root:

- `packages/php/kernel/src/Package.php`;
- `packages/php/data-permission/src/Package.php`;
- `packages/php/testing/src/Package.php`;
- `packages/php/settings/src/Package.php`;
- `packages/php/reference-codes/src/Package.php`;
- `packages/php/file-media/src/Package.php`;
- `packages/php/integration-security/src/Package.php`;
- `packages/php/kernel/tests/Unit/PackageTest.php`;
- `packages/php/data-permission/tests/Unit/PackageTest.php`;
- `packages/php/testing/tests/Unit/PackageTest.php`;
- `backend/app/command/InstallProductProfileApplier.php`;
- `backend/app/command/UpgradeWorkflow.php`;
- `backend/app/module/RuntimeModuleRegistry.php`;
- `backend/tests/Smoke/HostPackageBoundaryTest.php`;
- `starter/backend/src/Module/ModuleRegistryFactory.php`;
- `starter/backend/tests/auth-clients.php`;
- `starter/backend/tests/reference-codes.php`;
- `starter/backend/tests/settings.php`;
- `starter/backend/tests/smoke.php`.

No other PHP file under `src/`, `app/`, `database/`, `config/`, `route/`, or
`tests/` may change in this source task. `Package::VERSION`, namespaces,
signatures, migrations, and runtime behavior remain unchanged. The task
performs static manifest and path review, checks its exact write set, runs
`git diff --check`, commits once, and stops. It runs no Composer update or
automated test.

## P1-PKG01-WEB Write Set

Prerequisite: the independent commit containing this contract. This task may
be prepared independently from P1-PKG01-PHP because their write sets do not
overlap.

Manifest files:

- new `packages/web/package.json`;
- new `packages/web/LICENSE` copied verbatim from the repository root;
- `frontend/package.json`;
- `starter/frontend/package.json`;
- `pnpm-workspace.yaml`;
- `starter/pnpm-workspace.yaml`;
- `packages/web/admin-core/package.json` (delete);
- `packages/web/admin-shell/package.json` (delete);
- `packages/web/testing/package.json` (delete);
- `packages/web/settings/package.json` (delete);
- `packages/web/reference-codes/package.json` (delete);
- `packages/web/file-media/package.json` (delete);
- `packages/web/task-job/package.json` (delete);
- `packages/web/notification-sms/package.json` (delete);
- `packages/web/import-export/package.json` (delete);
- `packages/web/ops-console/package.json` (delete);
- `packages/web/integration-security/package.json` (delete).

The following source files may change only by replacing an existing internal
`@peanut-admin/*` import, export, mock, or type reference with the matching
`@peanut-admin/admin/*` subpath. No executable statement, template, style,
test assertion, state, route, or public type may otherwise change:

- `frontend/src/app/context-generation.ts`;
- `frontend/src/app/contracts.ts`;
- `frontend/src/app/host-config.ts`;
- `frontend/src/app/modules.ts`;
- `frontend/src/app/router-meta.d.ts`;
- `frontend/src/app/router.ts`;
- `frontend/src/app/routes.ts`;
- `frontend/src/app/runtime.ts`;
- `frontend/src/app/store.ts`;
- `frontend/src/components/targets/candidates.ts`;
- `frontend/src/modules/example-reference/pages/ReferenceListPage.vue`;
- `frontend/src/modules/example-reference/routes.ts`;
- `frontend/src/modules/example-target/pages/TargetListPage.vue`;
- `frontend/src/modules/example-target/routes.ts`;
- `frontend/src/modules/example-work-item/pages/WorkItemListPage.vue`;
- `frontend/src/modules/example-work-item/pages/WorkItemPolicyPage.vue`;
- `frontend/src/modules/example-work-item/routes.ts`;
- `frontend/src/modules/peanut-file-media/routes.ts`;
- `frontend/src/modules/peanut-import-export/routes.ts`;
- `frontend/src/modules/peanut-integration-security/routes.ts`;
- `frontend/src/modules/peanut-notification-sms/routes.ts`;
- `frontend/src/modules/peanut-reference-codes/routes.ts`;
- `frontend/src/modules/peanut-settings/routes.ts`;
- `frontend/src/modules/peanut-task-job/routes.ts`;
- `frontend/src/modules/unconfigured-client.ts`;
- `frontend/src/pages/common/AccountPage.vue`;
- `frontend/src/pages/common/DashboardPage.vue`;
- `frontend/src/pages/common/EffectiveAccessPreviewPage.vue`;
- `frontend/src/pages/common/ResourceCollectionPage.vue`;
- `frontend/src/pages/common/resources.ts`;
- `frontend/src/pages/governance/GovernanceWorkbenchPage.vue`;
- `frontend/src/pages/platform/OpsConsoleHostPage.vue`;
- `frontend/src/pages/platform/TenantDetailPage.vue`;
- `frontend/src/pages/platform/UpgradeStatusPage.vue`;
- `frontend/src/pages/status/StatusPage.vue`;
- `frontend/src/shell/WorkspaceLayout.vue`;
- `frontend/src/shell/host-config.ts`;
- `frontend/tests/account-page.spec.ts`;
- `frontend/tests/file-media-page.spec.ts`;
- `frontend/tests/package-boundary.spec.ts`;
- `frontend/tests/reference-codes-page.spec.ts`;
- `frontend/tests/runtime.spec.ts`;
- `packages/web/admin-core/src/index.ts`;
- `packages/web/admin-core/tests/package.spec.ts`;
- `packages/web/admin-shell/src/index.ts`;
- `packages/web/admin-shell/src/targets.ts`;
- `packages/web/admin-shell/tests/package.spec.ts`;
- `packages/web/file-media/src/FileAssetSelector.vue`;
- `packages/web/file-media/src/FileMediaPage.vue`;
- `packages/web/file-media/src/index.ts`;
- `packages/web/file-media/src/runtime.ts`;
- `packages/web/import-export/src/ImportExportPage.vue`;
- `packages/web/import-export/src/runtime.ts`;
- `packages/web/integration-security/src/runtime.ts`;
- `packages/web/notification-sms/src/NotificationInboxPage.vue`;
- `packages/web/notification-sms/src/index.ts`;
- `packages/web/notification-sms/src/runtime.ts`;
- `packages/web/ops-console/src/OpsConsolePage.vue`;
- `packages/web/reference-codes/src/ReferenceCodesPage.vue`;
- `packages/web/reference-codes/src/index.ts`;
- `packages/web/reference-codes/src/runtime.ts`;
- `packages/web/settings/src/SettingsPage.vue`;
- `packages/web/settings/src/index.ts`;
- `packages/web/settings/src/runtime.ts`;
- `packages/web/settings/tests/page.spec.ts`;
- `packages/web/task-job/src/TaskJobPage.vue`;
- `packages/web/task-job/src/runtime.ts`;
- `packages/web/task-job/tsconfig.json`;
- `packages/web/testing/src/index.ts`;
- `packages/web/testing/tests/package.spec.ts`;
- `packages/web/ops-console/tsconfig.json`;
- `starter/frontend/src/App.vue`;
- `starter/frontend/src/clients.ts`;
- `starter/frontend/src/modules/example-greeting/index.ts`;
- `starter/frontend/src/modules/peanut-file-media.ts`;
- `starter/frontend/src/modules/peanut-import-export.ts`;
- `starter/frontend/src/modules/peanut-integration-security.ts`;
- `starter/frontend/src/modules/peanut-notification-sms.ts`;
- `starter/frontend/src/modules/peanut-ops-console.ts`;
- `starter/frontend/src/modules/peanut-reference-codes.ts`;
- `starter/frontend/src/modules/peanut-settings.ts`;
- `starter/frontend/src/modules/peanut-task-job.ts`;
- `starter/frontend/tests/file-media.spec.ts`;
- `starter/frontend/tests/reference-codes.spec.ts`;
- `starter/frontend/tests/settings.spec.ts`;
- `starter/frontend/verification/clients.spec.ts`;
- `starter/frontend/verification/module.spec.ts`.

The task performs static import mapping review, checks its exact write set,
runs `git diff --check`, commits once, and stops. It runs no pnpm install,
typecheck, unit test, or build.

## P1-PKG01-INTEGRATION Write Set

Prerequisite: P1-PKG01-PHP and P1-PKG01-WEB integrated in that order.

Only the following integration-owned files may change:

- `composer.lock`;
- `starter/backend/composer.lock`;
- `pnpm-lock.yaml`;
- `starter/pnpm-lock.yaml`;
- `package.json`;
- `starter/package.json`;
- `scripts/bootstrap-worktree-dependencies`;
- `scripts/check`;
- `scripts/check-architecture`;
- `scripts/check-workspace`;
- `scripts/create-internal-starter`;
- `scripts/test-integration`;
- `scripts/test-security`;
- `scripts/test-unit`;
- `scripts/verify-internal-starter`;
- `backend/tests/Upgrade/SettingsUpgradeTest.php`;
- `backend/app/Modules/Peanut/FileMedia/module.json`;
- `backend/app/Modules/Peanut/ImportExport/module.json`;
- `backend/app/Modules/Peanut/IntegrationSecurity/module.json`;
- `backend/app/Modules/Peanut/NotificationSms/module.json`;
- `backend/app/Modules/Peanut/ReferenceCodes/module.json`;
- `backend/app/Modules/Peanut/Settings/module.json`;
- `backend/app/Modules/Peanut/TaskJob/module.json`;
- `starter/backend/src/Modules/Peanut/FileMedia/module.json`;
- `starter/backend/src/Modules/Peanut/ImportExport/module.json`;
- `starter/backend/src/Modules/Peanut/IntegrationSecurity/module.json`;
- `starter/backend/src/Modules/Peanut/NotificationSms/module.json`;
- `starter/backend/src/Modules/Peanut/ReferenceCodes/module.json`;
- `starter/backend/src/Modules/Peanut/Settings/module.json`;
- `starter/backend/src/Modules/Peanut/TaskJob/module.json`;
- `tools/project-generator/src/ProjectGenerator.php`;
- `tests/starter/assert-generated-starter.php`;
- `starter/README.md`;
- `docs/architecture/index.md`;
- `docs/guide/admin-web.md`;
- `docs/guide/module-development.md`;
- `docs/reference/packages/data-permission.md`;
- `docs/reference/packages/file-media.md`;
- `docs/reference/packages/reference-codes.md`;
- `docs/reference/packages/settings.md`;
- `docs/reference/third-party-licenses.generated.md` (generated only by its existing writer);
- `README.md` and `docs/status/index.md` only for candidate status;
- new `docs/decisions/dependencies/p1-pkg01-lock-evidence.json`.

The independently authorized workspace baseline-repair sections below are
narrow additional write sets for the exact files and mechanical changes they
name. They do not otherwise expand this integration write set.

The integration owner rejects any source change outside the two source-task
commits, updates all four locks once, updates guards from per-module package
counts to the two public boundaries, changes each first-party Module manifest
to `php_package: peanut-admin/core` and the matching
`web_package: @peanut-admin/admin/<subpath>`, updates canonical package guidance
without rewriting historical evidence, and performs one consolidated
verification round immediately before its commit:

1. `./scripts/check-workspace`;
2. one isolated Composer consumer requiring `peanut-admin/core` from the
   `packages/php` package root and resolving all ten runtime namespaces plus
   the installed package path;
3. one isolated pnpm consumer installing `@peanut-admin/admin` and resolving
   every public subpath;
4. `git diff --check`.

If a group fails, the integration owner performs one static batch repair and
reruns only that failed group once. A second failure blocks the stage.

## Workspace Baseline Repair Write Set

The first authorized `./scripts/check-workspace` integration run exposed seven
stale baseline assertions that predate and are behaviorally independent from
the two-package boundary. The integration owner may repair only the following
files before the single allowed rerun of that failed verification group:

- `backend/app/Modules/Peanut/ImportExport/module.json`;
- `starter/backend/src/Modules/Peanut/ImportExport/module.json`;
- `backend/tests/Contract/ModuleGuardMiddlewareTest.php`;
- `backend/tests/Install/ProductProfileTest.php`;
- `packages/php/kernel/tests/Integration/Schema/MigrationInventoryTest.php`;
- `packages/php/kernel/tests/Unit/Menu/MenuCatalogSynchronizerTest.php`;
- `backend/app/Modules/Peanut/IntegrationSecurity/Resources/permissions.json`;
- `starter/backend/src/Modules/Peanut/IntegrationSecurity/Resources/permissions.json`.

The two Module manifests may only replace their legacy string dependency list
with the existing object-shaped dependency contract while retaining the new
`peanut-admin/core` and `@peanut-admin/admin/import-export` package mappings.
The four test files may only align constructor fixtures and inventory counts
with the production source already present at prerequisite commit `e863fdf`.
No production PHP behavior, schema, migration, menu catalog, Product Profile,
package boundary, authorization, Tenant, audit, or user-visible result may
change. After one static batch repair, only `./scripts/check-workspace` may be
rerun once; another failure blocks P1-PKG01.

The two Integration Security permission catalogs may only replace the invalid
permission type `navigation` with the schema-defined navigation permission type
`menu`. Permission keys, names, risk levels, routes, authorization semantics,
and every other catalog entry remain unchanged.

## Workspace PHPStan Baseline Repair Write Set

After PHPUnit passed all 562 tests, the same authorized workspace run exposed
97 pre-existing PHPStan errors in the following exact files:

- `backend/app/AppService.php`;
- `backend/app/command/UpgradeCli.php`;
- `backend/app/command/UpgradeWorkflow.php`;
- `backend/app/controller/api/platform/v1/OpsConsoleController.php`;
- `backend/app/filemedia/FileDeliveryHttpRuntime.php`;
- `backend/app/filemedia/FileDeliveryRepository.php`;
- `backend/app/importexport/ImportExportHttpRuntime.php`;
- `backend/app/importexport/TenantMemberDirectoryProvider.php`;
- `backend/app/integrationsecurity/CurlPinnedWebhookTransport.php`;
- `backend/app/integrationsecurity/IntegrationSecurityHttpRuntime.php`;
- `backend/app/integrationsecurity/IntegrationSecurityRuntimeFactory.php`;
- `backend/app/middleware/PlatformAuthRuntimeFactory.php`;
- `backend/app/middleware/TenantAccountRuntimeFactory.php`;
- `backend/app/middleware/TenantAuthRuntimeFactory.php`;
- `backend/app/notification/NotificationHttpRuntime.php`;
- `backend/app/ops/HostRuntimeStatusProvider.php`;
- `backend/app/ops/PdoMaintenanceWindowStore.php`;
- `backend/app/ops/PdoOpsTaskDispatcher.php`;
- `backend/app/ops/PdoRuntimeLogProvider.php`;
- `backend/app/task/TaskHttpRuntime.php`;
- `backend/app/upgrade/BackupManifest.php`;
- `backend/app/upgrade/MigrationInventory.php`;
- `backend/tests/Upgrade/UpgradeLifecycleTest.php`;
- `packages/php/kernel/src/Authorization/Governance/GovernancePermissionCatalog.php`;
- `packages/php/kernel/src/Host/ExternalOperationResult.php`;
- `packages/php/kernel/src/Platform/Application/PlatformWorkspaceQueryService.php`;
- `packages/php/kernel/tests/Unit/Governance/GovernanceWorkbenchTest.php`.

This independent baseline repair may add or correct native types, precise
PHPDoc array shapes, local variable narrowing, explicit list normalization,
constructor assignments, and test fixture annotations only. It must not alter
public signatures except to express an already enforced value type, change a
query, branch, exception, status, permission, Tenant boundary, schema, route,
API result, or user-visible behavior. PHPStan baselines, ignore directives,
configuration weakening, inline `@var` overrides, compatibility shims, and new
dependencies are forbidden. Each disjoint file group receives static review
and `php -l`; the integration owner runs PHPStan once on the combined tree.

## Workspace Deptrac Baseline Repair Write Set

After PHPStan passed, the authorized workspace continuation exposed four
identical Deptrac violations because the shared
`PeanutAdmin\App\module\TenantWideModuleProvider` abstraction was collected as
general Backend code. Only `deptrac.yaml` may change in this repair. It must
place that exact class in a dedicated module-provider support layer, remove it
from the general Backend collector, and allow only Module Internals to depend
on the new layer. It must not allow Module Internals to depend on the Backend
layer, change source code, suppress a violation, or weaken uncovered-symbol
enforcement.

The same repair must register the existing TaskJob, NotificationSms,
ImportExport, OpsConsole, and IntegrationSecurity source directories and
namespaces as first-party layers. Their dependency edges remain the exact
former package-manifest graph: TaskJob to Kernel; NotificationSms to Kernel
and TaskJob; ImportExport to Kernel, FileMedia, and TaskJob; OpsConsole to
Kernel and TaskJob; IntegrationSecurity to Kernel. Backend may consume all
five layers. Module Internals may consume only TaskJob, NotificationSms,
ImportExport, and IntegrationSecurity for their owned migrations; OpsConsole
remains platform Backend infrastructure. `--fail-on-uncovered` stays enabled.

## Workspace PHP CS Fixer Baseline Repair Write Set

After PHPUnit, PHPStan, and Deptrac passed, the same authorized workspace
continuation exposed formatting differences in exactly 85 files. The frozen
sorted path inventory has SHA-256
`b87174327141e84f85a08896ca8074a708f0e7e068fd3aba2f58beb257e7cc51` and
contains only:

- `backend/app/AppService.php`;
- `backend/app/Modules/Peanut/FileMedia/Database/Migrations/20260724020102_create_file_delivery.php`;
- `backend/app/Modules/Peanut/ImportExport/Database/Migrations/20260724040101_create_import_export.php`;
- `backend/app/Modules/Peanut/ImportExport/ModuleProvider.php`;
- `backend/app/Modules/Peanut/IntegrationSecurity/Database/Migrations/20260724040301_create_integration_security.php`;
- `backend/app/Modules/Peanut/IntegrationSecurity/ModuleProvider.php`;
- `backend/app/Modules/Peanut/NotificationSms/Database/Migrations/20260724030201_create_notifications.php`;
- `backend/app/command/TaskWorkerCommand.php`;
- `backend/app/controller/api/platform/v1/OpsConsoleController.php`;
- `backend/app/controller/api/v1/FileController.php`;
- `backend/app/controller/api/v1/ImportExportController.php`;
- `backend/app/controller/api/v1/IntegrationSecurityController.php`;
- `backend/app/controller/api/v1/MemberAdminRuntime.php`;
- `backend/app/controller/api/v1/NotificationController.php`;
- `backend/app/controller/api/v1/TaskController.php`;
- `backend/app/filemedia/FileDeliveryHttpRuntime.php`;
- `backend/app/filemedia/FileDeliveryRepository.php`;
- `backend/app/filemedia/LocalSignedDeliveryAdapter.php`;
- `backend/app/filemedia/PdoDeliveryReplayGuard.php`;
- `backend/app/http/TenantModuleRuntime.php`;
- `backend/app/importexport/ImportExportHttpRuntime.php`;
- `backend/app/importexport/ImportExportRuntimeFactory.php`;
- `backend/app/importexport/PdoFileMediaGateway.php`;
- `backend/app/importexport/TenantMemberDirectoryProvider.php`;
- `backend/app/integrationsecurity/CurlPinnedWebhookTransport.php`;
- `backend/app/integrationsecurity/IntegrationSecurityHttpRuntime.php`;
- `backend/app/integrationsecurity/IntegrationSecurityRuntimeFactory.php`;
- `backend/app/notification/NotificationHttpRuntime.php`;
- `backend/app/notification/NotificationRuntimeFactory.php`;
- `backend/app/notification/PdoAttachmentResolver.php`;
- `backend/app/notification/PdoRecipientResolver.php`;
- `backend/app/ops/HostRuntimeStatusProvider.php`;
- `backend/app/ops/OpsRuntimeFactory.php`;
- `backend/app/ops/PdoMaintenanceWindowStore.php`;
- `backend/app/ops/PdoOpsTaskDispatcher.php`;
- `backend/app/ops/PdoPlatformPermissionChecker.php`;
- `backend/app/ops/PdoRuntimeLogProvider.php`;
- `backend/app/ops/ReferenceBackupRestoreProvider.php`;
- `backend/app/task/PdoTaskAuthorizationRevalidator.php`;
- `backend/app/task/TaskHttpRuntime.php`;
- `packages/php/import-export/src/Application/ImportExportException.php`;
- `packages/php/import-export/src/Application/ImportExportService.php`;
- `packages/php/import-export/src/Contract/SchemaDefinition.php`;
- `packages/php/import-export/src/Execution/CsvOperationRunner.php`;
- `packages/php/import-export/src/Execution/ImportExportTaskHandler.php`;
- `packages/php/import-export/src/Execution/ImportExportTaskSubmissionProvider.php`;
- `packages/php/import-export/src/Persistence/PdoImportExportRepository.php`;
- `packages/php/import-export/tests/feature-harness.php`;
- `packages/php/integration-security/src/Application/IntegrationSecurityException.php`;
- `packages/php/integration-security/src/Application/MachineIdentityService.php`;
- `packages/php/integration-security/src/Application/MachineScopeCatalog.php`;
- `packages/php/integration-security/src/Application/MachineScopeGrantPolicy.php`;
- `packages/php/integration-security/src/Application/SessionSecurityService.php`;
- `packages/php/integration-security/src/Application/WebhookDeliveryLogService.php`;
- `packages/php/integration-security/src/Application/WebhookService.php`;
- `packages/php/integration-security/src/Persistence/IntegrationSecurityRepository.php`;
- `packages/php/integration-security/src/Persistence/PdoIntegrationSecurityRepository.php`;
- `packages/php/integration-security/src/Webhook/TrustedWebhookEvent.php`;
- `packages/php/integration-security/src/Webhook/WebhookDestinationPolicy.php`;
- `packages/php/integration-security/src/Webhook/WebhookDispatcher.php`;
- `packages/php/integration-security/tests/feature-harness.php`;
- `packages/php/integration-security/tests/mysql-harness.php`;
- `packages/php/kernel/src/Platform/Application/PlatformWorkspaceQueryService.php`;
- `packages/php/kernel/src/Tenancy/Application/TenantWorkspaceQueryService.php`;
- `packages/php/kernel/tests/Unit/Governance/GovernanceWorkbenchTest.php`;
- `packages/php/notification-sms/src/Persistence/PdoNotificationRepository.php`;
- `packages/php/notification-sms/tests/feature-harness.php`;
- `packages/php/notification-sms/tests/mysql-harness.php`;
- `packages/php/ops-console/src/Application/OpsConsoleException.php`;
- `packages/php/ops-console/src/Logs/RuntimeLogProviderRegistry.php`;
- `packages/php/ops-console/src/Logs/RuntimeLogQuery.php`;
- `packages/php/ops-console/src/Logs/RuntimeLogService.php`;
- `packages/php/ops-console/src/Logs/SafeLogMessageCatalog.php`;
- `packages/php/ops-console/src/Logs/StructuredLogBatch.php`;
- `packages/php/ops-console/src/Logs/StructuredLogRecord.php`;
- `packages/php/ops-console/src/Maintenance/MaintenanceReasonRegistry.php`;
- `packages/php/ops-console/src/Maintenance/MaintenanceService.php`;
- `packages/php/ops-console/src/Status/OpsStatusSnapshot.php`;
- `packages/php/ops-console/src/Task/BackupRestoreProviderRegistry.php`;
- `packages/php/ops-console/src/Task/OpsTask.php`;
- `packages/php/ops-console/src/Task/OpsTaskService.php`;
- `packages/php/ops-console/src/Task/TaskJobStatusProjection.php`;
- `packages/php/ops-console/tests/feature-harness.php`;
- `packages/php/task-job/src/Persistence/PdoTaskJobRepository.php`;
- `packages/php/task-job/tests/feature-harness.php`;

The integration owner may run the existing PHP CS Fixer configuration once to
mechanically rewrite only this frozen inventory. No formatter configuration,
public signature, query, branch, exception, status, permission, Tenant
boundary, schema, route, API result, or user-visible behavior may change. The
owner must verify that the actual changed path set exactly equals the frozen
inventory, then rerun only the failed PHP CS Fixer check once. A mismatch or a
second formatter failure blocks P1-PKG01.

## Workspace ESLint Baseline Repair Write Set

After PHP CS Fixer passed, the first authorized Web lint run exposed one
unused local binding and 609 pre-existing Vue template-layout warnings in
exactly these eight files:

- `frontend/src/pages/governance/GovernanceWorkbenchPage.vue`;
- `frontend/src/pages/platform/UpgradeStatusPage.vue`;
- `packages/web/file-media/src/FileAssetSelector.vue`;
- `packages/web/file-media/src/FileMediaPage.vue`;
- `packages/web/import-export/src/ImportExportPage.vue`;
- `packages/web/integration-security/src/IntegrationSecurityPage.vue`;
- `packages/web/ops-console/src/OpsConsolePage.vue`;
- `packages/web/task-job/src/TaskJobPage.vue`.

The integration owner may run the existing ESLint configuration with `--fix`
against only these eight explicit paths, then mechanically wrap remaining Vue
attributes and element contents to satisfy the same existing rules. In
`FileAssetSelector.vue`, the unused local `props` binding may be removed while
retaining the same `withDefaults(defineProps(...))` declaration. No executable
statement, template element, directive, event, condition, style value, public
type, route, API behavior, or user-visible result may otherwise change. Only
the failed `pnpm lint` group may be rerun once after the complete batch; a
changed path outside this inventory or a second failure blocks P1-PKG01.

## Workspace Unit-Test Baseline Repair Write Set

After lint and typecheck passed, the first authorized Web unit run exposed two
test-assembly gaps caused by the consolidated package boundary. Only these
files may change:

- `frontend/tests/w03-shell.spec.ts`;
- `package.json`;
- `pnpm-lock.yaml`.

The Shell spec may add a two-entry `routeRegistry` to its existing mocked
Admin Runtime for the already mocked Tenant and platform menu routes. The root
manifest may add `@peanut-admin/admin` at `workspace:0.1.0-alpha.1` as a
development-only dependency so root-owned Vitest can resolve package subpaths
used by collected Starter tests. The root lock may change only by adding that
workspace link to the root importer; no third-party resolution, version,
integrity, peer graph, other importer, test assertion, production source, or
runtime behavior may change.

The integration owner refreshes the dependency layout offline, statically
checks the exact three-file write set and lock delta, then reruns only the
failed `pnpm test:unit` group once. A changed path outside this inventory or a
second failure blocks P1-PKG01.

## P1-PKG01-R01 Root Peer Consumer Remediation

The original P1-PKG01 unit budget is closed after its second failure. R01 is an
independent dependency-layout repair with verification identifier
`P1-PKG01-R01-UNIT-001`; it does not reopen or rewrite the prior results.

Static resolution evidence shows that `packages/web` and `frontend` can both
resolve `vue/server-renderer`, while the repository-root Vitest consumer cannot.
The root now directly consumes `@peanut-admin/admin` for package-boundary tests
but does not install that package's required Vue peer. Only these files may
change:

- `package.json`;
- `pnpm-lock.yaml`.

The root manifest may add `vue` at the already locked exact version `3.5.39`
as a development-only dependency. The root lock may add only the matching root
importer entry; no package snapshot, third-party version, integrity, peer graph,
other importer, source, test, configuration, or runtime behavior may change.
Adding Vue as a production dependency of `@peanut-admin/admin`, changing peer
requirements, adding an alias, hoisting dependency layouts, or adding another
package is forbidden.

The R01 owner refreshes the dependency layout offline, proves a root Node
consumer can import `vue/server-renderer`, verifies the exact two-file write
set and lock delta, then runs `pnpm test:unit` once. A failure receives one
read-only diagnosis and stops R01; it does not authorize another unit rerun.

## P1-PKG01-R02 Exact Vue Alias Remediation

R01 is closed after its single unit run. Its read-only Vite resolver diagnosis
proved that the object-form string alias `vue` also rewrites the
`vue/server-renderer` subpath to the invalid filesystem path
`vue.esm-bundler.js/server-renderer`. R02 is an independent configuration
repair with verification identifier `P1-PKG01-R02-UNIT-001`.

Only `vitest.config.ts` may change. The existing Vue replacement path must stay
unchanged, while the alias key becomes the exact regular expression `/^vue$/`
so only the bare `vue` import is deduplicated. No other alias, plugin, test
selection, environment, dependency, source, test assertion, or runtime behavior
may change.

The R02 owner first proves through the Vite resolver that bare `vue` still uses
the fixed frontend instance and `vue/server-renderer` no longer resolves below
`vue.esm-bundler.js`. It then runs `pnpm test:unit` once. A failure receives one
read-only diagnosis and stops R02; it does not authorize another unit rerun.

## P1-PKG01-R03 Starter Platform Route Ownership Remediation

R02 is closed after its single unit run. Its read-only failure diagnosis proved
that the Starter registers the platform Ops Console route through
`defineAdminModule`, whose Tenant Module contract correctly accepts only
`/app/*` routes. The reference Host already owns `/platform/ops` as a platform
route outside its Tenant Module array. R03 is an independent Starter assembly
repair with verification identifier `P1-PKG01-R03-UNIT-001`.

Only these files may change:

- `starter/frontend/src/modules/peanut-ops-console.ts`;
- `starter/frontend/src/app/modules.ts`;
- `starter/frontend/tests/ops-console.spec.ts`;
- `starter/frontend/tests/settings.spec.ts`;
- `starter/frontend/tests/reference-codes.spec.ts`;
- `starter/frontend/tests/file-media.spec.ts`;
- `starter/frontend/tests/task-job.spec.ts`;
- `starter/frontend/tests/notification-sms.spec.ts`;
- `starter/frontend/tests/import-export.spec.ts`;
- `starter/frontend/tests/integration-security.spec.ts`.

The Ops Console Host must expose a Host-owned platform route and its existing
runtime. The route remains `/platform/ops`, uses the platform audience and
`platform.ops.read` permission, and renders the existing package page with the
existing fail-closed runtime. `createStarterModules` must keep the Ops Console
route and runtime available to the Host while excluding it from the Tenant
`modules` array. The seven existing Tenant Module inventory assertions may
only remove the Ops Console key. The Ops Console test must assert the platform
route contract and its absence from the Tenant Module inventory.

Changing `defineAdminModule`, accepting `/platform/*` as a Tenant Module route,
moving the Ops Console below `/app`, changing a production route, permission,
transport, runtime capability, page, or user-visible behavior, or adding an
alias or compatibility layer is forbidden.

The R03 owner statically verifies this exact ten-file write set, runs
`git diff --check`, and then runs `pnpm test:unit` once. A failure receives one
read-only diagnosis and stops R03; it does not authorize another unit rerun.
After R03 passes, the P1-PKG01 integration owner may continue with the not-yet-
run `pnpm build` and `docker compose config --quiet` groups exactly once each.

## Publication Stop Line

P1-PKG01 produces a fixed package-boundary candidate only. It does not create
a tag, GitHub Release, Packagist version, npm version, compatibility promise,
production claim, or downstream lock movement.

Publication is a separate P1-PKG02 task. It may publish only immutable
`0.1.0-alpha.1` versions after both isolated consumer probes pass on the exact
fixed commit and a separate publication approval records registry ownership,
credentials, the generated `packages/php` split repository, provenance,
package contents, license, checksum, and rollback by version replacement
rather than deletion.

## Stop Conditions

Work stops instead of expanding the whitelist when:

- any PHP namespace or Web public type must change;
- any schema, migration, route, OpenAPI, generated artifact, authorization,
  Tenant, audit, idempotency, status, or business behavior would change;
- a compatibility alias or old package name appears necessary;
- a new third-party dependency is required;
- the checked-in starter cannot consume the two new boundaries without source
  behavior changes;
- publication would require a mutable version or deleting historical versions.
