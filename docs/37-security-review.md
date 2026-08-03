# 37 — Security Review

> **Related:** [15-security-architecture](15-security-architecture.md) · [32-api-reference](32-api-reference.md) · [17-testing-strategy](17-testing-strategy.md)
> This document records the results of the project's dedicated authorization/security audits. [15-security-architecture.md](15-security-architecture.md) describes the mechanisms; this document describes the audit process and findings.

## Phase 12, Milestone 1 — Authorization Audit (Version 1)

**Method:** every route in `routes/web.php` checked against its intended Policy.

**Findings:**
- **No Policy gap found.** Every admin write action authorizes either through a Form Request's `authorize()` delegating to a Policy, or a controller-level `$this->authorize()` call for actions with no Form Request (`destroy`/`publish`/`moveUp`/`moveDown`). Every student attempt-scoped route is protected by `attempt.owner` middleware plus explicit cross-case `abort_unless` checks where relevant (`EvidenceController::recordView()`, `Student\HintController::unlock()`).
- **A real test-coverage gap was found:** several admin management test files asserted student/instructor were forbidden on write actions but never asserted a guest is redirected — relying implicitly on the `/admin` route group's `auth` middleware without proving it per controller.

**Resolution:** 12 new guest-redirect tests added across `CaseManagementTest`, `CategoryManagementTest`, `HintManagementTest`, `RubricCriterionManagementTest`, `ManualReviewTest`. No application code changed — this was a test-coverage audit, not a vulnerability fix.

## Phase 17, Milestone 3 — Discussion Routes Authorization Audit

**Method:** mirrored the Phase 12 pattern explicitly, including checking for the exact guest-coverage gap that audit found and fixed, specifically so it wasn't quietly reintroduced in a new subsystem.

**Findings:** guest-redirect was already proven for all four Discussion routes in one existing test. Cross-student-blocked was proven only for `start()` — `respond()`, `end()`, and `show()` shared the identical `attempt.owner` middleware but had no dedicated test of their own.

**Resolution:** 3 tests added, proving each action independently blocks a different student.

## Route-by-Route Authorization Summary

See [32-api-reference.md](32-api-reference.md#authorization-summary-by-route-group) for the current, complete summary table.

## Known, Documented Security-Relevant Gaps

These are stated explicitly, not silently accepted:

| Gap | Status | Reference |
|---|---|---|
| `DiscussionSessionPolicy::view()`'s admin/instructor allowance is unreachable through shipped routes | Documented, not a vulnerability (the data is simply less accessible than the Policy technically permits, not more) | [15-security-architecture.md](15-security-architecture.md) |
| Email verification not enforced | `verified` middleware present on `/dashboard` but currently a no-op | [28-maintenance-guide.md](28-maintenance-guide.md) |
| Evidence content trusted as admin-authored | No evidence-authoring UI exists yet, so this hasn't been tested against untrusted input; the design explicitly does not assume this remains sufficient forever | [15-security-architecture.md](15-security-architecture.md) |

## Application-Level Security Posture

| Control | Status |
|---|---|
| CSRF protection | Standard Laravel, applied to every state-changing form/fetch call |
| Mass-assignment guarding | `$fillable` allowlists on every model; `cases.discussion_*` deliberately withheld from `CaseModel::$fillable` until a validated, authorized write path existed (Phase 19) |
| Password hashing | Laravel default (bcrypt, `BCRYPT_ROUNDS=12`) |
| Session security | `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true` in production |
| XSS | Blade's default output escaping; one found-and-fixed `innerHTML` DOM-construction vector in evidence-tab JS (Phase 5 review) — see [15-security-architecture.md](15-security-architecture.md#frontend-defense-in-depth) |
| SQL injection | Eloquent/query builder used throughout; no raw string-interpolated SQL found in any audited controller/service |
| Rate limiting | Applied specifically to the one cost-incurring endpoint (`throttle:discussion-messages`, per user+attempt) |
| Third-party data exposure (AI) | `LeakageGuard` — deterministic, independent of prompt instructions; see [15-security-architecture.md](15-security-architecture.md) |
| Secrets management | `.env` gitignored, machine-specific; no API key or credential committed to the repository |

## AI-Specific Security Findings

Covered fully in [15-security-architecture.md](15-security-architecture.md#ai-discussion-safety) and [13-provider-abstraction.md](13-provider-abstraction.md). Summary of the two concrete, real findings from behavioral conformance validation (Phase 21):

1. **A real infrastructure misconfiguration** (Ollama `/v1` suffix) was found and fixed — not a security defect, but the finding demonstrates the value of testing against real infrastructure rather than mocks alone.
2. **A real model-behavior safety finding** (OpenRouter's tested free model leaked on an injection-resistance transcript) was found, and the model was correctly **not** shipped as a recommended default — the conformance harness functioned exactly as designed, catching an unsafe default before it could be recommended, and the runtime `LeakageGuard` also correctly caught the leak in the moment it occurred (defense in depth, both layers functioned).

## Recommendation for Future Audits

Any new route group, especially one gated by shared middleware, should be checked for the specific "prove it per controller, don't infer it from the group" gap this project has now found twice in two different subsystems (Version 1 admin routes, Version 2 discussion routes) — see [30-lessons-learned.md](30-lessons-learned.md#testing-strategy).
