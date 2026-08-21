# Peanut Admin Documentation

This directory is the public documentation source for Peanut Admin.

## Current Scope

The buildable documentation site contains the P0 developer guide, runtime references, core concepts, architecture, engineering standards, task status, dependency decisions, and API contract. Runtime claims are bound to executable examples or current source symbols.

Run the site locally from the repository root:

```bash
./scripts/bootstrap-worktree-dependencies
pnpm docs:dev
```

If bootstrap reports an offline cache miss, run
`./scripts/warm-worktree-dependencies` explicitly and retry bootstrap.

Build the same static output used by GitHub Pages:

```bash
./scripts/check-docs
```

`check-docs` also runs `scripts/verify-doc-examples`, including a temporary MySQL installation and the fictional Module contract tutorial.

## Content Status

Every Markdown document under `docs/` must be registered in `content-status.json`.

Allowed states:

- `canonical`: current implementation fact source;
- `draft`: incomplete and not an implementation fact source;
- `superseded`: historical content excluded from current guidance;
- `generated`: produced from code or schema and not manually edited.

Run `./scripts/check-doc-content-status` from the repository root after adding, moving, or removing documentation.

## Editing Rule

Documentation and implementation must change in the same task when public behavior changes. Generated areas are updated only through their generator.
