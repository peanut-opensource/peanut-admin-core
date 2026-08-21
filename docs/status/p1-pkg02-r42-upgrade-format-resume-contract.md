# P1-PKG02-R42 Upgrade Format Resume Contract

## Observed Failure

The single R41 workspace invocation passed lock reproduction, Composer
validation, PHPUnit, PHPStan, and Deptrac, then stopped at PHP-CS-Fixer. The
only reported difference places the opening brace of
`SettingsUpgradeTest::tableSignatures()` on the return-type line. Blame traces
that signature to the earlier `344d18c1` qualification-fixture commit; R41 did
not change this test.

## Authorized Change

R42 may change only:

- this contract;
- `backend/tests/Upgrade/SettingsUpgradeTest.php`.

The test change must apply only the PHP-CS-Fixer brace placement reported by
the failed group. It must not change the method signature, query, fixture,
assertion, schema, Runtime source, dependency, lock, or R41 lock evidence.

`docs/status/p1-pkg02-r41-current-lock-evidence-contract.md` is the controlling
task definition created before the R41 invocation. R42 explicitly permits that
unchanged input to remain in the eventual qualification checkpoint; this does
not authorize further edits to it or expand the R41 output write set.

After static review and `git diff --check`, rerun the complete PHP-CS-Fixer
group once. If it passes, resume only the previously unrun workspace tail:
pnpm lint, typecheck, unit tests, build, and Docker Compose configuration. Then
run the fixed root license hash, private-path/product-content guard,
required/deferred directory guards, and `git diff --check` once in their
original order. Do not rerun any earlier passing group. Any failure receives
one read-only diagnosis and stops.
