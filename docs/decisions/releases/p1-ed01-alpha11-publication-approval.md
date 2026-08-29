# P1-ED01 Alpha.11 Publication Approval

Document ID: `core-doc-decisions-releases-p1-ed01-alpha11-publication-approval`

## Status

```text
state: conditionally approved
source_branch: dev
composer_package: peanut-admin/core@0.1.0-alpha.11
npm_package: @peanut-admin/admin@0.1.0-alpha.11
npm_dist_tag: alpha
immutable_tag: v0.1.0-alpha.11
publication_authorized: true after every gate below passes on one fixed candidate
```

Alpha.11 is the corrective coordinated package release after the immutable Alpha.10 publication
attempt stopped before npm, Packagist and GitHub Release completion. It includes the product-neutral
tenant-scoped and instance-scoped persistence contracts plus the Settings closure. It remains an
alpha release and does not promise stable compatibility, cross-mode data conversion, a product
Edition, production readiness or downstream adoption by itself.

## Required Gates

1. One clean source commit and tree contain `0.1.0-alpha.11` in every package, workspace, starter,
   lock and verification identity. The source tag, split tag, npm and Packagist versions do not
   already exist.
2. The fixed candidate runs `./scripts/check` once with exclusively claimed MySQL, cache, browser,
   starter-listener and output resources. Every service, database, volume, network, listener and
   lease is absent after success.
3. The Composer projection is exactly `packages/php/` from that candidate. Its file inventory and
   SHA-256 are recorded before the generated-only split commit and annotated tag are created in
   `peanut-opensource/peanut-admin-core-php`.
4. The source annotated tag is created only after the split tag exists and matches the projection.
   The tag workflow publishes npm with provenance and creates the GitHub Release. Its npm publisher
   must be non-interactive and must preserve the package's two-factor authentication policy; no
   source or secret fallback is allowed.
5. Packagist is not auto-updated. An authenticated maintainer explicitly refreshes
   `peanut-admin/core`, then a clean Composer consumer resolves Alpha.11 from Packagist and a clean
   npm consumer resolves Alpha.11 from npm.

Any missing credential, stale or mismatched tag, failed qualification group, registry propagation
failure or consumer mismatch blocks only the affected publication step. No immutable tag or
registry version is moved, overwritten or deleted; a defect is corrected by a newer prerelease.

## Approval Boundary

The Peanut Admin post-release enhancement owner approved the fixed Core qualification and formal
publication needed by the dual-Edition application delivery path. This approval applies only to
Alpha.11 after the gates above and does not authorize unrelated Core features, stable `0.1.0`,
production data, credentials in source, weaker npm account security or automatic Peanut Admin
adoption.
