# P1-PKG02-R38 Reference Codes Retired Checkbox Stacking Contract

## Observed Failure

R37 gave the Element Plus native checkbox a stable, non-zero interactive box.
The first real-backend browser variant then resolved the checkbox as visible,
enabled, and stable, but could not click it because the adjacent
`.el-checkbox__inner` intercepted pointer events. The read-only diagnosis
confirmed that both elements use the same effective stacking level and that
the later-painted visual inner box is therefore above the native input.

## Authorized Change

R38 retains the R37 page-local checkbox treatment and authorizes only this
subsequent source change:

- `packages/web/reference-codes/src/ReferenceCodesPage.vue`

The native `.el-checkbox__original` input may be assigned a page-local stacking
level strictly above the Element Plus visual inner box. It must remain the only
semantic checkbox, keep its stable full-control interactive box, and naturally
receive pointer, focus, keyboard, and Playwright `check()` interaction. The
Element Plus appearance, accessible name, disabled predicate,
`:model-value="state.includeRetired"`, and
`@update:model-value="setIncludeRetired"` binding must remain unchanged.

## Forbidden Changes And Verification

R38 must not change a test, selector, timeout, wait, use `force`, disable
pointer events on the whole Element Plus control, add global CSS, add another
checkbox, or change Runtime, API, query, filter, authorization, schema, or
business behavior. No file outside the authorized page may change as part of
the source repair.

After static review and `git diff --check`, run `./scripts/test-browser` once
with the complete R01/R02 environment. If it passes, the retained R32-R38
source, fixtures, tests, and contracts form the next clean package candidate.
A failure receives one read-only diagnosis and stops.
