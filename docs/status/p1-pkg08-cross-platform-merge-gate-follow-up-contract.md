# P1-PKG08 Cross-Platform Merge Gate Follow-up Contract

## Status

```text
state: accepted
prerequisite_commit: 44f91f6
runtime_change: none
package_publication: none
```

P1-PKG07 fixed the stale Alpha.3 projections and pinned the performance PHP
runtime. Its CI result exposed three environment-specific defects: VitePress
parsed a line-broken placeholder as an HTML element, the fixed PHP container
could not reach the Compose MySQL service through host networking on the Linux
runner, and the generated pnpm license inventory varied with platform-specific
optional dependencies.

## Objective And Non-Goals

Make the existing merge gates deterministic across the supported macOS
workstation and Ubuntu CI runner. This task does not change Runtime behavior,
performance values, package versions, dependency versions, schemas, APIs,
published artifacts, or release tags, and it does not weaken or skip a gate.

## Exact Write Set

- `docs/status/p1-pkg04-alpha2-projection-workflow-contract.md`;
- `pnpm-workspace.yaml`;
- `scripts/check-third-party-licenses` only to classify the newly visible
  standard SPDX `0BSD` license used by the locked `tslib@2.8.1` package;
- `docs/reference/third-party-licenses.generated.md`, generated only through
  `./scripts/check-third-party-licenses --write`;
- `scripts/test-performance`;
- `tests/performance/PerformanceQualificationContractTest.php`;
- this contract, `docs/content-status.json`, and `docs/status/index.md`.

No other file may change.

## Deterministic Contracts

The projection document keeps its command placeholder inside one complete code
span so VitePress cannot interpret it as an HTML element.

The workspace declares every operating system, CPU, and libc variant present
in the fixed pnpm lock as a supported architecture. The license checker
therefore inventories the same optional packages on every supported host; it
does not filter platform packages from release evidence. The resulting
cross-platform inventory exposes `tslib@2.8.1` under its standard SPDX `0BSD`
license, which is explicitly reviewed with the existing permissive licenses.

When the fixed PHP image is selected, the performance script discovers the
network attached to the Compose MySQL service, joins that network, and uses the
service name and container port. The local PHP path retains host networking,
`127.0.0.1`, and the configured host port.

## Focused Acceptance And Stop Line

1. Static review proves the exact write set and unchanged performance values,
   package versions, dependency versions, and local PHP path.
2. `bash -n scripts/test-performance`, PHP lint for the changed test, YAML and
   JSON parsing, and `git diff --check` pass once.
3. The generated license inventory is refreshed once after a frozen workspace
   install with the declared supported architectures.
4. Only the three previously failed PR #1 workflows run on the resulting
   commit. A second failure of any corrected group stops this task; passed
   groups are not rerun manually.
