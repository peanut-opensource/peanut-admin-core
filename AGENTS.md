# Peanut Admin Core Agent Contract
This repository owns reusable, product-neutral contracts and packages; work only within the user-authorized task and its stated write set.
## Read before acting
- Read the affected implementation and `README.md` first.
- For documentation, read `docs/README.md`, `docs/content-status.json`, and
  `docs/document-impact-map.json`; use `./scripts/core-docs-governance check`.
- For status or Runtime coverage, read `docs/status/index.md` and
  `docs/status/runtime-operation-coverage.json`.
- For release qualification or publication, read
  `docs/guide/release-qualification.md` and the exact immutable candidate record.
- Product-version policy is owned by the Application ADR at
  `repo://peanut-admin/docs/architecture/product-version-identity-adr.md` and
  projected locally by `docs/architecture/product-version-identity.md`.
- Product control owner, writer, recovery point, and failure budget exist only
  in the Application private supervision contract resolved from its repository
  root. Core keeps no mirror; independent Core work needs no private state.
- Before any runtime action, select the exact resource and environment in
  `resources/project-resources.json`. Missing or conflicting registration blocks
  the operation; never guess a host, port, credential, service, or fallback.
- Paths, hashes, diffs, JSON extraction, catalog generation, and fixed command
  orchestration are local deterministic work, without a model.
## Boundaries
- Core owns product-neutral storage mechanisms and technical state. Application
  owns provider assembly, credentials, authorization, object ledger,
  compensation, lifecycle, product Modules, deployment, and capability status;
  see `docs/architecture/storage-driver-boundary.md`.
- Application, Core PHP, Core Web, and both Editions share the Application-owned
  product version. Module and Instance versions are independent. A Core tag or
  package publication is not an Application release, deployment, or adoption.
  Defer Application/Core version and lock alignment until all development work
  is complete and release preparation begins.
- Do not add product-specific business logic, names, tables, pages, workflows,
  or examples; copy legacy code or history; or install a dependency without its
  accepted decision record.
- Runtime migration follows the accepted direction in
  `docs/architecture/index.md#accepted-thinkphp-8-runtime-direction`; it needs
  a separately authorized bounded task and no dual paths or weakened tests.
## Safety, validation, and delivery
- Preserve fail-closed tenant isolation, authorization, audit, and Module
  boundaries. Never add bypasses, silent fallbacks, test-only production paths,
  secrets, or destructive shared-history operations.
- Keep ordinary verification to static review, exact write-set review,
  `git diff --check`, and one affected existing check when behavior changes.
  Do not add tests unless requested.
- Full repository checks, browser matrices, clean install/upgrade, recovery,
  performance, and cross-platform checks belong only to their fixed-candidate
  qualification contract. A candidate needs the binding in
  `docs/guide/release-qualification.md`; historical evidence never authorizes a
  later candidate, publication, tag, release, production claim, or downstream
  adoption.
- If a contracted check fails, repair the findings once and rerun only that
  group once; a second failure blocks its dependent delivery.
- Git delivery follows Application execution rules §5.1, as reaffirmed by the
  user's 2026-09-15 decision: use local `feat/<description>` branches, merge
  completed and minimally checked work into local `dev`, then push only `dev`.
  Push a feature branch only for explicitly needed remote collaboration, backup
  or review; do not use `git push --all` or `git push --mirror`.
- Ordinary development does not authorize advancing `main`, tagging, publishing
  or deployment. At an explicit release milestone, freeze the intended commit,
  complete applicable qualification and human approval, then merge `dev` into
  `main` through a PR and tag the final release commit for authorized publication.
  Require PRs for `main` and prohibit direct pushes, force pushes and deletion.
  Preserve existing history; do not roll back `main`.
- Before a new development batch, read the latest delivery rules. Preserve
  other tasks' pinned policy, control state and immutable evidence for their
  owner's normal handoff. Clean only merged task branches/worktrees with no
  active owner; do not remove unfinished work or retained qualification inputs.
- Replace obsolete rule text in place and keep entry points and knowledge notes
  aligned with the current policy. Use Git history or separate backups for
  historical reference; do not leave superseded instructions in active rules.
- Preserve protected Git history; release, tag, or publication needs its
  accepted decision and qualification binding.
- Do not replace manifests, KernelSchema, OpenAPI, dependency decisions,
  Runtime coverage, resource registry, qualification record, or immutable tag
  as their respective source of truth. Documentation registries classify and
  route impact only.
- Stop when the assigned task is complete. If the task facts conflict with its
  write set, report the conflict instead of guessing.
