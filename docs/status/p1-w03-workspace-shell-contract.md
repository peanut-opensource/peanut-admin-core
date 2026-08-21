# P1-W03 Workspace Shell Contract

## Status

```text
task: P1-W03
state: implementation-ready
prerequisite: a2e9001a48012b427950fe348b8074d46159f6ce
schema_owner: none
migration: none
dependency_change: none
runtime_ledger_change: none
qualification: candidate only
```

P1-W03 provides a reusable desktop and mobile administration Shell for an
external host. It presents trusted navigation and identity state supplied by
P1-W02, delegates Tenant switching and logout to the P1-W02 Runtime, and keeps
application-owned pages as ordinary build-time Module contributions.

Implementation starts only from the exact prerequisite above and only after
this contract is committed independently. Uncommitted files from another
worktree are not an input.

## Objective And Non-Goals

The Shell must let a host configure its product name, compact brand mark, and
separate Tenant and platform audience labels without copying the reference
layout. It must provide a predictable header, desktop Sidebar, mobile Drawer,
breadcrumb, identity area, Tenant switch, logout, and explicit status views.

This task does not add:

- backend, PHP, database, migration, OpenAPI, or Runtime operation changes;
- a permission evaluator, Module availability check, trusted-context loader,
  menu resolver, token store, API client, or business-data request;
- a server-driven component loader, component path, remote JavaScript, `eval`,
  dynamic import derived from server data, or plugin marketplace;
- an application business page, consumer-specific concept, product workflow,
  generic CRUD engine, form schema, DataGrid, dashboard builder, or marketing
  page;
- a new dependency, package publication, release, production-readiness claim,
  or downstream-consumption approval.

## Host Branding And Configuration

`defineShellHostConfig()` accepts presentation-only configuration:

```text
brand.name
brand.mark
audiences.tenant.label
audiences.platform.label
commands.switchTenantLabel
commands.logoutLabel
```

Every value is a trimmed, non-empty display string with a bounded length. The
configuration cannot contain a Tenant ID, account ID, permission, Module key,
route component, API origin, token, server response, or business value.

The reference host reads optional build-time branding values from its own
environment and supplies safe defaults. The reusable package does not read
`import.meta.env` and does not own host branding.

## Audience And Identity Boundary

`AdminShell` fixes `data-audience="tenant"`; `PlatformShell` fixes
`data-audience="platform"`. A caller cannot override either wrapper's
audience through attributes or server data.

The host supplies a display-only identity with account, context, and actor
labels produced after P1-W02 trusted-context loading. The Shell renders these
labels as text and never interprets them as authorization facts.

Tenant switch is rendered only by `AdminShell`. `PlatformShell` never renders
or emits a Tenant-switch command. Logout is available in both audiences and
only emits a command for the host to execute through P1-W02.

## Navigation Contract

The Shell accepts only normalized navigation items produced by the host after
P1-W02 resolves server menu `route_name` values against its build-time route
registry:

```text
key: stable local key
label: display text
path: trusted local path or null for a group
children: normalized navigation items
```

Navigation items contain no component or executable value. The Shell renders
only local links, emits the selected path, and closes the mobile Drawer after
navigation. The host performs the router transition. Application business
pages remain ordinary build-time Module contributions and are never promoted
to Shell internals.

The Shell does not decide whether a route, Module, or permission is allowed.
P1-W02 remains authoritative for route registration, Module availability,
functional permission, audience entry, and page-chunk loading.

## Desktop And Mobile Behavior

Desktop behavior:

- the Header, Sidebar, breadcrumb, and content region have stable dimensions;
- the Sidebar can collapse and expand without resizing the content outside the
  viewport;
- collapsed controls retain accessible names and visible keyboard focus;
- the identity area truncates long labels without hiding their accessible
  text.

Mobile behavior:

- the desktop Sidebar is replaced by an Element Plus Drawer;
- the menu trigger has an accessible name and a fixed target size;
- the Drawer has a visible close control, closes on `Escape`, and closes after
  a navigation selection;
- identity and audience context remain available inside the Drawer;
- Tenant switch appears only for the Tenant audience;
- no Shell or status view creates horizontal viewport overflow at 390px.

The Shell keeps keyboard focus visible. It does not trap focus outside the
open Drawer and respects reduced-motion preferences already defined by the
reference host.

## Breadcrumb Contract

The host supplies ordered display-only breadcrumb items. An item may contain a
trusted local path; the final item is current and has no required path. The
Shell does not infer breadcrumbs from server menu paths or component names.

The reference host provides the audience home crumb and the current build-time
route title. Long titles wrap or truncate without covering the content area.

