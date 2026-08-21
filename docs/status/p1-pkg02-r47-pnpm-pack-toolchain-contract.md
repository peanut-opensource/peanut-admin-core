# P1-PKG02-R47 pnpm Pack Toolchain Contract

## Observed Failure

R46 completed the exact Composer projection inspection, then the npm dry-run
stopped with `Unknown option: 'dry-run'`. Read-only toolchain evidence shows
that the same Corepack launcher selects pnpm 11.13.0 from the repository root
but pnpm 9.15.6 when the current directory is the temporary projection. The
9.15.6 selection occurred before `--dir` was applied. pnpm 11.13.0 help lists
both required `--dry-run` and `--json` options.

This is a current-directory package-manager selection failure, not an npm
package-content failure. Candidate
`b84b8876cf24e7b749f0e79ab95053e772c922e7` and its Web projection remain
unchanged.

## Authorized Retry

R47 authorizes one npm package-content inspection from the repository root,
using pnpm 11.13.0 and an absolute `--dir` path to the exact temporary Web
projection. The command must retain `pack --dry-run --json`; it must not switch
to npm, another pnpm version, or the working-tree package source.

The inspection must verify package name, version, license, every declared
export target, required metadata/source files, and absence of Host,
application, test-fixture, environment, private-key, and unrelated monorepo
content. It may then create one local tarball from the same projection solely
to calculate its SHA-256; no registry, tag, Release, or remote state may change.

R47 changes only this contract. Composer inspection is already authoritative
and must not be repeated. A required npm inspection failure receives one
read-only diagnosis and stops; a pass permits the qualification evidence write
set already defined by P1-PKG02.
