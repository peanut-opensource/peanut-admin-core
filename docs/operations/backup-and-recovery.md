# Backup And Recovery

The reference recovery workflow orchestrates MySQL `mysqldump` and `mysql`. It does not implement a custom database backup format. Production deployments should use their cloud or database platform's mature snapshot and point-in-time recovery features, while retaining the same overwrite, integrity, and verification contracts.

## Create A Reference Backup

With the reference MySQL container running:

```bash
DB_DATABASE=peanut_admin \
./scripts/backup-mysql --output /secure/path/peanut-admin-backup
```

The new output directory contains:

- `dump.sql`: a consistent single-transaction dump;
- `dump.sql.sha256`: an independent checksum file;
- `inventory.json`: exact row counts, MySQL table checksums, and migration inventory;
- `manifest.json`: format version, timing, dump digest, size, and embedded inventory.

The script refuses an existing output path. A failed backup removes only the newly created artifact directory.

## Restore To A New Database

```bash
DB_DATABASE=peanut_admin \
./scripts/restore-mysql \
  --backup /secure/path/peanut-admin-backup \
  --target peanut_admin_restore_check
```

The target must differ from `DB_DATABASE` and must not already exist. The restore verifies the dump digest before creating the target, imports into the new database, then compares every table row count, table checksum, and migration record with the source inventory. A mismatch removes the failed target.

The scripts never overwrite or drop the active database.

## Automated Drills

```bash
./scripts/verify-clean-install
./scripts/verify-recovery
./scripts/test-recovery
```

The recovery fixture contains Alpha and Beta tenants. The drill corrupts a copy of the dump and proves rejection, restores a clean copy, validates schema and hashes, logs into both tenants, and proves Beta cannot resolve Alpha's typed target.

`./scripts/test-recovery` also runs the internal starter qualification. That creates two projects in empty temporary directories, compares the generated files and lock files, installs the local versioned package snapshots, builds Admin Web, starts the ThinkPHP and Vite hosts, and checks their HTTP responses. Recovery qualification therefore covers both the complete reference host and the minimal package-consuming host.

The report is written to `/tmp/peanut-admin-recovery-report.json` with measured backup and restore durations in milliseconds. Preserve the report produced by each drill. These values describe that fixture and machine only; they are not production RPO or RTO promises.

The 2026-07-17 D03 local reference run measured 542 ms to create the consistent dump and 1,095 ms to restore and compare its inventory. Its RPO observation is the snapshot at backup invocation; it does not measure production write loss between scheduled backups.

## Separate Assets And Secrets

SQL backup does not include environment secrets, signing or encryption keys, or uploaded files. Back them up through separate approved systems, with access control and restoration drills. Do not place plaintext secrets beside the SQL artifact.

## Verification Checklist

Never test recovery by overwriting the active database. Restore into a new database name, deploy the matching application release against it, then verify:

- schema and migration versions;
- tenant and platform login;
- tenant isolation and wrong-audience denial;
- Module installation and migration checksums;
- important row counts and fixture hashes;
- application and cache health behavior.

## Recovery Evidence

RPO and RTO must be measured from a real drill. Documentation must record observed values and environment details, not unsupported promises.

Schedule the workflow and retain the JSON report with the release and backup evidence. Cloud deployment adapters remain provider-specific runbooks and must preserve the same fail-closed restore target rules.
