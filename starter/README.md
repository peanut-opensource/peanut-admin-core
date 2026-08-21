# Peanut Admin Internal Starter

This fixed internal starter verifies that a clean host can consume versioned
Peanut Admin packages. It is intentionally small and is not a public project
generator.

The generated project contains:

- a minimal ThinkPHP backend host with an external Module namespace;
- a Vue and Vite Admin Web host;
- local snapshots of `peanut-admin/core` and `@peanut-admin/admin` at version
  `0.1.0-alpha.2`;
- complete package snapshots including every internal Module migration and schema;
- a schema-validated fictional `example.greeting` Module;
- a host-owned `peanut.settings` Module backed by the Settings directory in
  `peanut-admin/core`;
- a fictional typed setting definition with repeatable synchronization and
  default resolution evidence;
- an `@peanut-admin/admin/settings` Tenant contribution composed through the
  consolidated Admin package;
- a host-owned `peanut.reference-codes` Module and
  `@peanut-admin/admin/reference-codes` Tenant contribution with no committed
  application code sets or values;
- host-owned Import/Export and Integration Security Modules with package-root
  Admin Web contributions;
- one platform Ops Console workbench for health, version, maintenance,
  backup/restore task, and redacted runtime-log contracts;
- two registered fictional Tenant Clients with independent sessions and cookies;
- a generic protected frontend transport for application-owned OpenAPI clients;
- build, type, unit, MySQL authentication, backend, and HTTP smoke checks.

Install and verify:

```bash
composer install --working-dir backend
pnpm install
php backend/tests/smoke.php
php backend/tests/auth-clients.php
php backend/tests/settings.php
php backend/tests/reference-codes.php
php backend/tests/import-export.php
php backend/tests/integration-security.php
pnpm typecheck
pnpm test
pnpm build
```

The authentication, Settings, Reference Codes, Import/Export, and Integration
Security checks require MySQL 8 and the same connection
variables used by the repository checks. Secret configuration is intentionally
blank in `.env.example`; a consuming host must supply key material outside the
generated source before enabling secret definitions. This starter does not
define template variables, CRUD generation, package publishing, source overwrite
upgrades, or compatibility promises.
