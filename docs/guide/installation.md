# Installation

The P0 installer is a local CLI workflow. It checks the PHP runtime, validates a static ProductProfile, applies Kernel and data-permission migrations, applies Module migrations in dependency order, bootstraps the first platform owner, and can create one initial tenant owner.

Use the [internal starter](./internal-starter.md) when the goal is to prove that a new host can consume the reusable packages. The reference installation below exercises the complete repository host and database schema.

## Requirements

- PHP 8.3 with `json`, `pdo`, `pdo_mysql`, `openssl`, and `mbstring`.
- Composer 2.10.2, with dependencies installed from the committed lock file.
- Node 24 and pnpm 11.13.0 for Admin Web and documentation builds.
- MySQL 8.4 for the reference deployment.
- Valkey for the non-authoritative cache health check.

Start the development services:

```bash
docker compose up -d mysql cache
```

Install dependencies from the repository root:

```bash
composer install
./scripts/bootstrap-worktree-dependencies
```

The bootstrap command always uses the frozen lockfile in offline mode. Only
after it reports missing cached content, run the explicitly networked warm
command and retry:

```bash
./scripts/warm-worktree-dependencies
./scripts/bootstrap-worktree-dependencies
```

The repository qualification gate checks the exact Composer and pnpm versions.
Run `composer --version` and `pnpm --version` before `./scripts/check`; using a
different Composer release is not qualification evidence even when dependency
installation succeeds.

## First Install

The default Compose database is `peanut_admin`. Set the first owner password only through the environment. The installer never prints it.

```bash
export PEANUT_BOOTSTRAP_PASSWORD='replace-with-a-strong-secret'

./scripts/install \
  --email owner@example.com \
  --display-name 'Platform Owner' \
  --tenant-code first-tenant \
  --tenant-name 'First Tenant' \
  --tenant-owner-email owner@example.com \
  --tenant-owner-name 'Tenant Owner'
```

When the tenant owner uses a different email, also set `PEANUT_TENANT_OWNER_PASSWORD`. An existing email must not receive a new password through bootstrap.

The successful JSON response contains identifiers and applied Module keys, never credentials. The initial tenant follows the normal two-step member activation and tenant activation flow.

## ProductProfile

The default profile is [`profiles/reference-admin.json`](../../profiles/reference-admin.json). It is validated by [`schemas/product-profile.schema.json`](../../schemas/product-profile.schema.json).

A profile may declare Module configuration, role-template names for preview, and an optional default Department. It cannot contain tenant identifiers, credentials, role assignments, or permission grants. P0 does not automatically apply `role_templates`; applications must use explicit, audited role-management flows.

If `default_department` is omitted, the Kernel does not create one. If present, profile application creates it idempotently without overwriting an existing Department with the same tenant-local code.

## Repeat Behavior

A normal repeat install is rejected after a platform operator exists. Use the explicit mode only to re-run preflight, migration drift checks, and installation-state checks:

```bash
./scripts/install \
  --email owner@example.com \
  --display-name 'Platform Owner' \
  --allow-existing
```

This mode does not create another owner or replay applied Module migrations.

## Health

```bash
./scripts/health-check
```

Database and application-state failures are `unhealthy`. A cache failure is `degraded` because cache data is not authoritative. The CLI returns non-zero for both degraded and unhealthy states so automation cannot silently ignore reduced service.
