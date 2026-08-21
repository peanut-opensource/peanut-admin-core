# P1-PKG02 Alpha Publication Approval

## Status

```text
state: preflight-open
qualified_candidate_commit: b84b8876cf24e7b749f0e79ab95053e772c922e7
source_repository: peanut-opensource/peanut-admin
source_branch: dev
composer_split_repository: peanut-opensource/peanut-admin-core
composer_package: peanut-admin/core@0.1.0-alpha.1
npm_package: @peanut-admin/admin@0.1.0-alpha.1
npm_dist_tag: alpha
immutable_tag: v0.1.0-alpha.1
publication_authorized: false
```

The user directed that the qualified reusable core packages be published before
application migration. This record converts that direction into exact external
targets and safety boundaries. Publication remains unauthorized until every
pending gate below is verified and this record changes to `approved` in a
separate commit.

## Ownership And Gate Record

| Gate | State | Evidence or required completion |
| --- | --- | --- |
| Qualified source | verified | Candidate `b84b8876cf24e7b749f0e79ab95053e772c922e7`; qualification review records both projection digests |
| Source monorepo | verified | Public `peanut-opensource/peanut-admin`; current GitHub identity has `ADMIN` permission |
| GitHub organization | verified | Current identity is an active `peanut-opensource` administrator |
| Composer split name | verified | `peanut-opensource/peanut-admin-core` is currently absent and available |
| Composer split creation/protection | pending | Create public generated-only repository with `main`; protect immutable tags and disallow direct development |
| npm package availability | verified | Public registry returns 404 for `@peanut-admin/admin`; no version currently exists |
| npm scope ownership | pending | Verify or establish administrator ownership of `@peanut-admin` through an authenticated npm session |
| npm publisher | pending | Configure GitHub trusted publishing when available, otherwise a granular automation token stored only as an Actions secret |
| Packagist package availability | verified | Packagist returns 404 for `peanut-admin/core`; no version currently exists |
| Packagist ownership | pending | Verify authenticated owner and submit only the generated split repository |
| Packagist update credential | pending | Configure GitHub hook/token without committing or printing it |
| GitHub publication workflow | pending | Pin exact tools, generate both projections from the qualified candidate, verify recorded digests, publish npm provenance, tag both repositories, and create a prerelease |
| Version and tag uniqueness | verified | Both registries have no existing package; exact alpha version and tag are reserved by this record |
| Isolated registry consumers | pending | After publication, install each registry version in one clean isolated consumer and resolve every public namespace/subpath |

## Immutable Projection Mapping

| Artifact | Source | Qualified SHA-256 |
| --- | --- | --- |
| Composer split | Candidate subtree `packages/php/` | `bd7d9ea177a926ae7563ef9ddaa1bf26b7040b33a90093e4855745d6909f935a` |
| npm tarball | Candidate subtree `packages/web/`, packed by pnpm 11.13.0 | `69153040efc53f6938b8088d8da9ea68bc8e63ae4a6fc64d7cb057ded18a25a2` |

The development monorepo is authoritative. The Composer split is generated
output and accepts no direct feature or fix commits. A release record must map
the source candidate, split commit, npm tarball, both digests, tags, registry
URLs, workflow run, and consumer-probe evidence.

## Publication Sequence

1. Verify authenticated npm and Packagist ownership without exposing secrets.
2. Create `peanut-opensource/peanut-admin-core` as a public generated-only
   repository with default branch `main` and protected immutable tags.
3. Generate the exact `packages/php/` split from the qualified candidate and
   confirm its recorded digest before pushing it.
4. Generate the exact npm tarball and confirm its recorded digest before
   publishing `@peanut-admin/admin@0.1.0-alpha.1` as public with dist-tag
   `alpha` and provenance.
5. Tag both repositories `v0.1.0-alpha.1`, submit/update the Composer split on
   Packagist, and create a GitHub prerelease on the monorepo.
6. Run one clean Composer consumer and one clean npm consumer against the
   registry versions, then record exact resolved versions and public exports.
7. Change this record to `completed` only after every external URL, digest,
   provenance record, and consumer probe is fixed.

## Rollback And Deletion Policy

Published registry versions and Git tags are immutable. A defect is corrected
by publishing a newer prerelease and deprecating the affected npm/Packagist
version with a migration message. The workflow must never overwrite, retag, or
silently replace an artifact. Deletion is not a normal rollback mechanism and
is not promised even for test versions.

## Credential Boundary

No npm, Packagist, or GitHub token belongs in Git, command output, build logs,
package contents, release artifacts, or qualification evidence. Credentials
must be scoped to publication, stored in the provider or GitHub Actions secret
store, and rotated or revoked independently of package source.

## Stop Line

`publication_authorized` remains `false`. No external write may occur until npm
scope ownership, Packagist ownership, split repository protection, workflow
identity, and credential storage are verified and recorded.
