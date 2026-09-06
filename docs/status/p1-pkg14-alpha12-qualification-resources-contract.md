# P1-PKG14 Alpha.12 Qualification Resources Contract

Document ID: `core-doc-status-p1-pkg14-alpha12-qualification-resources-contract`

```text
task: P1-PKG14
state: fixed qualification bundle selected
prerequisite: 32314b10fa7a01d8e60b91323c4a9de4b969f738
runtime_change: none
candidate_commit: the merge commit containing this contract
qualification: pending one immutable-candidate run
publication_authorized: true only after successful qualification and publication preflight
downstream_adoption: false
```

## Objective

Register the exact, exclusively claimable runtime resources required to qualify the coordinated
Alpha.12 package candidate. This task changes no package identity, Runtime, schema, migration,
manifest, route, dependency, workflow, Tag, Release, registry version or application lock.

The resource registration closes the preflight gap identified by P1-PKG13. It does not convert the
already green pull-request or `dev` workflows into fixed-candidate qualification evidence.

## Exact Write Set

- `resources/project-resources.json`;
- `docs/status/index.md` and this contract;
- `docs/content-status.json`;
- `docs/governance/authoritative-source-map.md`;
- `docs/guide/testing.md`;
- generated `docs/reference/document-catalog.generated.md`.

An insufficient write set stops this task. No qualification command runs in this registration
commit.

## Registered Bundle

The qualification owner selects all of these resources together:

- `peanut-admin-core-mysql84-alpha12-qualification` at `127.0.0.1:33432`;
- `peanut-admin-core-valkey91-alpha12-qualification` at `127.0.0.1:36432`;
- `peanut-admin-core-compose-alpha12-qualification`, Compose project
  `peanut-admin-core-alpha12-q01`;
- `peanut-admin-core-playwright-chromium-alpha12-qualification`;
- `peanut-admin-core-alpha12-qualification-output`;
- the six Alpha.12 listener resources at `38132`, `35232`, `38232`, `35332`, `38332` and `35432`;
- the existing registered Composer 2.10.2 and Node 24.13.0 / pnpm 11.13.0 toolchains and pnpm
  store, now explicitly available to the qualification environment.

The fixed environment is:

```bash
export COMPOSE_PROJECT_NAME=peanut-admin-core-alpha12-q01
export MYSQL_PORT=33432
export DB_HOST=127.0.0.1
export DB_PORT=33432
export CACHE_PORT=36432
export BACKEND_PORT=38132
export FRONTEND_PORT=35232
export PEANUT_BROWSER_BACKEND_PORT=38232
export PEANUT_BROWSER_FRONTEND_PORT=35332
export PEANUT_STARTER_BACKEND_PORT=38332
export PEANUT_STARTER_FRONTEND_PORT=35432
export MYSQL_DATABASE=peanut_admin_alpha12_qualification
export TMPDIR=/private/tmp/peanut-admin-core-alpha12-q01
export PEANUT_COMPOSER=/private/tmp/peanut-admin-core-tools/composer-2.10.2
```

The qualification uses the development-only fixture credential references already defined by
`compose.yaml`; it does not print, copy or add credential values. There is no alternate host,
port, database, container project, browser, toolchain, output directory or fallback.

## Preflight And Fixed Candidate

After this freeze merges, its exact merge commit and tree become the Alpha.12 qualification
candidate because the diff changes only candidate-status documentation. Before the single Gate:

1. fetch `dev` and create a clean detached qualification worktree at that exact commit;
2. prove the Alpha.12 source Tag, split Tag, GitHub Release, npm and Packagist versions remain absent;
3. prove the registered Compose project, containers, network, volumes, output paths and all eight
   service/listener ports are absent or unbound;
4. verify PHP 8.3.24, Composer 2.10.2, Node 24.13.0, pnpm 11.13.0, Playwright 1.61.1 and the fixed
   Composer/pnpm lock digests;
5. install only the exact worktree dependencies from the registered toolchain and store.

Any mismatch stops qualification. It does not select a free-looking port, another worktree cache,
CI service, old candidate resource or local package path.

## Qualification And Cleanup

The owner runs exactly once from the immutable candidate:

```bash
./scripts/check
```

A failure receives one read-only diagnosis and invalidates that candidate. A source repair requires
a new candidate; no passing group is rerun on the failed identity.

After success or failure, the owner removes only Compose project
`peanut-admin-core-alpha12-q01` with its volumes and orphans, the three registered output paths and
the detached qualification worktree. The owner then proves zero matching containers, networks,
volumes, listeners, child processes, databases and outputs remain. The persistent Composer tool and
pnpm/Playwright caches are retained under their registered lifecycle and are not qualification
residue.

Passing the Gate permits a separate immutable package-content inspection and qualification review.
It does not authorize a split commit, Tag, Release, npm, Packagist, clean registry consumer,
Application adoption or deployment.
