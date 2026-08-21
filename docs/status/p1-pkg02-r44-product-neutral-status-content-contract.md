# P1-PKG02-R44 Product-Neutral Status Content Contract

## Observed Failure

The fixed root license guard passed. The following content guard stopped on
eight uses of one internal downstream product name in five historical status
documents. The same matches exist in fixed candidate `0ed843a`; none was added
by R41 through R43. They describe exclusions, stop lines, or an earlier package
documentation finding and do not define reusable Runtime behavior.

The repository-wide guard intentionally keeps the public core repository
product-neutral. Excluding status history from that guard would weaken the
contract, while deleting the history would remove qualification evidence.

## Authorized Change

R44 may change only:

- this contract;
- `docs/status/p1-b04-minimal-reference-codes-contract.md`;
- `docs/status/p1-w03-workspace-shell-contract.md`;
- `docs/status/starter-v1-c02-file-media-contract.md`;
- `docs/status/p1-pkg02-alpha-publication-contract.md`;
- `docs/status/starter-v1-c02-file-media-delivery-handoff.md`.

Each internal product-name reference must become generic downstream-product or
downstream-application wording. Existing hashes, task lineage, exclusions,
stop lines, capability meaning, and qualification status must remain intact.

R44 must not alter `scripts/check`, exclude paths from the guard, change a
Runtime/package/test/manifest/lock, remove status evidence, or weaken the rule
that reusable packages and their repository documentation are product-neutral.

After static review and `git diff --check`, rerun only the failed content guard
once. If it passes, run the previously unrun required/deferred directory guard
and final `git diff --check` once. Any failure receives one read-only diagnosis
and stops.
