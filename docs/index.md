# Peanut Admin Developer Documentation

Peanut Admin is a reusable multi-tenant administration foundation. It combines reusable PHP and web packages, a ThinkPHP reference host, a Vue Admin Shell, module contracts, examples, and project checks in one public repository.

P0 is an internal alpha foundation that downstream teams can extend safely. It is not yet a finished commercial admin framework and does not contain product-specific domain logic.

## Start Here

- [Developer Guide](./guide/): install, extend, test, upgrade, and troubleshoot the current runtime.
- [Core Concepts](./core-concepts/): understand accounts, tenants, members, platform operators, departments, and typed business targets.
- [Architecture](./architecture/): understand package boundaries, module ownership, isolation, and composition.
- [Engineering Standards](./standards/): follow dependency, security, documentation, and implementation rules.
- [API Contract](./api/): track the OpenAPI 3.1 contract as it is implemented.
- [P0 Status](./status/): see what is implemented and what remains intentionally unavailable.

## Stable Principles

1. A tenant is the SaaS customer and data-isolation root. A store, warehouse, supplier, or project is a business target inside a tenant, not a tenant alias.
2. Login identity and tenant membership are separate: `Credential -> Account -> Tenant -> TenantMember`.
3. Platform operators use separate sessions, guards, APIs, and roles. Platform authority never implies tenant business access.
4. Functional permission answers whether an operation may be attempted. Data permission answers which records or typed targets it may affect.
5. Missing tenant context, module state, permission, provider, or operation declaration fails closed.
6. A module owns its schema, rules, repositories, migrations, APIs, permissions, and public contracts. Other modules do not read or write its tables directly.
7. Shared master data keeps one truth source and one identifier space. Ownership and scope decide who may view, use, or maintain each record.

## Current Runtime Status

Reusable PHP and web packages, Kernel and data-permission schema, tenant and platform authentication, authorization contracts, fictional example Modules, all 75 current P0 OpenAPI handlers, the reference Admin Shell, real full-stack browser tests, local installation/upgrade workflows, and the fixed internal starter are implemented.

The internal starter proves package consumption from a clean directory but is not a public generator or long-term template upgrade promise. The P0 Runtime and the subsequent external-host consumption changes have passed their fixed-commit internal qualification gates. Neither qualification is a public release or production-readiness claim.
