# 34 — Class Reference

> **Related:** [33-folder-structure](33-folder-structure.md) · [04-system-architecture](04-system-architecture.md) · [08-ai-architecture](08-ai-architecture.md)
> Covers every Service, major Controller, and AI-subsystem class, with responsibility and key methods as established in the codebase and its commit history. For full method signatures, read the class directly — this reference is for orientation, not a substitute for the source.

## Services (`app/Services`)

| Class | Responsibility | Key methods |
|---|---|---|
| `CaseCatalogService` | Case browsing, publish-invariant enforcement | `publish($case)` — checks at least one rubric criterion exists before flipping status |
| `CaseAttemptService` | Starting/resuming an attempt, reattempt policy | `start($case, $user)` — throws `ReattemptNotAllowedException` when blocked |
| `EvidenceInvestigationService` | Evidence retrieval + view recording | Fires `EvidenceViewed` |
| `HintService` / `HintUnlockService` | Hint listing / idempotent unlock + penalty | `unlock($attempt, $hint)` — transaction + row lock, floors `max_possible_score` at zero, re-unlocking is a no-op |
| `DiagnosisSubmissionService` | Idempotent diagnosis submission, triggers evaluation | `submit($attempt, $data)` — returns the existing diagnosis on a stale resubmission rather than erroring; calls `EvaluationService::evaluate()` synchronously |
| `EvaluationService` | Rubric-criterion scoring via Strategy resolution | `evaluate($attempt, $diagnosis)`, `recalculateTotals()` (extracted so initial auto-evaluation and a later manual review recompute totals through the same code) |
| `ManualReviewService` | Instructor score/comment override | Persists `instructor_score`/`instructor_comment` per criterion, never overwrites `score_awarded` |
| `AnalyticsService` | Cohort-level aggregate metrics | `completionMetrics()`, `scoreDistribution()`, `hintUsage()`, `averageCompletionTime()`, `reattemptStatistics()`, `categoryAggregates()` — every method accepts an optional `?array $caseIds` scope |
| `ActivityLogService` | Admin audit-trail entries | Called from admin services on create/update/publish/archive |
| `UserRegistrationService` | Registration | Always assigns the `student` role |
| `DiscussionService` | AI Discussion Engine orchestration | `start()`, `respond()`, `end()` — see [14-state-machine.md](14-state-machine.md) |

## Controllers — Student (`app/Http/Controllers/Student`)

| Class | Owns |
|---|---|
| `DashboardController` | Inbox |
| `CaseCatalogController` | Assigned Incidents index + Incident Briefing show |
| `CaseAttemptController` | Start attempt, Investigation Workspace show |
| `EvidenceController` | Record an evidence view |
| `NotebookController` | Notebook autosave |
| `HintController` | Hint unlock |
| `DiagnosisController` | Create/store diagnosis; reads accepted-discussion prefill at render time |
| `DiscussionController` | start/respond/end/show — see [09-discussion-engine.md](09-discussion-engine.md) |
| `PerformanceReviewController` | Score, per-criterion breakdown, discussion transcript |

## Controllers — Admin (`app/Http/Controllers/Admin`)

| Class | Owns |
|---|---|
| `DashboardController` | Stat cards, Needs Attention, Recent Activity |
| `CaseController` | Full case CRUD (resource, except `show`) + `publish` |
| `HintController` | Nested hint CRUD, `moveUp`/`moveDown` reordering |
| `RubricCriterionController` | Nested rubric criterion CRUD |
| `CategoryController` | Category CRUD (except create/show/edit) |
| `EvaluationReviewController` | Manual review queue (index/edit/update) |
| `AnalyticsController` | Renders `AnalyticsService`'s output, no queries of its own |

## AI Discussion Engine (`app/Discussion`)

