# P1-PKG12 Application Infrastructure Extraction Contract

```text
task: P1-PKG12
state: implementation-authorized
prerequisite: ddcafc21570c154a5210c400c37602529d3cba02
schema_change: none
dependency_change: none
runtime_operations: none
qualification: candidate only
```

## Objective

Move product-neutral Tenant scoping, scheduling, dictionary, settings, file
namespace, notification, OAuth/external-channel, diagnostic and Admin Web shell
behavior out of the Peanut Admin application and into the existing two public
package boundaries. The application remains the ThinkPHP Host and keeps every
framework adapter, database repository and product-domain workflow.

The extracted PHP code must depend only on PHP, PDO and existing Core contracts.
It must not import `think\\facade`, `app\\` or an application table model. The
extracted Web code must remain framework-neutral and must not import application
stores, routes or components.

## Compatibility And Security Boundary

- Tenant values are created only from trusted positive Tenant identities.
- Cache keys, locks, scheduled execution and file object paths remain Tenant
  namespaced and fail closed for malformed inputs.
- Default-Tenant and entry-binding resolution require one active matching Tenant.
- Dictionary and settings behavior is exposed through Provider contracts; the
  application owns persistence and ThinkPHP wiring.
- External callbacks reject ambiguous, inactive or provider-mismatched bindings.
- Module execution preserves deployment, Tenant enablement and authorization
  checks; application adapters may delegate but may not bypass them.
- No database schema, migration, OpenAPI operation or third-party dependency is
  added by this task.

## Exact Implementation Write Set

- `packages/php/file-media/src/Storage/TenantObjectNamespace.php`
- `packages/php/integration-security/src/Application/BrowserOAuthCallbackRoutes.php`
- `packages/php/integration-security/src/External/*.php`
- `packages/php/integration-security/src/OAuth/*.php`
- `packages/php/integration-security/src/Wechat/OfficialAccountService.php`
- `packages/php/kernel/src/Authorization/RegisteredAdminPermissionPolicy.php`
- `packages/php/kernel/src/Context/AuthenticatedMemberContext.php`
- `packages/php/kernel/src/Context/TenantContextRequirement.php`
- `packages/php/kernel/src/Dictionary/**/*.php`
- `packages/php/kernel/src/Host/ApplicationHostPolicy.php`
- `packages/php/kernel/src/Module/ModuleExecutionContext.php`
- `packages/php/kernel/src/Platform/InstanceControlPlanePolicy.php`
- `packages/php/kernel/src/Scheduling/ScheduleWindow.php`
- `packages/php/kernel/src/Tenancy/*.php` for the new extraction-owned classes
- `packages/php/notification-sms/src/Application/VerificationCodeSecret.php`
- `packages/php/notification-sms/src/Sms/*.php` for the new extraction-owned contracts
- `packages/php/ops-console/src/Logs/TenantDiagnosticAttributes.php`
- `packages/php/settings/src/Application/WebsiteConfigService.php`
- `packages/php/settings/src/Contract/WebsiteConfigStore.php`
- `packages/web/admin-core/src/access/permission-policy.ts`
- `packages/web/admin-core/src/auth/tenant-session.ts`
- `packages/web/admin-core/src/module/plugin-contribution-policy.ts`
- `packages/web/admin-core/src/module/tenant-modules.ts`
- `packages/web/admin-core/src/index.ts`
- `packages/web/admin-shell/src/deployment-mode.ts`
- `packages/web/admin-shell/src/tabs.ts`
- `packages/web/admin-shell/src/index.ts`

Package versions, dependency manifests, lockfiles, generated projections,
Starter files, reference Host Runtime, schema, migrations, OpenAPI and release
artifacts are outside this implementation commit.

## Acceptance And Stop Line

- Every changed PHP file parses and the package tree contains no ThinkPHP or
  application namespace import.
- Admin Core and Shell public indices export the new framework-neutral symbols.
- The downstream application's focused Tenant/module contracts pass while using
  the local package checkout.
- Exact write-set review and `git diff --check` pass.

Completion creates only an unqualified source candidate. It does not publish a
Composer/npm package, create or move a tag, change a downstream lock, or claim
stable compatibility. A new immutable package version requires a separate
fixed-candidate qualification and publication approval.
