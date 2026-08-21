# P1-PKG02-R49 Prior Contract Registration Contract

## Observed Failure

R48 registered R41 through R48 and the final package qualification review. The
documentation-status check then found that R37 through R40 were also absent
from `docs/content-status.json`. Git history confirms those four contracts were
added after the manifest's last update and before R41; they are tracked
qualification evidence, not new R48 documents.

## Authorized Change

After this independent contract commit, R49 authorizes only the existing R48
change to `docs/content-status.json` to additionally register:

- `docs/status/p1-pkg02-r37-reference-retired-checkbox-contract.md`;
- `docs/status/p1-pkg02-r38-reference-retired-checkbox-stacking-contract.md`;
- `docs/status/p1-pkg02-r39-recovery-module-count-contract.md`;
- `docs/status/p1-pkg02-r40-performance-pdo-injection-contract.md`;
- this R49 contract.

All five entries must be canonical and maintainer-owned. R49 must not alter or
remove an existing registration, prior contract, qualification review, package,
Runtime, test, script, version, manifest, or lock.

After JSON parsing, static review, `./scripts/check-doc-content-status`, exact
R48/R49 write-set inspection, and `git diff --check`, commit the complete
qualification evidence without rerunning any package or qualification check.
