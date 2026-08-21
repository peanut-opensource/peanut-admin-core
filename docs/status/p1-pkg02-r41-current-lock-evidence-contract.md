# P1-PKG02-R41 Current Lock Evidence Contract

## Observed Failure

The performance-qualified candidate reached workspace checks and stopped
before validation or build because `scripts/check-workspace` still compared the
current root lock with the historical P1-PKG01 package-boundary evidence. That
evidence correctly records a former first-party-only lock state. R15 later
changed three root transitive development resolutions to remediate published
advisories, so rewriting the P1-PKG01 evidence would falsify its task lineage.

## Authorized Change

R41 may change only:

- new `docs/decisions/dependencies/p1-pkg02-lock-evidence.json`;
- `docs/decisions/dependencies/index.md`;
- `scripts/check-workspace`.

The new evidence must record the exact current SHA-256 for all four locks,
identify the root pnpm lock as the R15 transitive advisory remediation, and
identify the other three locks as unchanged from P1-PKG01. Workspace checks
must read this new evidence. The dependency index must distinguish the
historical P1-PKG01 record from the current P1-PKG02 record.

R41 must not change a manifest, lock, dependency, override, audit threshold,
package boundary, Runtime source, test, or the historical P1-PKG01 evidence.

After JSON parsing, static review, and `git diff --check`, run
`./scripts/check-workspace` once with the complete qualification environment.
If it passes, run the remaining fixed license, content, directory, and diff
guards once in their original order. A failure receives one read-only diagnosis
and stops.
