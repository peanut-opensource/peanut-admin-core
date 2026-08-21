# P1-PKG02 Alpha Publication Qualification

## Decision

Candidate `b84b8876cf24e7b749f0e79ab95053e772c922e7` passes the fixed-tree
engineering gates and exact package-content inspection for the two consolidated
alpha package boundaries:

- `peanut-admin/core@0.1.0-alpha.1`;
- `@peanut-admin/admin@0.1.0-alpha.1`.

This decision qualifies immutable package contents for the remaining external
publication gates. It does not publish either package, create a split
repository, tag, GitHub Release, registry version, downstream-consumption
approval, compatibility promise, or production-readiness claim.

## Fixed Evidence

- Package candidate: `b84b8876cf24e7b749f0e79ab95053e772c922e7`.
- Qualification planning record: `f520ee08b09180de38e67036ec9fb7a35e4ecb10`.
- Content-probe contracts: R46 `45b7090` and R47 `03c5220`.
- Toolchain: PHP 8.3.24, Composer 2.10.2, Node 24.13.0, pnpm 11.13.0.
- Current lock evidence:
  `docs/decisions/dependencies/p1-pkg02-lock-evidence.json`.

The qualification used the explicit no-repeat resume contracts recorded by
R41 through R44. Passing browser, recovery, clean-install, internal Starter,
and performance groups were retained from their owning stages instead of being
rerun.

## Fixed-Tree Results

| Gate | Result |
| --- | --- |
| Lock and manifest reproduction | Passed for all four committed locks |
| Dependency security | No high or critical Composer/pnpm advisory |
| PHP unit | 562 tests, 3,516 assertions; 253 environment-gated skips already covered by dedicated groups |
| PHP static and architecture | PHPStan 0 errors; Deptrac 0 violations and 0 uncovered |
| PHP format | 802 files passed after R42 |
| Web lint and typecheck | Passed |
| Web unit | 51 files, 209 tests passed after R43 aligned the stale R35 assertion |
| Web and documentation build | Passed |
| Compose configuration | Passed |
| Browser | 46 declared desktop/mobile real-browser tests passed in the owning stage |
| Recovery and install | Recovery, clean install, and internal Starter verification passed in R39 |
| Performance | Seven scenarios and focused contracts passed in R40 |
| Repository guards | Apache-2.0 hash, product-neutral content, required/deferred directories, and diff passed |

## Package Projections

| Projection | Content result | SHA-256 |
| --- | --- | --- |
| Composer `packages/php/` | 597 files; `composer.json`, Apache-2.0 license, and exactly 10 runtime PSR-4 roots; strict validation passed | `bd7d9ea177a926ae7563ef9ddaa1bf26b7040b33a90093e4855745d6909f935a` |
| npm `packages/web/` | 62 packed files; metadata and all 11 declared export subpaths present; dry-run and tarball lists identical | `69153040efc53f6938b8088d8da9ea68bc8e63ae4a6fc64d7cb057ded18a25a2` |

The Composer digest is the SHA-256 of the exact candidate subtree archive. The
npm digest is the SHA-256 of the local tarball packed from the exact candidate
subtree with pnpm 11.13.0. Neither operation contacted a registry. Host
applications, unrelated monorepo content, test fixtures where forbidden,
environment files, private keys, certificate bundles, credential payloads, and
generated secrets were absent.

Composer strict validation emitted only its standard recommendation to omit a
manifest `version` when Packagist derives versions from immutable tags. The
accepted alpha contract currently fixes `0.1.0-alpha.1`; publication ownership
must decide the generated split/tag metadata without changing the qualified
package content silently.

## Remaining Publication Gates

Publication remains stopped until one approval record verifies:

- npm scope ownership and a non-interactive provenance-capable credential;
- Packagist ownership and a protected generated Composer split repository;
- GitHub workflow permissions for split projection, immutable tags, prerelease,
  npm provenance, and Packagist update;
- version/tag uniqueness and rollback by a newer immutable version rather than
  mutation or deletion;
- isolated registry consumers for both published versions.

## Final Stop Line

The two alpha projections are qualified but unpublished. The development
monorepo remains the only source of truth. No external write is authorized by
this review.