| Class | Responsibility |
|---|---|
| `LlmClientInterface` | The one boundary for talking to a language model — `complete(SystemPrompt, array $conversationHistory, string $newMessage): LlmTurnResult` |
| `OpenAiCompatibleLlmClient` | Serves Ollama, OpenRouter, and OpenAI (all OpenAI Chat Completions wire format) |
| `AnthropicLlmClient` | Anthropic Messages API |
| `GeminiLlmClient` | Google Generative Language API |
| `ChainedLlmClient` | Composite `LlmClientInterface` implementing the ordered fallback chain |
| `LlmClientFactory` | The one place "which provider" is decided; `build()` (production chain), `buildSingleTier()` (conformance harness only) |
| `FakeLlmClient` | Network-free testing double — `willReturn()`, `willThrow()`, `recordedCalls()`, `callCount()` |
| `StructuredOutputParser` | Validates/normalizes a model's structured output; throws `StructuredOutputParseException` on a missing required field |
| `ParsedStructuredOutput` | Validated result value object |
| `TurnClassifier` | Extracts `DiscussionVerdict` from an already-validated `ParsedStructuredOutput` |
| `SystemPromptBuilder` | Pure string composition of persona + subject into the system prompt |
| `LeakageGuard` | Deterministic, non-LLM check — `containsLeak(replyText, sensitiveText): bool` |
| `AiPersonaInterface` / `MentorPersona` / `InterviewerPersona` | The "who/how" axis — `systemPromptFragment()`, `shouldOfferHint()`, `acceptanceBar()` |
| `PersonaResolver` | Resolves a config key to a persona instance |
| `DiscussionSubjectInterface` / `CaseAttemptDiscussionSubject` | The "what" axis — `framingText()`, `groundTruthContext()`, `progressContext()` |
| `GoldenTranscript(s)` / `GoldenTranscriptRunner` / `TranscriptRunResult` / `TranscriptTurnResult` | The behavioral conformance harness (`app/Discussion/Conformance`) |
| `ValidateDiscussionProviderCommand` | `php artisan discussion:validate-provider {provider}` |

## Evaluation Engine (`app/Evaluation`)

| Class | Responsibility |
|---|---|
| `EvaluationStrategyInterface` | One scoring algorithm per implementation |
| `KeywordMatchStrategy` | Proportional credit — matched ÷ required × weight |
| `EvidenceCitationStrategy` | Proportional credit based on cited evidence overlap with required evidence |
| `ManualReviewStrategy` | Recorded, excluded from totals until an instructor reviews it |
| `EvaluationStrategyResolver` | Resolves `matching_type` → strategy instance |
| `CriterionResult` | Value object returned by every strategy |

## Repositories (`app/Repositories`)

Six interfaces (`Contracts/`) + six Eloquent implementations (`Eloquent/`) for `CaseModel`, `CaseAttempt`, `Diagnosis`, `Evaluation`, `EvidenceItem`, `Hint` — see [04-system-architecture.md](04-system-architecture.md#where-a-repository-is-deliberately-not-used) for which aggregates get one and why.

## Policies (`app/Policies`)

`CasePolicy`, `CategoryPolicy`, `EvaluationPolicy`, `DiscussionSessionPolicy` — see [04-system-architecture.md](04-system-architecture.md#policies).

## Models (`app/Models`)

18 Eloquent models, one per domain table (`User`, `Role`, `Category`, `CaseModel`, `EvidenceType`, `EvidenceItem`, `Hint`, `RubricCriterion`, `CaseAttempt`, `InvestigationNote`, `Diagnosis`, `EvidenceView`, `HintUnlock`, `Evaluation`, `EvaluationCriterionResult`, `ActivityLog`, `DiscussionSession`, `DiscussionTurn`). Full column-level detail in [07-database-design.md](07-database-design.md).

## Support Classes (`app/Support`)

| Class | Purpose |
|---|---|
| `Badge` | Difficulty/score badge CSS classes — extracted during the Phase 5 review after being duplicated across 4 view files |
| `ScoreFormatter` | Trimmed-decimal score display — extracted after being duplicated across 3 view files |

## View Components (`app/View/Components`)

`AppLayout`, `AdminLayout`, `GuestLayout`, `WorkspaceLayout` — see [06-frontend-architecture.md](06-frontend-architecture.md#layout-components) for `WorkspaceLayout`'s nullable `discussionUrl` parameter specifically.

## Middleware (`app/Http/Middleware`)

`EnsureUserHasRole` (aliased `role`), `EnsureAttemptBelongsToUser` (aliased `attempt.owner`) — see [04-system-architecture.md](04-system-architecture.md#middleware).

## Events & Listeners

| Event | Listener |
|---|---|
| `EvidenceViewed` | `RecordEvidenceView` |
| `CaseAttemptCompleted` | *(none — documented extension point)* |
| `DiscussionAccepted` | `PrefillDiagnosisFromAcceptedDiscussion` |

## Enums (`app/Enums`)

`UserRole`, `CaseDifficulty`, `CaseStatus`, `AttemptStatus`, `ConfidenceLevel`, `MatchingType` (Version 1); `DiscussionStatus`, `DiscussionTurnRole`, `DiscussionVerdict` (Version 2 core state machine — `persona` is deliberately a plain validated string, not an enum, see [07-database-design.md](07-database-design.md)).
