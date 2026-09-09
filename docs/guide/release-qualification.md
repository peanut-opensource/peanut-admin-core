# Release qualification binding

Document ID: `core-doc-guide-release-qualification`

Source-tag publication is gated by `scripts/check-release-candidate` and
`.github/workflows/release.yml`. Matching package versions alone is insufficient.
The gate requires an annotated source tag and reads `docs/releases/qualifications/<version>.json` from the tag's Git
commit, never from an uncommitted replacement. The checkout and executed verifier
must match that commit. Release authority and the protected `npm-release`
environment remain separate prerequisites; a JSON claim is not an independent
attestation that its author actually ran the checks.

## Candidate and evidence commits

Freeze a clean candidate only after Development repairs are complete. Run its
registered qualification and nine-role review. After both pass, commit their
evidence and the qualification record, then tag that later evidence commit.
The record identifies the earlier candidate, solving the commit self-reference
problem without changing the qualified packages.

Every commit between candidate and tag, including merge parents, may change only:

- `docs/releases/qualifications/<version>.json`;
- `docs/releases/evidence/<version>/<name>.json` or `<name>.md`, where `name`
  contains lowercase letters, numbers, dots or hyphens and starts with a letter
  or number;
- `docs/content-status.json`, `docs/reference/document-catalog.generated.md`
  and `docs/status/index.md`.

The two package subtrees must remain exactly equal. Runtime, tests, manifests,
locks, workflow, verifier and other governance changes require a new candidate,
even if an intermediate edit was subsequently reverted. Evidence Markdown must
also meet the normal documentation registry requirements; JSON evidence avoids
introducing an extra developer page. The allowlist is fixed in the verifier and
cannot be widened by a qualification record.

## Record contract

The committed record has `schema_version: 1`, the exact unprefixed package
`version`, and `status: pass`. `candidate` and `candidate_tree` are complete
40-character lowercase Git object identities. A pending record has null evidence
and identities and is always rejected. Alpha.13's committed record now contains
passing qualification and review evidence; its earlier pending state remains
historical. A new version requires its own candidate and evidence.

`q01` must contain `status: pass`, the same `candidate`, `command: ./scripts/check`,
and `evidence_path` / `evidence_sha256`. `d05` must contain the same status,
candidate and evidence fields, plus `roles`: exactly nine objects with numeric
`id` 1 through 9, each with `status: pass`. The two evidence paths must differ,
be committed regular files beneath this version's evidence directory, and match
their SHA-256 digests. Q01 evidence records the run ID, resource lease, toolchain,
group results and report identities. D05 evidence records each role's attack
questions, findings, disposition and residual risks. An unresolved blocking
finding must not be represented as a passing role.

`projections.composer` and `projections.npm` each contain `path` and `sha256`.
Their paths are fixed to `packages/php` and `packages/web`. Digests cover the
complete committed source projection, including file paths, modes and bytes;
they are not npm tarball integrity strings or timestamp-sensitive tar hashes.
For each recursive `git ls-tree -r -z` entry in Git order, hash the UTF-8 mode,
NUL, UTF-8 relative path, NUL, decimal blob byte length, NUL, then the blob bytes.
Only regular/executable files and symlink blobs are supported; gitlinks fail.
SHA-256 of the concatenation is the projection digest. The verifier also compares
the actual Git subtree identities and package name/version manifests.

Obtain candidate identities without making any qualification claim:

```bash
node scripts/check-release-candidate --identity FULL_CANDIDATE_SHA
```

Validate the clean, checked-out source tag before publishing either projection:

```bash
node scripts/check-release-candidate v0.1.0-alpha.13
```

The source workflow repeats this gate before registry checks or publication,
uses Node 24.13.0 / pnpm 11.13.0, verifies Composer split equality and disables
npm pack/publish lifecycle scripts. The Composer split must already match the
qualified projection. This gate does not authorize publishing, updating a
downstream lock, or operating a production service.

## Retired Alpha.5 entry points

The manually dispatched `alpha5-composer-projection-preflight.yml` workflow and
`scripts/check-alpha5-package-projection` are retired. Although their labels
still said Alpha.5, their hard-coded package expectation had become alpha.6.
They cannot validate a current candidate and are removed from executable paths.
Historical contract references remain historical evidence. Their exact content
is readable from Git, for example with `git show
61201242e5ff6bbbfeae8f4ceb51bec6aeaeca52:scripts/check-alpha5-package-projection`.
Current source publication uses the generic gate above; no version-specific
replacement workflow is introduced.

The bounded verifier regression uses isolated temporary Git repositories and
real commits/tags, with no registry or database access:

```bash
node --test tests/supply-chain/release-candidate.test.mjs
```
