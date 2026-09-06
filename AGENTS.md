# Peanut Admin Agent Contract

This repository is the clean implementation home for Peanut Admin.

## Required Reading

Read the current task and relevant implementation first. Use `README.md` for orientation;
`docs/README.md` and `docs/content-status.json` for documentation changes;
`docs/status/index.md` and `docs/status/runtime-operation-coverage.json` for status or coverage decisions;
and `docs/status/p1-execution-baseline.md` for P1 Runtime work.
Read applicable `company-rules/` files when that internally synchronized directory exists.

The versioned runtime-resource authority is
`resources/project-resources.json`. Before a task connects to, starts, tests,
or qualifies against a database, cache, listener, container, external service,
or other runtime resource, it must select the exact registered resource ID and
environment. Missing or conflicting registration blocks that operation; no
port, credential, host, service, or fallback may be guessed.

`docs/content-status.json` is also the unique documentation registry: it records stable document IDs and lifecycle, while its metadata groups record audience, type, upstream facts, owner domain, scope, relations, public projection and validation. `docs/document-impact-map.json` maps changed facts/code to the minimum documentation closure. These files classify documentation and do not replace manifests, KernelSchema, OpenAPI, dependency decisions or Runtime coverage. Use `./scripts/core-docs-governance check` for the static documentation gate.

`company-rules/` is synchronized by CompanyOS for internal work and is intentionally Git-ignored in this public repository. External clones remain governed by this public `AGENTS.md` when that local directory is absent.

## Current Boundary

- Work only on an explicitly assigned P0 or P1 task.
- Keep each write task in one independently reviewable commit.
- Do not create runtime code before its task is approved.
- Do not copy code, Git history, schemas, or documents from any legacy framework repository.
- Do not add product-specific business logic, names, tables, pages, or examples.
- Do not install dependencies without an accepted dependency decision record.
- Prefer mature libraries when an accepted dependency exists; do not recreate established infrastructure without a recorded reason.

## P1 Execution Stop Line

- P1 starts from the fixed input recorded in `docs/status/p1-execution-baseline.md`.
- Historical plans are not executable task definitions. Every P1 Runtime task must name its prerequisite commit, exact file whitelist, schema owner, API contract, security behavior, test owner, and stop line.
- Existing P0 operations and models are inherited. Do not rebuild them under new names or weaken their fail-closed behavior.
- A P1 operation must be classified as `p1` in the Runtime coverage ledger and must have executable test ownership.
- A P1 dependency may be installed only after its decision record is accepted for the exact use case.
- Later P1 commits are not downstream-consumption baselines until a new fixed-commit aggregate qualification and review explicitly approve them.

## Runtime Remediation Stop Line

- The historical D04 commit `f351a21` is not a qualified P0 Runtime or a downstream-consumption baseline.
- The remediation history contains implementation evidence through R07 and the revised documentation and recovery gates; commit subjects alone do not prove qualification.
- A candidate is qualified only when a fresh D04 aggregate check and the fixed-commit D05 nine-role review are both recorded against the same resulting tree.
- Do not merge a remediation candidate, publish packages, create a tag or release, or provide a downstream-consumption baseline without the required qualification evidence and separate approval.
- The Runtime tree fixed at `d26186dfb23af34c62c58b4da94fea77bd63d724` and the D05 closure at `b010803ccd0c99179c5f7b35fb7bd89b177ea455` satisfy that evidence requirement. The 2026-07-18 approval permits promotion to `dev` and exact-commit private downstream validation only.
- External Module hosting and isolated Tenant Clients are separately qualified for exact-commit private downstream validation at `0ab02a9b735ba9f4c23509cb366b9bf04039ebf8`; see `docs/reviews/external-host-consumption-qualification.md`.
- That approval does not permit a tag, GitHub Release, package publication, production claim, or consumption of later unqualified Runtime changes.
- Do not add product-specific business models, tables, pages, names, or workflows to the Kernel, reusable packages, internal starter, or fictional examples.

## Safety Rules

- Treat tenant isolation, authorization, audit, and module boundaries as fail-closed contracts.
- Never add a super-user flag, tenant-scope bypass, silent fallback, or test-only production bypass.
- Never expose passwords, tokens, cookies, secrets, private paths, or personal data in logs or commits.
- Do not use destructive Git commands or rewrite shared history.
- Do not skip, weaken, or remove checks to make a task pass.

## Verification Policy

- Ordinary changes use static review, an exact write-set check and `git diff --check`.
  Behavior changes run one affected existing check using registered resources when required.
  Do not add tests unless requested. Shared integration and full qualification remain assigned
  to their stage or fixed-candidate owner; do not repeat unaffected passing groups.
- The integration owner completes every source acceptance and shared wiring,
  performs static review, verifies the exact write set, and fixes the final
  tree before running any automated check. Immediately before the final stage
  commit, it runs one consolidated round; each contracted group runs once.
- If a group fails, collect all findings and repair them as one static batch.
  Only that failed group may run one more time. A second failure blocks the
  stage; never loop, widen the suite, rerun a passed group, or add a post-commit
  confirmation run. A passing round is committed as the exact same tree.
- `./scripts/check`, browser matrices, clean install and upgrade, backup and
  restore, performance, and cross-platform checks belong only to the final
  fixed-candidate qualification contract.
- Historical task evidence is not an active instruction to rerun checks. A
  failure from an authorized stage or qualification check is never hidden or
  waived.
- A performance failure blocks the affected qualification, release, or
  downstream lock movement. It does not freeze unrelated ordinary feature work.

## Task Execution

1. Confirm the repository, branch, clean worktree, and prerequisite commit.
2. Read the task whitelist and stop line.
3. Modify only whitelisted files.
4. Perform static review, verify the exact write set, run `git diff --check`,
   and record the controlling stage's deferred verification identifier.
5. Inspect the staged diff and commit only the current task.
6. Merge the completed task branch directly into the latest local `dev`, push
   `dev` once, then delete the task's local/remote branch and worktree.
7. Use a pull request only for `dev` to `main`, a formal release, or when the
   user explicitly requests review; ordinary development does not require one.
8. Stop after the assigned task and branch cleanup.

If facts conflict or the file whitelist is insufficient, stop and report the conflict instead of guessing.
