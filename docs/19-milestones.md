# 19 — Milestones

> **Related:** [18-development-phases](18-development-phases.md) · `CHANGELOG.md`
> This document is the milestone-level index. Phase-level objectives, architectural reasoning, and outcomes are in [18-development-phases.md](18-development-phases.md); this file lists every milestone's purpose, key files, and testing/notes in compact form.

## Phase 1 — Laravel Project Setup

| Milestone | Purpose | Key files | Notes |
|---|---|---|---|
| Single milestone | Laravel 11 + Breeze + Bootstrap 5 scaffolding, student/admin shells, DB switched to MySQL | `layouts/app.blade.php`, `layouts/admin.blade.php`, `resources/sass/app.scss`, `.env` | Composer advisory-block override needed (see [18](18-development-phases.md)) |

## Phase 2 — Authentication & Roles

| Milestone | Purpose | Key files | Tests |
|---|---|---|---|
| Single milestone | Role model, `UserRole` enum, `EnsureUserHasRole`, registration service | `app/Enums/UserRole.php`, `app/Http/Middleware/EnsureUserHasRole.php`, `app/Services/UserRegistrationService.php` | `RoleGatingTest` |

## Phase 3 — Database Schema & Models

| Milestone | Purpose | Key files | Tests |
|---|---|---|---|
| Single milestone | 15 tables, 5 enums, models, 6 repositories | `database/migrations/*`, `app/Models/*`, `app/Repositories/*` | `DomainGraphWiringTest` |

## Phase 4 — Admin CMS

| Milestone | Purpose | Key files | Notes |
|---|---|---|---|
| Single milestone | Case/Category/Hint/Rubric CRUD, publish workflow, Admin Dashboard | `app/Http/Controllers/Admin/*`, `app/Services/CaseCatalogService.php`, `app/Services/ActivityLogService.php` | Evidence-count publish invariant deliberately stubbed (`TODO(Phase 7)`) |

## Phase 5 — Student Engineering Office (7 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Shell | Global nav, guest sample-incident link | `layouts/navigation.blade.php` | `EngineeringOfficeShellTest` |
| 2 | Inbox | Student dashboard | `resources/views/dashboard.blade.php` | — |
| 3 | Assigned Incidents | Case catalog + Incident Briefing | `app/Http/Controllers/Student/CaseCatalogController.php` | — |
| — | Investigation Workspace | Evidence Explorer/Viewer, Engineering Notebook | `investigation/show.blade.php`, `EvidenceInvestigationService` | — |
| 4 | Hint Unlocking | Idempotent hint unlock with penalty | `HintUnlockService`, `Student/HintController` | — |
| 5 | Timer & Progress | Live elapsed time from `started_at`; `.min-w-0`/`.min-h-0` real-utility fix | `resources/sass/app.scss` | — |
| 6 | Diagnosis Submission | Root cause/fix form, idempotent submit | `DiagnosisSubmissionService`, `investigation/diagnosis.blade.php` | — |
| 7 | Performance Review | Score header, per-criterion breakdown, model solution recap | `PerformanceReviewController`, `investigation/performance-review.blade.php` | — |
| — | **Architectural review** (no behavior change) | SOLID pass, extracted `Badge`/`ScoreFormatter`, XSS hardening | `app/Support/Badge.php`, `app/Support/ScoreFormatter.php` | 222/222 unchanged before/after |

## Phase 6 — Evaluation Engine, Manual Review, Analytics (4 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Evaluation Engine (Core) | Strategy-pattern scoring | `EvaluationService`, `Evaluation/Strategies/*` | `EvaluationEngineTest` |
| 2 | Manual Review | Instructor score override workflow | `ManualReviewService`, `Admin/EvaluationReviewController` | `ManualReviewTest` |
| 3 | Analytics Foundation | Reusable aggregation layer, backend only | `AnalyticsService` | `AnalyticsServiceTest` |
| 4 | Analytics Dashboard | Read-only rendering layer over Milestone 3 | `Admin/AnalyticsController` | `AnalyticsDashboardTest` |

