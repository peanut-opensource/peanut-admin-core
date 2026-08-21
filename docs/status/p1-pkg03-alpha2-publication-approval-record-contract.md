# P1-PKG03 Alpha.2 Publication Approval Record Contract

## Qualified Input

The fixed Alpha.2 candidate and both immutable projections are qualified by
`docs/reviews/p1-pkg03-alpha2-publication-qualification.md`. Current read-only
external preflight confirms:

- `peanut-opensource/peanut-admin` is public and the current GitHub identity is
  an active organization administrator;
- `peanut-opensource/peanut-admin-core` remains the intended generated-only
  Composer split repository;
- neither Alpha.1 nor Alpha.2 has been published under the two reserved package
  names;
- the local npm CLI is not authenticated, and no verified npm or Packagist
  non-interactive publishing identity is available to this task.

## Authorized Change

After this independent contract commit, the approval-record task may change
only:

- new `docs/decisions/releases/p1-pkg03-alpha2-publication-approval.md`;
- `docs/status/index.md`;
- `docs/content-status.json`.

The record must name the qualified candidate, both exact projection digests,
the monorepo authority, generated Composer split repository, exact Alpha.2
versions, npm `alpha` dist-tag, immutable tag convention, provenance
requirement, credential boundaries, isolated registry consumers, and rollback
by a newer immutable version plus deprecation.

Unknown npm/Packagist ownership, sessions, credentials, repository protection,
workflow identity, and post-publication consumer evidence must remain pending.
The record must keep `publication_authorized: false` while any gate is pending.

This task must not create a repository, organization, package, token, secret,
workflow, tag, Release, registry version, or external state; change a package,
Runtime, manifest, lock, version, test, script, prior qualification, or the
historical Alpha.1 approval record; or claim that an immutable version may be
overwritten or deleted as rollback.

After JSON parsing, static review, `./scripts/check-doc-content-status`, exact
write-set inspection, and `git diff --check`, the approval record is committed
independently. A later execution contract may perform external setup only
after the record makes every target and stop line explicit.
