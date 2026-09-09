# Core Status

Document ID: `core-doc-status-index`

This index records current Core boundary facts and links only to active planning
documents. It is not a release claim, a qualification record, or an execution
contract. Package manifests, KernelSchema, OpenAPI, the runtime coverage ledger
and fixed-commit evidence remain the authoritative sources for their facts.

## Current facts

- Source publication requires the [committed qualification binding](../guide/release-qualification.md).
  Alpha.13 Q01 and the nine-role D05 review pass for fixed candidate
  `a949a77728f2940153c6cfd76b104d5d8bb183e3`; the versioned qualification
  record binds their committed evidence and both package projections. Registry
  publication and downstream adoption are separate states: Alpha.13 has since
  been published and Application v3.0.14 locks it. The
  historical Alpha.5 executable preflight is retired.

- Core remains a product-neutral package and contract repository; Peanut Admin
  Application owns product Modules, deployment and product capability status.
- The current Runtime operation inventory and executable test ownership are in
  [`runtime-operation-coverage.json`](./runtime-operation-coverage.json).
- A completed candidate, qualification, publication, remediation or handoff
  record proves only its named fixed scope. It does not authorize a later
  Runtime, package publication, release, production claim or downstream lock.
- Historical Core `0.1.0-alpha.12` was published from source commit
  `9089516a18f19e19a048683594087e0b4ffc5455` and Composer split commit
  `9017212da0da63f445d693be94d533f681c6dc92`. The annotated source/split tags,
  GitHub Release, npm package, Packagist package and clean Composer consumer
  agree. Peanut Admin Application v3.0.13 has adopted that immutable version.

The current package identity is [Alpha.13](https://github.com/peanut-opensource/peanut-admin-core/releases/tag/v0.1.0-alpha.13),
published on 2026-09-08 from source tag commit `9e63054850f5e2f5270485ba7e62ea42bb6e9afb` with the committed
qualification record. Future coordinated product numbering follows the
[version identity decision](../architecture/product-version-identity.md).

## Plans and bounded candidate history

- [Alpha.13 publication candidate](./alpha13-publication-candidate-contract.md)
  records the repair and no-repeat continuation that produced qualified source
  candidate `a949a77728f2940153c6cfd76b104d5d8bb183e3`. Its Q01 and D05 evidence are
  complete; its preparation-time pending statements are historical. The published
  source tag and Application v3.0.14 dependency locks supersede that pending state.

- [P1-ED01 Edition persistence scope](./p1-ed01-edition-persistence-scope-contract.md) is implemented
  for Idempotency, Task/Job and Import/Export in the Alpha.11 source line. The follow-up
  [P1-ED01-R01 Settings persistence scope](./p1-ed01-r01-settings-persistence-scope-contract.md)
  records the approved Settings closure. Neither candidate moves the fixed downstream lock; a Host
  may formally consume them only from a separately approved published version.

- [P1 Execution Baseline](./p1-execution-baseline.md) — execution constraints
  and prerequisites; it is not implementation proof.
- [P1 Downstream Module Readiness Plan](./p1-downstream-module-readiness-plan.md)
  and [Post-Q01 Cross-Product Capability Plan](./p1-post-q01-cross-product-capability-plan.md)
  — current planning order and stop lines.
- [P1 Execution Reality and Post-Q01 Roadmap](./p1-execution-and-post-q01-roadmap.md)
  — navigation summary for active planning; fixed evidence named there retains
  its own lifecycle.
- Candidate-only contracts, including [WF01](./p1-wf01-configurable-workflow-runtime-contract.md),
  [CAP04 Collaboration](./p1-cap04-collaboration-contract.md),
  [R02 External Operation Host Kit](./p1-r02-external-operation-host-kit-contract.md)
  and [PKG12 Application Infrastructure Extraction](./p1-pkg12-application-infrastructure-extraction-contract.md),
  authorize only their stated work and do not establish completion.

## Historical evidence

Completed remediations, candidate qualifications, publication records and
handoffs are historical evidence. They are deliberately omitted from this
status index. Find them, with their lifecycle, in the generated
[document catalog](../reference/document-catalog.generated.md).