## Phase 12 — Testing & Deployment (5 milestones, closes Version 1)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Authorization audit | Route-vs-Policy audit; 12 guest-coverage tests added | Various test files | +12 tests |
| 2 | Validation/error-state audit | Fixed hint-unlock `response.ok` gap, notebook `maxlength` mirror | `investigation/show.blade.php` | Manual verification |
| 3 | End-to-end testing | 3 full HTTP-level journeys | `EndToEndWorkflowTest` | New file |
| 4 | Performance/N+1 audit | Fixed `needsAttention()` N+1 (2N→8 flat queries) | `Admin\DashboardController`, `CaseCatalogService` | Query-count verification |
| 5 | Deployment prep | `DemoDataSeeder`, deployment guide, 25-check smoke test | `database/seeders/DemoDataSeeder.php`, `docs/12-deployment-guide.md` | Manual smoke test |

**Version 1 close: 291/291 tests passing.**

## Phase 13 — Discussion Engine Foundations (6 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Core tables | `discussion_sessions`/`discussion_turns` migrations | `database/migrations/*` | — |
| 2 | Config columns | `cases.discussion_*` additive columns | migration | — |
| 3 | Core enums | `DiscussionStatus`, `DiscussionTurnRole`, `DiscussionVerdict` | `app/Enums/*` | — |
| 4 | Models | `DiscussionSession`, `DiscussionTurn` | `app/Models/*` | Manual tinker verification |
| 5 | Empty contracts | `LlmClientInterface`, `AiPersonaInterface`, `DiscussionSubjectInterface`, `SystemPrompt`, `LlmTurnResult` | `app/Discussion/Contracts/*` | — |
| 6 | Graph wiring test (closes Phase 13) | Proves schema/models wired | `DiscussionGraphWiringTest` | +14 assertions, 292 total |

## Phase 14 — Provider-Agnostic LLM Client Layer (6 milestones, one a catch-up)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Config + FakeLlmClient | `config/llm.php`, network-free testing binding | `config/llm.php`, `app/Discussion/Testing/FakeLlmClient.php` | 296 total |
| 2 | OpenAiCompatibleLlmClient | Serves Ollama/OpenRouter/OpenAI | `Infrastructure/Llm/Providers/OpenAiCompatibleLlmClient.php` | 302 total |
| 3 | Anthropic + Gemini clients | Two more concrete implementations | `AnthropicLlmClient.php`, `GeminiLlmClient.php` | 312 total |
| 4 | ChainedLlmClient | Ordered fallback core | `Infrastructure/Llm/ChainedLlmClient.php` | 321 total |
| 5 | LlmClientFactory (closes Phase 14) | Provider selection, cost-safety gate | `Infrastructure/Llm/LlmClientFactory.php` | 332 total |
| 6 | StructuredOutputParser (catch-up) | Discovered missing while starting Phase 15 | `StructuredOutputParser.php`, `TurnClassifier.php` | 362 total |

## Phase 15 — Personas & System Prompt Construction (5 milestones, one a catch-up)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Personas + resolver | Mentor/Interviewer, `PersonaResolver` | `Personas/*`, `PersonaResolver.php` | 344 total |
| 2 | Subject adapter | Read-only Version-1 adapter | `Subjects/CaseAttemptDiscussionSubject.php` | 351 total |
| 3 | SystemPromptBuilder | Persona + subject composition | `Support/SystemPromptBuilder.php` | 365 total |
| 5 (catch-up) | LeakageGuard | Discovered missing during pre-Phase-16 audit | `Support/LeakageGuard.php` | 374 total |

## Phase 16 — DiscussionService & State Machine (5 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Core state machine | Turn orchestration, 4 states | `app/Services/DiscussionService.php` | 387 total |
| 2+3 | Event + listener | `DiscussionAccepted`, prefill | `app/Events/DiscussionAccepted.php`, `app/Listeners/PrefillDiagnosisFromAcceptedDiscussion.php` | 390 total |
| 4 | Policy | `DiscussionSessionPolicy` | `app/Policies/DiscussionSessionPolicy.php` | 399 total |
| 5 (closes Phase 16) | End-to-end flows | 5 full scenarios | `DiscussionServiceEndToEndTest` | 405 total |

