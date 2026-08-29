# Core Status

Document ID: `core-doc-status-index`

This index records current Core boundary facts and links only to active planning
documents. It is not a release claim, a qualification record, or an execution
contract. Package manifests, KernelSchema, OpenAPI, the runtime coverage ledger
and fixed-commit evidence remain the authoritative sources for their facts.

## Current facts

- Core remains a product-neutral package and contract repository; Peanut Admin
  Application owns product Modules, deployment and product capability status.
- The current Runtime operation inventory and executable test ownership are in
  [`runtime-operation-coverage.json`](./runtime-operation-coverage.json).
- A completed candidate, qualification, publication, remediation or handoff
  record proves only its named fixed scope. It does not authorize a later
  Runtime, package publication, release, production claim or downstream lock.

## Active plans and candidate contracts

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
