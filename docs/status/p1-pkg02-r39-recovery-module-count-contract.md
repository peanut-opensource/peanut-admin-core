# P1-PKG02-R39 Recovery Module Count Contract

## Observed Failure

The fixed `ad949488c58e939eb60c3939dde77d7f57620149` candidate passed its
46-test browser gate and resumed qualification at recovery. Recovery script
contracts and clean install passed. The restored-database acceptance then
expected three active Module installations but the restored current product
profile contained ten.

The authoritative install integration contract enumerates the same ten Modules
from `profiles/reference-admin.json` and creates ten Tenant Module records for
one Tenant. The recovery fixture installs that profile for Alpha and then
applies it to Beta, so the restored database must contain exactly ten active
Module installations and twenty enabled Tenant Module records.

## Authorized Change

R39 may change only:

- `tests/recovery/RecoveryAcceptanceTest.php`.

The restored fixture assertions must require exactly ten active Module
installations and exactly twenty enabled Tenant Module records. Tenant count,
project count, login, Tenant selection, and typed-target isolation assertions
must remain unchanged.

R39 must not derive expectations from restored rows, weaken an exact assertion,
change the product profile, installer, backup, restore, fixture seeding,
Runtime, schema, Module state, or production behavior.

After static review and `git diff --check`, run `./scripts/test-recovery` once
with the complete qualification environment. If it passes, qualification
continues once through performance, internal Starter verification, workspace
checks, and the remaining `scripts/check` guards in their original order. A
failure receives one read-only diagnosis and stops.
