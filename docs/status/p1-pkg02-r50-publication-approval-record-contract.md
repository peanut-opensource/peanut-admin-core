# P1-PKG02-R50 Publication Approval Record Contract

## Qualified Input

The fixed package candidate and both immutable projections are qualified by
`docs/reviews/p1-pkg02-alpha-publication-qualification.md`. Read-only external
preflight confirms:

- `peanut-opensource/peanut-admin` is public, defaults to `dev`, and the current
  GitHub identity has repository administration;
- the current identity is an active administrator of `peanut-opensource`;
- `peanut-opensource/peanut-admin-core` does not exist and its name is free;
- `@peanut-admin/admin` and `peanut-admin/core` are not publicly registered;
- no npm or Packagist publishing credential is currently available locally.

## Authorized Change

After this independent contract commit, R50 authorizes only:

- new `docs/decisions/releases/p1-pkg02-publication-approval.md`;
- `docs/status/index.md`;
- `docs/content-status.json`.

The record must name the monorepo authority, generated Composer split
repository, exact `0.1.0-alpha.1` versions, npm `alpha` dist-tag, immutable tag
convention, provenance requirement, credential boundaries, consumer probes,
and rollback by a newer immutable version plus deprecation. It must register
itself and this R50 contract in the documentation manifest.

Unknown npm/Packagist account ownership, sessions, credentials, and workflow
secrets must remain explicitly pending. The record must not authorize external
publication while any gate is pending.

R50 must not create a repository, organization, package, token, tag, Release,
workflow, registry version, or external state; change a package, Runtime,
manifest, lock, version, test, script, or prior qualification result; or claim
that a published immutable version may later be deleted or overwritten.

After JSON parsing, static review, `./scripts/check-doc-content-status`, exact
write-set inspection, and `git diff --check`, commit the preflight record. A
later execution contract may perform only the external setup that this record
has made exact.
