# P1-OVR05-R01 Web Override Type Narrowing Remediation Contract

## Status

```text
state: resolved
qualification_contract: P1-OVR05
failed_group: Q3 Web Public Types
source_candidate_commit: de35258601fabf4da6e737961762c3ba7264b780
runtime_operations: none
```

## Finding

The first P1-OVR05 Q3 run failed before checking the new Shell Host types. In
`admin-core/src/runtime/overrides.ts`, TypeScript does not retain the
`Map.get()` non-undefined narrowing after the separate `fail()` statement and
reports `slot` as possibly undefined at each later access.

Q1 PHP behavior and Q2 Web behavior passed on the same integration tree and
must not run again. The failing Q3 group may run one more time after this
remediation.

## Authorized Repair

Replace only the two-step lookup:

```ts
const slot = slots.get(key)
if (slot === undefined) fail('ADMIN_OVERRIDE_KEY_UNKNOWN', key)
```

with one nullish fail-closed expression:

```ts
const slot = slots.get(key) ?? fail('ADMIN_OVERRIDE_KEY_UNKNOWN', key)
```

The repair must preserve the unknown-key error code, lookup timing, registry
immutability, override validation, and all runtime behavior. No cast,
non-null assertion, fallback slot, test weakening, or compiler-option change is
allowed.

## File Whitelist

The remediation may change only:

- `packages/web/admin-core/src/runtime/overrides.ts` for the exact lookup above;
- `docs/status/index.md` for remediation status only;
- this contract only for final state.

The P1-OVR05 qualification owner separately retains its approved test-isolation
and result-record write set.

## Verification

After static review and `git diff --check`, rerun only failed group Q3 once:

```bash
pnpm --filter @peanut-admin/admin \
  --filter @peanut-admin/reference-admin typecheck
```

A second Q3 failure blocks P1-OVR05. Q1 and Q2 are already passed and must not
be repeated.
