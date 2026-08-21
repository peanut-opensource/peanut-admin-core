# P1-PKG03 Alpha.2 Publication Qualification

## Decision

Candidate `b0dc376c2147b98522764486342c9525fe5678ce` passes the retained
fixed-tree gates, the Q04 performance retry on the fixed PHP toolchain, the
repository tail guards, and package-content inspection for:

- `peanut-admin/core@0.1.0-alpha.2`;
- `@peanut-admin/admin@0.1.0-alpha.2`.

This decision qualifies immutable Alpha.2 package contents for external
publication preflight. It does not publish a package, create a split
repository, tag, Release, registry version, or approve application migration.

## Fixed Evidence

- Package candidate: `b0dc376c2147b98522764486342c9525fe5678ce`.
- Q03 continuation: `320988f708a14010bf581b8dc78de803c37d44c3`.
- Q04 toolchain retry: `6b0453a`.
- Q05 projection toolchain retry: `ca19ded`.
- Toolchain: PHP 8.3.24, Composer 2.10.2, Node 24.13.0, pnpm 11.13.0.

Q02 results through third-party license inventory and the dedicated secret
scan were retained. Q03 retained the passing supply-chain, unit, integration,
security, browser, recovery, internal Starter, and workspace groups. Those
groups were not rerun.

Q04 passed all seven performance scenarios and the focused performance
contract (`3` tests, `41` assertions). The Apache-2.0 hash, forbidden-content,
required/deferred-directory, and diff tail guards also passed.

## Package Projections

| Projection | Content result | SHA-256 |
| --- | --- | --- |
| Composer `packages/php/` | 604 files; valid manifest; Apache-2.0 license; exactly 10 Runtime PSR-4 roots and override contracts | `176608c1602b0ccf8acf79a9755eb7417c25445330ccde7baddcae7df8620bdc` |
| npm `packages/web/` | 67 packed files; all 14 exports, including `./client`, `./client/nuxt`, and `./client/uniapp`; dry-run and tarball lists identical | `94b15ddcbe031b109e687b01c61002b343c8259d4b0745b05e64b391718b13ef` |

The Composer archive is rooted at the qualified commit and `packages/php`
path, so its tar headers inherit the fixed commit timestamp. Both projections
exclude Host applications, environment files, credentials,
secrets, and unrelated monorepo content. Composer strict validation reported
only its standard recommendation to omit the manifest `version`; the accepted
Alpha.2 contract intentionally fixes that version, so the warning is retained
as publication evidence rather than silently changing qualified content.

## Remaining Publication Gates

Publication remains unauthorized until npm scope ownership, Packagist
ownership, the protected generated Composer split repository, non-interactive
workflow identities and credentials, immutable version uniqueness,
provenance, and isolated registry consumers are verified and recorded.

## Stop Line

Alpha.2 is qualified but unpublished. The development monorepo remains the
only source of truth, and Peanut Admin applications must not consume this
candidate as a released dependency yet.