## Phase 17 — HTTP Layer (4 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Entry layer | Routes, controller, Form Requests | `DiscussionController.php`, `Requests/Student/Start/RespondToDiscussionRequest.php` | 421 total |
| 2 | Full HTTP journey | One continuous conversation over real HTTP | `DiscussionHttpFullJourneyTest` | 422 total |
| 3 | Auth gap closure | 3 missing cross-student tests | `DiscussionControllerTest` | 425 total |
| 4 (closes Phase 17) | Rate limiting | Per-user-per-attempt throttle | `DiscussionServiceProvider::boot()` | 427 total |

## Phase 18 — Investigation Workspace UI (4 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Entry point | "Start Engineering Discussion" button | `View/Components/WorkspaceLayout.php`, `layouts/workspace.blade.php` | 431 total |
| 2–4 (one commit) | Chat panel, end/accept flow, unavailable state | `investigation/show.blade.php` | 4 new feature test files |

## Phase 19 — Performance Review & Admin Config (3 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Performance Review section | Discussion transcript card | `PerformanceReviewController`, `performance-review.blade.php` | — |
| 2 | Admin config fields | Enable toggle, persona, max-rounds | Admin case editor views, `StoreCaseRequest`/`UpdateCaseRequest` | — |
| 3 (closes Phase 19) | Feature tests | Both surfaces | `PerformanceReviewDiscussionSectionTest`, `CaseDiscussionConfigValidationTest` | — |

## Phase 20 — Cost-Safety & Observability (3 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | fallback_log persistence | End-to-end through `DiscussionService` | `ChainedLlmClient.php`, `LlmTurnResult.php` | — |
| 2 | Structured logging | Chain-exhaustion logging | `DiscussionController::logChainExhausted()` | — |
| 3 (closes Phase 20) | Paid-tier safety regression | The invariant-protecting test | New end-to-end test | — |

## Phase 21 — Provider Conformance Validation (4 milestones)

| # | Milestone | Purpose | Key files | Tests |
|---|---|---|---|---|
| 1 | Harness | Golden transcripts + validate-provider command | `app/Discussion/Conformance/*`, `ValidateDiscussionProviderCommand.php` | — |
| 2–4 (closes Phase 21) | Run against real providers | Ollama/OpenRouter/Gemini, 3 runs each; fixed the `/v1` config bug | `config/llm.php`, `docs/15-provider-conformance-results.md` | 482 total |

**Version 2 feature-complete: 482/482 tests passing.**

## Phase 22 — Documentation, Deployment Update & Release

| # | Milestone | Purpose | Key files |
|---|---|---|---|
| 1 | Deployment guide update | LLM provider setup, Ollama note | `docs/12-deployment-guide.md` |
| 2 | CHANGELOG/README | Version 2 summary entry | `CHANGELOG.md`, `README.md` |
| 4 | Wording correction | Version-1-touchpoint scope correction | `docs/14-v2-implementation-roadmap.md`, `CHANGELOG.md` |
| — | This documentation package | docs/00–39, formal report, supervisor-requested files | `docs/*` |

## Post-Phase-22 — Visual Identity Rollout (in progress)

| # | Milestone | Purpose | Key files | Status |
|---|---|---|---|---|
| — | Design System v1 spec | Approved visual-identity specification | [16-design-system.md](16-design-system.md) | Approved |
| — | Admin → Student Workspace nav link | Reciprocal admin sidebar nav link | `admin-navigation.blade.php`, `AdminNavigationTest` | Complete |
| 1 | Token foundation | Palette/radius/spacing/shadow tokens in SCSS | `resources/sass/_variables.scss`, `app.scss` | Complete — verified pixel-identical before/after |
| 2 | Buttons and form controls | Crisp focus ring, Secondary/Ghost button variants | `resources/sass/app.scss` | Complete |
| 3 | Cards and nav chrome | Hairline-border cards, skip links | — | Pending |
| 4 | Empty and loading states | Unify to icon+message+action pattern | — | Pending |
| 5 | Engineering Discussion alignment | Verify/wire tokens into discussion panel | — | Pending |
| 6 | Icon system rollout | Vendor outline icon set | — | Pending |
| 7 | Accessibility and responsive audit | Skip links, aria-labels, contrast, breakpoints | — | Pending |

Both branding items (favicon, `BrandingTest.php`) and the admin-navigation reciprocal link predate the formal Design System spec and remain compatible with it.
