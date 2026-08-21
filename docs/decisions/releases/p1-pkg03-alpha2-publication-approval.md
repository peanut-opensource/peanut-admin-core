# P1-PKG03 Alpha.2 Publication Approval

## Status

```text
state: completed
qualified_candidate_commit: b0dc376c2147b98522764486342c9525fe5678ce
source_repository: peanut-opensource/peanut-admin
source_branch: dev
composer_split_repository: peanut-opensource/peanut-admin-core
composer_split_commit: 330e76787ba754e1c7c11c2204c1c7f1e9560bb1
composer_package: peanut-admin/core@0.1.0-alpha.2
npm_package: @peanut-admin/admin@0.1.0-alpha.2
npm_dist_tag: alpha
immutable_tag: v0.1.0-alpha.2
publication_authorized: true
```

Alpha.2 publication completed from the qualified source candidate. This record
captures the immutable projections, registry and Release URLs, clean consumer
evidence, and the known post-publication operational limits. Alpha.2 remains an
alpha release; publication does not create a stable compatibility promise or a
downstream-consumption lock.

## Ownership And Gate Record

| Gate | State | Evidence or required completion |
| --- | --- | --- |
| Qualified source | verified | Candidate `b0dc376c2147b98522764486342c9525fe5678ce`; qualification records both projection digests |
| Source monorepo | verified | Public `peanut-opensource/peanut-admin`; current GitHub identity has organization administration |
| Composer split repository | completed | Generated-only `peanut-opensource/peanut-admin-core` at commit `330e76787ba754e1c7c11c2204c1c7f1e9560bb1` |
| Composer publication | completed | `peanut-admin/core@0.1.0-alpha.2` is available from [Packagist](https://packagist.org/packages/peanut-admin/core) |
| npm publication | completed | `@peanut-admin/admin@0.1.0-alpha.2` is available from [npm](https://www.npmjs.com/package/@peanut-admin/admin/v/0.1.0-alpha.2) |
| GitHub prerelease | completed | [v0.1.0-alpha.2](https://github.com/peanut-opensource/peanut-admin/releases/tag/v0.1.0-alpha.2) |
| Version and tag uniqueness | verified | Alpha.2 is the unique published immutable version; Alpha.1 remains unpublished |
| Immutable tags | verified | `v0.1.0-alpha.2` exists on both the source monorepo and generated Composer split |
| Clean Composer consumer | passed | PHP 8.3 consumer resolved 604 projected files and all 10 PSR-4 roots |
| Clean npm consumer | passed | Consumer resolved all 14 public exports |

## Immutable Projection Mapping

| Artifact | Source | Qualified SHA-256 |
| --- | --- | --- |
| Composer split | Generated-only commit `330e76787ba754e1c7c11c2204c1c7f1e9560bb1`; 604 files and 10 PSR-4 roots | `176608c1602b0ccf8acf79a9755eb7417c25445330ccde7baddcae7df8620bdc` |
| npm tarball | `@peanut-admin/admin@0.1.0-alpha.2`; 14 public exports | `94b15ddcbe031b109e687b01c61002b343c8259d4b0745b05e64b391718b13ef` |

The monorepo remains authoritative. The Composer split is generated output and
accepts no direct feature or fix commits. A release record must map the source
candidate, split commit, npm tarball, both digests, tags, registry URLs, and
consumer evidence.

## Publication Result

1. Source commit `b0dc376c2147b98522764486342c9525fe5678ce` produced the
   generated-only Composer split commit `330e76787ba754e1c7c11c2204c1c7f1e9560bb1`.
2. The Composer projection published as `peanut-admin/core@0.1.0-alpha.2` with
   the digest recorded above; one clean PHP 8.3 consumer passed with all 10
   PSR-4 roots present.
3. The npm projection published as `@peanut-admin/admin@0.1.0-alpha.2` with the
   tarball digest recorded above; one clean consumer passed with all 14 exports.
4. Immutable tag `v0.1.0-alpha.2` exists on both the source monorepo and the
   generated Composer split, and the GitHub prerelease is linked above.

## Current Operational Limits

- npm `alpha` and `latest` both point to `0.1.0-alpha.2`.
- Packagist is currently not auto-updated. The published Alpha.2 remains
  consumable from the Packagist package URL, so this does not block Alpha.2
  consumption.

## Rollback And Credential Boundary

Published registry versions and Git tags are immutable. A defect is corrected
by a newer prerelease and deprecation, never mutation, retagging, or deletion.
No npm, Packagist, or GitHub credential belongs in Git, command output, build
logs, package contents, release artifacts, or qualification evidence.

## Stop Line

`publication_authorized` is `true` and this record is complete. Published
versions and tags are immutable; any defect requires a newer prerelease and
deprecation, never mutation, retagging, or deletion. Alpha.2 remains an alpha
release and does not by itself authorize stable compatibility or a downstream
consumption lock.
