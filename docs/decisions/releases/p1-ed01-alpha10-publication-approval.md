# P1-ED01 Alpha.10 Publication Approval

Document ID: `core-doc-decisions-releases-p1-ed01-alpha10-publication-approval`

## Status

```text
state: superseded after incomplete publication attempt
source_branch: dev
composer_package: peanut-admin/core@0.1.0-alpha.10
npm_package: @peanut-admin/admin@0.1.0-alpha.10
npm_dist_tag: alpha
immutable_tag: v0.1.0-alpha.10
publication_authorized: false; immutable tag retained as historical evidence
superseded_by: v0.1.0-alpha.11
```

Alpha.10 is the first coordinated package release authorized to include the
P1-ED01 persistence-scope contract. It remains an alpha release and does not
promise stable compatibility, cross-mode data conversion, a product Edition,
production readiness or downstream adoption by itself.

## Required Gates

1. One clean source commit and tree contain `0.1.0-alpha.10` in every package,
   workspace, starter and verification identity. The source, split, npm and
   registry versions do not already exist.
2. The fixed candidate runs `./scripts/check` once with exclusively claimed
   MySQL, cache, browser, starter-listener and output resources. Every service,
   database, volume, network, listener and lease is absent after success.
3. The Composer projection is exactly `packages/php/` from that candidate. Its
   file inventory and SHA-256 are recorded before the generated-only split
   commit and annotated tag are created in
   `peanut-opensource/peanut-admin-core-php`.
4. The source annotated tag is created only after the split tag exists and
   matches the projection. The tag workflow verifies that split, publishes npm
   with provenance and creates the GitHub Release; it does not claim to update
   Packagist.
5. Packagist is not auto-updated. An authenticated maintainer explicitly
   refreshes `peanut-admin/core`, then a clean Composer consumer resolves
   Alpha.10 from Packagist and a clean npm consumer resolves Alpha.10 from npm.

Any missing credential, stale or mismatched tag, failed qualification group,
registry propagation failure or consumer mismatch blocks only the affected
publication step. No immutable tag or registry version is moved, overwritten
or deleted; a defect is corrected by a newer prerelease.

## Approval Boundary

The Peanut Admin post-release enhancement owner approved the fixed Core
qualification and formal publication needed by the dual-Edition application
delivery path. This approval applies only to Alpha.10 after the gates above and
does not authorize unrelated Core features, stable `0.1.0`, production data,
credentials in source, or automatic Peanut Admin adoption.

## Publication Outcome

The source tag was created at `fe633c1765e41589d70348af44b5e0e27e332ab1` after its fixed-tree
qualification. The generated Composer split tag also exists. The source-tag workflow then stopped
at npm's interactive one-time-password requirement before publishing npm or creating the GitHub
Release, and Packagist was not refreshed. The immutable tag and split tag remain unchanged.

The later Settings persistence-scope closure is not in that tag. The corrective release therefore
uses Alpha.11; no Alpha.10 tag, package, split or registry identity is overwritten or deleted.
