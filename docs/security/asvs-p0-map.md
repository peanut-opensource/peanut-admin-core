# OWASP ASVS 5.0 P0 Map

Peanut Admin uses selected OWASP ASVS 5.0.0 controls as engineering references for the P0 foundation. This is not an ASVS certification or a claim that every Level 2 control is implemented. Products built on the foundation must complete their own threat model, deployment controls, MFA, recovery, file handling, and full ASVS assessment.

The control wording and numbering come from the official OWASP ASVS 5.0.0 CSV. G-07 is the repository's detailed security and isolation matrix; `tests/security/g07-evidence.json` binds every G-07 ID to executable evidence.

## Adopted Controls

| ASVS 5.0 control | P0 application | Executable evidence |
| --- | --- | --- |
| V1.2.4 | SQL is structured and parameterized; data permission is compiled into the query. | `QueryConstraintTest`, `AuthorizationPathParityTest` |
| V2.2.1, V2.2.2 | Typed targets, filters, operation cardinality, and state transitions are validated in trusted PHP services. | `ApiContractTest`, `TargetCardinalityValidatorTest`, lifecycle tests |
| V2.3.3 | Bootstrap, refresh, idempotency, authorization writes, and recovery preserve transaction boundaries. | integration, concurrency, and recovery gates |
| V2.4.1, V6.3.1 | Login attempts use identifier/IP windows and temporary credential lockout. | `TenantAuthServiceIntegrationTest` |
| V3.3.1-V3.3.4 | Refresh cookies are host-scoped, Secure, HttpOnly, and SameSite; platform and tenant names are distinct. | auth integration tests and `HttpBoundaryQualificationTest` |
| V3.4.2-V3.4.6 | Cross-origin access is disabled by default; the reference boundary defines restrictive browser headers. | `HttpBoundaryQualificationTest` |
| V3.5.1-V3.5.3 | Cookie-backed state-changing endpoints rely on restrictive same-origin/SameSite behavior and unsafe HTTP methods. | auth cookie tests and OpenAPI contract tests |
| V4.1.1 | API failures use RFC 9457 problem JSON with an explicit content type and request ID. | `ProblemDetailsMiddlewareTest` |
| V7.2.1-V7.2.4 | Opaque, high-entropy tokens are verified by the backend and rotated on authentication. | tenant/platform auth integration tests |
| V7.4.1, V7.4.2 | Logout, token reuse, account, tenant, member, and operator state changes invalidate sessions. | tenant/platform auth integration tests |
| V8.1.1, V8.2.1, V8.2.2 | Functional and data-specific authorization are separately documented and enforced. | RBAC and data-permission suites |
| V8.3.1 | Browser menus and buttons are hints; trusted services enforce operations. | browser route tests and `PermissionGuard` tests |
| V8.4.1 | Tenant, member, typed target, query, cache, lock, idempotency, and audit boundaries fail closed. | TEN/PERM/SYS G-07 evidence |
| V13.3.1 | Runtime secrets stay in environment or an external secret manager and are scanned from Git history and current files. | `scripts/check-secrets` |
| V16.2.1, V16.2.5 | Audit rows carry actor, target, request, result, and typed-boundary metadata without raw target lists or tokens. | audit schema and multi-target audit tests |
| V16.3.1, V16.3.2 | Authentication and authorization denials are recorded on their correct audit plane. | auth and authorization security suites |
| V16.5.1, V16.5.3 | Security failures return generic problem details and authorization fails closed. | problem-details and negative authorization tests |

## Explicit P0 Non-Claims

- MFA, password reset/recovery, user session-management screens, adaptive risk, file upload, SSO, and production secret-vault integration are not part of P0.
- HSTS and TLS termination are deployment responsibilities. A production product must prove them at its ingress and cannot infer them from this source tree.
- The reference HTTP boundary is a minimum secure default. A product that enables cross-origin clients must add an explicit allowlist and tests; wildcard credentialed CORS remains prohibited.
- ASVS Level 2 qualification belongs to a deployable product, not to this reusable foundation alone.

## Gate

Run:

```bash
./scripts/test-security
```

The command executes the G-07 evidence contract, authentication, authorization, tenant isolation, data-permission, module, idempotency, audit, and HTTP-boundary suites against MySQL. Any skipped security test fails the command.
