# P1-PKG04 Alpha.2 Projection Workflow Contract

## Status

```text
state: accepted
prerequisite_commit: 1282a93
qualified_candidate_commit: b0dc376c2147b98522764486342c9525fe5678ce
publication_authorized: false
```

## Objective

Add one manually dispatched, read-only GitHub Actions preflight that regenerates
the two qualified Alpha.2 projections and fails unless their versions, public
boundaries, file lists, and SHA-256 digests match the qualification record.

This task does not publish, push, tag, create a Release, contact Packagist,
create a repository or credential, upload an artifact, or approve application
consumption. The workflow must retain repository `contents: read` permission
and must not reference a secret.

## Exact Write Set

After this contract commit, implementation may change only:

- new `scripts/check-alpha2-package-projections`;
- new `.github/workflows/alpha2-projection-preflight.yml`;
- `docs/status/index.md` for candidate tooling status.

The script must pin candidate
`b0dc376c2147b98522764486342c9525fe5678ce`, Composer digest
`314f7eb0aaff6f288859b8dfab950487bd2b8b933b41a92dd02c49a09cf84411`,
npm digest
`94b15ddcbe031b109e687b01c61002b343c8259d4b0745b05e64b391718b13ef`,
PHP package version `0.1.0-alpha.2`, npm package version `0.1.0-alpha.2`, ten
Composer Runtime PSR-4 roots, and fourteen npm exports including all three
client subpaths.

The workflow must use only already accepted, immutable action revisions,
PHP 8.3 with Composer 2.10.2, Node 24.13.0, and pnpm 11.13.0. It may invoke only
the projection script after checkout and tool setup. The script may write only
inside a caller-supplied temporary output directory and must print a compact
summary without credentials or private paths.

## Acceptance And Stop Line

Static review must prove that neither file contains a publication command,
write permission, secret reference, registry mutation, Git push, tag, or
Release action. The integration owner runs the script once locally against an
empty temporary output directory, verifies the exact write set, runs
`git diff --check`, commits once, and stops. A failure receives one read-only
diagnosis and stops; correction requires a new contract.

## R01 Commit-Rooted Composer Archive Remediation

The first local invocation stopped before extraction because the qualification
digest was calculated from `candidate:packages/php`, which resolves to a tree
object without a commit timestamp. `git archive` therefore wrote the invocation
time into the tar headers, so identical package contents produced a different
archive SHA-256 on a later run. This is an evidence reproducibility defect, not
a package-content or candidate-source change.

R01 fixes the projection root as the qualified candidate commit plus the
`packages/php` path. The exact command shape is
`git archive --format=tar <candidate> packages/php`; extraction strips the two path components. The
resulting commit-rooted archive contains the same 604 files and has stable
SHA-256
`176608c1602b0ccf8acf79a9755eb7417c25445330ccde7baddcae7df8620bdc`.

R01 may change only:

- `scripts/check-alpha2-package-projections`;
- `docs/reviews/p1-pkg03-alpha2-publication-qualification.md`;
- `docs/decisions/releases/p1-pkg03-alpha2-publication-approval.md`;
- this contract.

The npm digest, package versions, candidate commit, file counts, public
boundaries, workflow permissions, package source, manifests, locks, Runtime,
tests, and every other qualification result remain unchanged. After static
review and `git diff --check`, the integration owner runs the projection script
once against a new empty temporary directory. A failure receives one read-only
diagnosis and stops.

## R02 Credential-Content Scan Remediation

The R01 invocation reproduced the commit-rooted Composer digest and then
stopped because the preflight treated any source filename containing `secret`
or `credential` as a leaked credential. The matched files are the qualified
Settings secret-protection contracts, Integration Security crypto adapters,
and Kernel credential models and tests. No environment file, private-key file,
or credential payload was found.

R02 may change only `scripts/check-alpha2-package-projections`. The Composer and
npm projections must reject `.env` files, private-key or certificate-key files,
and content matching a private-key header, npm access token, or AWS access-key
identifier. They must not reject a source path merely because a qualified
security type contains the words `secret`, `credential`, or `private`.

R02 must not weaken digest, file-count, package-name, version, license, PSR-4,
export, dry-run/tarball parity, or output-directory checks; change package
source or evidence; or add a secret exception list. After static review and
`git diff --check`, the integration owner runs the complete projection script
once against a new empty directory. A failure receives one read-only diagnosis
and stops.
