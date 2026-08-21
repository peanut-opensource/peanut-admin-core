# Dependency Policy

Peanut Admin reuses mature libraries when they solve an established problem and fit the frozen architecture. Reimplementation requires a written reason; convenience, preference, or avoiding a small adapter is not enough.

## Decision Before Installation

Every direct dependency, build tool, development image, and CI action requires an accepted record under `docs/decisions/dependencies/` before it is added. The record must state:

- the exact version reviewed and the allowed package constraint;
- whether it is a direct dependency;
- its purpose and license;
- credible alternatives and why they were not selected;
- the boundary or adapter that contains coupling;
- an actionable replacement or removal plan;
- the current security-review status;
- official package, documentation, source, release, or license references.

An accepted record is permission to use the named item for the recorded purpose. It is not permission to add unrelated packages from the same vendor or ecosystem.

## Versions And Locks

- Direct package versions use the constraint recorded in the dependency registry.
- Composer and pnpm lockfiles are committed and CI installs frozen locks.
- CI actions are pinned to immutable commit SHAs corresponding to the accepted release.
- Development images use an exact version tag; release qualification may additionally pin a digest.
- A lock refresh is a reviewable task. It must run vulnerability, license, build, test, and architecture checks.
- Placeholder ranges such as `latest`, `*`, `dev-main`, and unbounded major ranges are prohibited.

Transitive packages are governed by the lockfile and audit evidence. If production code starts importing a transitive package directly, that package must first receive its own accepted decision and become an explicit dependency.

## Adapter Rule

An adapter is required when a dependency touches a framework boundary, infrastructure service, external protocol, generated code, or a capability with material replacement or security cost. Small development-only tools may be invoked directly from repository configuration when no runtime coupling exists.

Adapters must preserve real semantics. They must not become generic wrappers that hide useful APIs, silently downgrade errors, or invent a second framework inside Peanut Admin.

## Security And Licensing

- `composer audit` and `pnpm audit` run against committed locks.
- Composer and pnpm license inventories are generated for release qualification.
- Gitleaks scans history and current changes.
- Pull requests introducing dependency changes use GitHub dependency review.
- A known exploitable vulnerability, unknown production license, abandoned critical package, or incompatible runtime constraint blocks adoption or release.
- Copyleft development services may be used for local development only when they are not linked into or redistributed as Peanut Admin packages. Distribution obligations must still be documented.

Audit output is evidence for a point in time, not a permanent guarantee. Security status must be refreshed whenever a lock changes and during release qualification.

## Removal And Deferral

Removing a dependency requires deleting unused adapters, configuration, generated artifacts, and lock entries in one bounded change. A deferred capability reserves neither a package nor a schema. Its implementation begins with requirements and a new decision record.

P0 explicitly defers filesystem abstraction, queue management UI, spreadsheet handling, notifications, Plugin runtime, MFA, and OIDC. Product projects may not smuggle these into the foundation through example code.
