# P1-PKG05 Admin Web Alpha.3 Classic Type Resolution Contract

## Status

```text
state: npm published and registry consumer verified
prerequisite_commit: 35bc40b0ff45b57b0ce7b50c9313c47c69f8102e
package: @peanut-admin/admin@0.1.0-alpha.3
dependency_change: none
runtime_change: none
publication_authorized: true after the focused gates below pass
```

The repository owner authorized this repair after the published Alpha.2 package
installed successfully but a real Peanut Admin consumer using TypeScript's
standard `node` resolver could not resolve `@peanut-admin/admin/core`. Alpha.2
contains every declared source target, but its manifest exposes type targets
only through conditional `exports`; it has no `typesVersions` map for classic
TypeScript resolution.

## Objective And Non-Goals

Publish one immutable npm correction that makes all fourteen existing public
subpaths resolvable by classic TypeScript consumers. The correction adds
standard package metadata; it does not add an alias package, application path
mapping, duplicate export, compatibility runtime, route, UI, API, schema,
authorization rule, state transition, or dependency.

The first focused consumer reached the package source after the metadata fix
and then stopped because `admin-core` used `String.replaceAll`, while the
consumer's declared compilation library is ES2020. The implementation therefore
also performs one behavior-preserving normalization change from
`replaceAll('-', '')` to `replace(/-/g, '')` in the request-id generator. This
does not change generated values or the public API.

Composer `peanut-admin/core@0.1.0-alpha.2` is unaffected and remains the current
PHP package. The two ecosystems may carry different alpha patch numbers until
the next coordinated candidate; this avoids an unnecessary Composer release.

## Exact Implementation Write Set

After this contract is committed independently, implementation may change only:

- `packages/web/package.json`, to set `0.1.0-alpha.3` and add one
  `typesVersions` map covering exactly the existing fourteen exports;
- `package.json` and `starter/frontend/package.json`, to require the matching
  workspace version;
- `pnpm-lock.yaml` and `starter/pnpm-lock.yaml`, only for those version changes;
- `packages/web/admin-core/src/api/client.ts`, only to replace the single
  request-id `replaceAll('-', '')` call with `replace(/-/g, '')`;
- `docs/status/index.md`, only to record the candidate result.

No other TypeScript, Vue, test, Host, PHP, Composer, OpenAPI, generated, schema,
migration, or application file may change. If this whitelist is insufficient,
implementation stops and this contract is amended separately.

## Focused Gates

The integration owner performs one consolidated round on the implementation
tree:

1. package the npm projection and verify the name, version, fourteen exports,
   fourteen `typesVersions` entries, and matching target files;
2. install the tarball in one clean TypeScript consumer using
   `moduleResolution: node`, import `@peanut-admin/admin/core`, and run one
   no-emit typecheck;
3. run the package's existing Admin Core unit group once;
4. run `git diff --check` and verify the exact write set.

If a group fails, it receives one read-only diagnosis and one static repair;
only that failed group may run once more. A second failure blocks publication.

## Publication And Rollback

After all focused gates pass, publication may create only:

- npm version `@peanut-admin/admin@0.1.0-alpha.3` with dist-tag `alpha`;
- immutable monorepo tag `v0.1.0-alpha.3` and matching GitHub prerelease;
- the publication result in the existing release decision/status documents.

Alpha.2 must not be overwritten, retagged, unpublished, or deleted. Rollback is
by deprecating Alpha.3 and publishing a newer immutable version. npm `latest`
is not changed by this task.

## Publication Result

The focused gates passed on source commit `4b197fc`, and immutable tag
`v0.1.0-alpha.3` was pushed before publication. npm publication completed on
2026-08-08:

- package: [`@peanut-admin/admin@0.1.0-alpha.3`](https://www.npmjs.com/package/@peanut-admin/admin/v/0.1.0-alpha.3);
- registry tarball SHA-1: `c80aeccb32aa542f55f01e9d58a61dba8a4b67f5`, matching the qualified candidate;
- dist-tags: `alpha` points to Alpha.3 and `latest` remains on Alpha.2;
- registry consumer: a clean TypeScript 4.9.5, ES2020,
  `moduleResolution: node` consumer imported `@peanut-admin/admin/core` and
  passed the same no-emit gate with dependency declaration checking skipped.

The matching
[`v0.1.0-alpha.3` GitHub prerelease](https://github.com/peanut-opensource/peanut-admin/releases/tag/v0.1.0-alpha.3)
was created after GitHub CLI authentication was restored. Repository rulesets
now require pull requests for `main`, reject deletion and non-fast-forward
updates of `main`, and reject updates, deletion, and non-fast-forward changes
for `v*` tags. The Alpha.3 publication task is operationally complete.

## Stop Line

This task does not publish Composer, change Packagist, modify the generated PHP
split, change repository permissions, migrate application behavior, or claim a
stable release. Peanut Admin application consumption resumes only after the
registry tarball passes the same classic-resolution consumer gate.
