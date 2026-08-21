# Upgrade

Peanut Admin supports a local, operator-controlled database upgrade. It does not
download or checkout releases, replace application code, contact a license
server, deploy to another machine, or perform remote fleet upgrades. Deploy the
reviewed target code through your normal release process before using this
workflow.

An upgrade is not a project regeneration operation. Never overwrite an existing
application with a newer starter.

## Pre-Upgrade Checklist

1. Review the exact source and target Git commit and tree plus dependency lock changes.
2. Confirm all Module manifests compile and their dependency graph is acyclic.
3. Create, verify, and restore-test a database backup using the deployment's approved backup system.
4. Stop writes or place the deployment in a controlled maintenance window.
5. Produce a release manifest and backup evidence manifest using the contracts below.
6. Run the repository checks against the target code.

Run preflight before opening the maintenance window for migration execution:

```bash
./scripts/upgrade-preflight \
  --release-manifest /deployment-evidence/release.json \
  --backup-manifest /deployment-evidence/backup.json \
  --environment staging
```

Run the upgrade with the same immutable evidence after deploying the target
code and stopping writes:

```bash
./scripts/upgrade \
  --release-manifest /deployment-evidence/release.json \
  --backup-manifest /deployment-evidence/backup.json \
  --environment staging
```

The paths are operator inputs and are never copied into the JSON execution
report. A path, DSN, SQL statement, credential, or exception detail is not an
allowed report field.

Fresh installation is a separate operation. `scripts/install` may create the
schema only when the selected database contains no tables. It cannot be used as
an evidence-free upgrade for an older or partially installed database.

## Release Manifest

The release owner creates the manifest from two clean fixed commits. To obtain
the canonical target inventory from a clean target checkout, run:

```bash
./scripts/upgrade-preflight --print-target-inventory
```

The source inventory comes from the accepted source release evidence. Every
source entry must occur byte-for-byte in the target inventory. Removing,
renaming, or changing a historical migration is a hard stop; the only normal
delta is an appended target entry. Before any schema mutation, the package and
Module migration ledgers in the database must also exactly match that source
inventory; an empty, older, newer, failed, or otherwise mixed database is not
accepted as the declared source release.

```json
{
  "schema_version": 1,
  "release_id": "starter-v1-stage-a.1",
  "source": {
    "commit": "1111111111111111111111111111111111111111",
    "tree": "2222222222222222222222222222222222222222"
  },
  "target": {
    "commit": "3333333333333333333333333333333333333333",
    "tree": "4444444444444444444444444444444444444444"
  },
  "migrations": {
    "source": [
      {
        "owner": "kernel",
        "key": "20260716010101_create_pa_account",
        "checksum": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
      }
    ],
    "target": [
      {
        "owner": "kernel",
        "key": "20260716010101_create_pa_account",
        "checksum": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
      }
    ]
  }
}
```

Owners are `kernel`, `data-permission`, or `module:<module-key>`. The committed
stage release process owns this manifest; an application operator must not
silently regenerate it from a dirty or moving branch.

Both commit objects must resolve locally and each declared tree must be the
actual tree of its commit. Database upgrade currently requires a clean Git
checkout. A packaged distribution without `.git` fails closed because Starter
v1 does not yet publish a complete executable-file and lockfile artifact
inventory; a partial release registry is not accepted as equivalent evidence.

## Backup Evidence Manifest

The backup manifest records evidence, not a database URL or filesystem path.
The source identity must exactly equal the release manifest source, and the
environment must equal the explicit command argument.
All three timestamps use strict RFC 3339 syntax and must be ordered from create
through verification to restore test.

```json
{
  "schema_version": 1,
  "backup_id": "orders-staging-before-stage-a",
  "environment": "staging",
  "source": {
    "commit": "1111111111111111111111111111111111111111",
    "tree": "2222222222222222222222222222222222222222"
  },
  "artifact_sha256": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
  "created_at": "2026-07-24T00:00:00Z",
  "verified_at": "2026-07-24T00:10:00Z",
  "restore_tested_at": "2026-07-24T00:20:00Z"
}
```

Do not put a DSN, password, token, SQL dump, personal path, or raw customer data
in either manifest.

The workflow applies migrations in this order:

```text
Kernel
-> data-permission package
-> Modules in manifest dependency order
```

Applied Module migration files are immutable. Each file is recorded in `pa_module_migration` with a SHA-256 checksum. A changed or missing applied file stops the upgrade before any Module installation state changes.

Kernel and data-permission histories are also part of the release manifest's
append-only migration inventory. The target checkout and manifest inventory
must match exactly before a database connection can mutate schema. Within each
owner, the source sequence must be an exact prefix of the target sequence and
new keys must sort after the source owner's last key. Backdated insertions are
rejected. Migration directories and files must be physical descendants of
their canonical package or Module roots; symlinks and path escapes are rejected.

After acquiring the database upgrade lock, the workflow repeats the clean
target commit/tree check and complete migration-inventory check before it reads
the source database ledger or mutates schema. Verification and execution use
the same package and Module migration roots.

Calling `./scripts/upgrade` without evidence is retained only as a post-install
idempotency probe. It succeeds when package histories, Module histories,
manifests, installations, Settings definition digests, and Reference Codes
definition digests already match the current release, with no extra Module
ledger or installation rows. It never
applies a pending migration or synchronizes catalogs. Any pending change returns
`UPGRADE_EVIDENCE_REQUIRED`.

## JSON Report

Preflight, success, and failure return schema-versioned JSON. A normal execution
report fixes the release, environment, source, target, migration-plan digest,
Module list, applied Module migration count, backup ID, and recovery basis.
The recovery basis includes the backup artifact SHA-256, normalized verified and
restore-tested instants, and both manifest SHA-256 digests.
Errors expose a stable code and generic message only.

## Failure Behavior

- A manifest, dependency, or checksum failure stops before Module mutation.
- A migration failure records `MODULE_MIGRATION_FAILED` and leaves the Module installation failed closed.
- The workflow never pretends that an irreversible DDL change was automatically rolled back.
- Recovery stops writers, preserves the report, and uses the verified backup and matching source release.
- Do not retry from a partial state until the migration-specific recovery procedure has established whether the database must be restored.

Re-running after a successful upgrade returns `applied_module_migrations: 0` until new migration files are present.

## Schema Compatibility

Kernel and data-permission migrations use their own Phinx history tables. Module migration order and checksum ownership use `pa_module_migration`; they are not repeated for each tenant. A TenantModule enable hook may write tenant-scoped defaults but must not execute DDL.
