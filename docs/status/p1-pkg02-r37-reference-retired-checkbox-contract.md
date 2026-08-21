# P1-PKG02-R37 Reference Codes Retired Checkbox Interaction Contract

## Observed Failure

The preceding Reference Codes browser evidence reached `42/46` passing tests.
The four desktop/mobile Reference Codes variants failed at the same interaction:

```text
page.getByRole('checkbox', { name: 'Include retired' }).check()
```

The existing read-only trace diagnosis is that Element Plus exposes its
`el-checkbox__original` native input to the semantic locator but hides that
input from browser actionability. Playwright resolves the correct checkbox and
then repeatedly reports `element is not visible` while attempting `check()`.
This is a page-control interaction contract failure, not an API, Runtime,
filter, or business-logic failure.

## Authorized Change

R37 authorizes only this subsequent source change:

- `packages/web/reference-codes/src/ReferenceCodesPage.vue`

The page may adjust the `Include retired` control so that its one exposed
checkbox is a real semantic `input[type="checkbox"]` with a stable, non-zero
interactive box. When enabled, it must be visible, focusable, and clickable;
when disabled, it must retain the existing disabled semantics. Its accessible
name must remain `Include retired`, and this existing interaction must work
naturally without `force`, a timing change, or a test-selector change:

```text
page.getByRole('checkbox', { name: 'Include retired' }).check()
```

The control must retain the Element Plus visual treatment, the existing
`:model-value="state.includeRetired"` state source, the current disabled
predicate, and the existing `@update:model-value="setIncludeRetired"` binding.
The handler must continue to reach the existing `runtime.setFilters` path.

## Forbidden Changes

R37 must not change any test, assertion, selector, timeout, wait, or use of
Playwright `force`; add global CSS or alter global Element Plus styling; change
the API, response shape, Runtime, query/filter semantics, authorization,
schema, or business logic; or modify any file outside the authorized page.
It must not add a second exposed checkbox or otherwise make the semantic
locator ambiguous.

After the page-only change, perform static review and `git diff --check` for
the exact write set. The existing browser acceptance remains the owning
qualification stage; a failure receives one read-only diagnosis and stops.