## Status Views

The package exports reusable status views for:

- forbidden access;
- not found or existence-hidden resources;
- Module unavailable;
- stale revision or conflict requiring explicit reload;
- rate limiting with the received `Retry-After` value and no automatic loop;
- service or configuration unavailability with request correlation;
- session expiry with an explicit sign-in command;
- empty content where an operation succeeded with no data.

Status views receive only display text, request ID, retry delay, and explicit
action callbacks. They do not call an API, retry automatically, expose private
configuration, or turn denial and unavailability into an empty-success state.
The reference `StatusPage` selects a view from the P1-W02 mapped error and the
existing RFC 9457 problem retained by the workspace store.

## W02 Integration

The reference `WorkspaceLayout` remains a thin host adapter. It may:

- read trusted identity and normalized menus from the existing workspace
  store;
- resolve menu route names only through the existing build-time registry;
- call `beginTenantSwitch()` and `logout()` on the installed P1-W02 Runtime;
- ask the host router to navigate to a trusted local path;
- hold presentation preferences such as Sidebar collapse and Drawer state.

It must not call a backend or business API, inspect permission keys, duplicate
route-guard decisions, accept a server component path, or execute remote code.

## Exact File Whitelist

The canonical contract commit may modify only:

```text
docs/status/p1-w03-workspace-shell-contract.md
docs/status/p1-downstream-module-readiness-plan.md
docs/status/index.md
docs/content-status.json
```

The implementation commit may add or modify only:

```text
packages/web/admin-shell/src/config.ts
packages/web/admin-shell/src/layout.ts
packages/web/admin-shell/src/states.ts
packages/web/admin-shell/src/theme.ts
packages/web/admin-shell/src/index.ts
packages/web/admin-shell/tests/components.spec.ts
packages/web/admin-shell/tests/package.spec.ts
packages/web/admin-shell/tests/responsive.spec.ts
frontend/src/shell/host-config.ts
frontend/src/shell/WorkspaceLayout.vue
frontend/src/pages/status/StatusPage.vue
frontend/src/style.css
frontend/tests/w03-shell.spec.ts
frontend/tests/e2e/shell-runtime.e2e.ts
```

`frontend/src/style.css` and `frontend/src/pages/status/StatusPage.vue` are the
only shared reference-host implementation files. Their changes must remain
presentation-only. No generated file is edited by this task.

Explicitly forbidden:

```text
backend/**
packages/php/**
packages/web/admin-core/**
frontend/src/app/**
frontend/src/modules/**
frontend/src/pages/auth/**
frontend/src/pages/common/**
frontend/src/pages/platform/**
docs/api/**
docs/status/runtime-operation-coverage.json
package.json
pnpm-lock.yaml
compose.yaml
playwright.config.ts
```

Company-os Patch files, P1-R01/P1-R02 internals, P1-B03/P1-B04 worktrees,
downstream application files, and every other repository are outside this task.

## Test Contract

Tests are written before implementation and must prove:

- invalid or empty branding is rejected without reading Runtime state;
- Tenant and platform wrappers expose immutable, separate audience markers;
- platform mode cannot render or emit Tenant switch;
- identity, breadcrumb, navigation, collapse, expand, logout, and Tenant-switch
  commands render with accessible names;
- navigation contains no component execution path and emits only a trusted
  local path supplied by the host;
- desktop Sidebar collapse does not change the outer viewport width;
- mobile Drawer opens, has a close control, closes on `Escape`, and closes
  after navigation;
- forbidden, not-found, Module-unavailable, conflict, rate-limit,
  service-unavailable, session-expired, and empty states remain distinct;
- rate-limit state displays `Retry-After` and never starts an automatic retry;
- the reference host delegates Tenant switch and logout to P1-W02 and does not
  issue a business API request from the Shell;
- Tenant and platform navigation, identity, and commands do not cross;
- desktop and mobile browser runs have no console error, no skipped test, and
  no horizontal viewport overflow.

Focused checks run first. Required final commands are:

```text
pnpm test:unit
pnpm typecheck
pnpm lint
pnpm --filter @peanut-admin/reference-admin build
PEANUT_BROWSER_FRONTEND_PORT=35183 ./scripts/test-browser
git diff --check
```

Browser evidence uses the existing local test backend and a W03-specific
frontend port. It must not reuse or stop the running Demo environment.

## Stop Line

The implementation is one independently reviewable commit after this canonical
contract commit. Completion makes W03 only an unqualified P1 candidate. It does
not move `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`, authorize a downstream
consumer, publish a package, create a tag or release, claim production
readiness, start P1-Q01, or add downstream product business logic.
