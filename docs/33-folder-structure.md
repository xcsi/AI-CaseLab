# 33 — Folder Structure

> **Related:** [04-system-architecture](04-system-architecture.md) · [34-class-reference](34-class-reference.md)
> Standard Laravel 11 layout; only application-specific folders are annotated below.

```
AICaseLab/
├── app/
│   ├── Console/Commands/          One command: ValidateDiscussionProviderCommand (provider conformance harness)
│   ├── Discussion/                 The entire AI Discussion Engine module — see below
│   ├── Enums/                      13 PHP backed enums (Version 1 + Version 2)
│   ├── Evaluation/                 The Evaluation Engine's Strategy pattern
│   │   ├── Contracts/               EvaluationStrategyInterface
│   │   ├── Strategies/              KeywordMatchStrategy, EvidenceCitationStrategy, ManualReviewStrategy
│   │   ├── CriterionResult.php      Value object
│   │   └── EvaluationStrategyResolver.php
│   ├── Events/                     EvidenceViewed, CaseAttemptCompleted, DiscussionAccepted
│   ├── Exceptions/                 ReattemptNotAllowedException (app-level, not Discussion-specific)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                 Breeze-generated
│   │   │   ├── Student/              8 controllers — the student-facing surface
│   │   │   └── Admin/                7 controllers — the admin/instructor surface
│   │   ├── Middleware/              EnsureAttemptBelongsToUser, EnsureUserHasRole
│   │   └── Requests/                Form Requests, mirrored Admin/ and Student/Auth/
│   ├── Listeners/                  RecordEvidenceView, PrefillDiagnosisFromAcceptedDiscussion
│   ├── Models/                     18 Eloquent models — one per domain table + 2 Discussion models
│   ├── Policies/                   CasePolicy, CategoryPolicy, EvaluationPolicy, DiscussionSessionPolicy
│   ├── Providers/                  AppServiceProvider, RepositoryServiceProvider, DiscussionServiceProvider
│   ├── Repositories/
│   │   ├── Contracts/                6 interfaces (the aggregate roots — see 04-system-architecture.md)
│   │   └── Eloquent/                 6 implementations
│   ├── Services/                   11 Services — the business-logic layer (see 34-class-reference.md)
│   ├── Support/                    Badge, ScoreFormatter — small extracted view-helper classes
│   └── View/Components/            AppLayout, AdminLayout, GuestLayout, WorkspaceLayout
│
├── app/Discussion/                 The AI Discussion Engine module (see 08-ai-architecture.md)
│   ├── Conformance/                 GoldenTranscript(s), GoldenTranscriptRunner, TranscriptRun/TurnResult — the behavioral validation harness
│   ├── Contracts/                   LlmClientInterface, AiPersonaInterface, DiscussionSubjectInterface
│   ├── Exceptions/                  7 typed exceptions (see 05-backend-architecture.md)
│   ├── Infrastructure/Llm/          ChainedLlmClient, LlmClientFactory
│   │   ├── Providers/                 OpenAiCompatibleLlmClient, AnthropicLlmClient, GeminiLlmClient
│   │   └── Support/                   StructuredOutputParser, ParsedStructuredOutput
│   ├── Personas/                    MentorPersona, InterviewerPersona
│   ├── Subjects/                    CaseAttemptDiscussionSubject
│   ├── Support/                     SystemPromptBuilder, TurnClassifier, LeakageGuard
│   ├── Testing/                     FakeLlmClient — network-free testing implementation
│   ├── LlmTurnResult.php            Value object
│   ├── SystemPrompt.php             Value object
│   └── PersonaResolver.php
│
├── config/
│   ├── llm.php                     Per-tier provider settings — see 26-configuration-reference.md
│   └── discussion_personas.php     Mentor/Interviewer config data
│
├── database/
│   ├── migrations/                 23 migrations
│   ├── seeders/                    RoleSeeder, AdminUserSeeder, EvidenceTypeSeeder, CategorySeeder, DemoDataSeeder
│   └── factories/
│
├── resources/
│   ├── sass/
│   │   ├── _variables.scss          Bootstrap variable overrides — the Design System's Sass-level tokens
│   │   └── app.scss                 CSS custom properties + all bespoke component CSS
│   ├── js/app.js                   Bootstrap JS bundle only — no framework
│   └── views/                      Blade templates — see 06-frontend-architecture.md
│
├── routes/
│   ├── web.php                     All application routes (no separate api.php in active use)
│   └── auth.php                    Breeze auth routes
│
├── tests/
│   ├── Feature/                    ~65 files — HTTP-level tests, organized Admin/Auth/Discussion/Student/
│   └── Unit/                       Pure-logic tests
│
├── docs/                           This documentation package (00–39, README, and the formal project report)
├── CHANGELOG.md                    Keep-a-Changelog-format history — the primary source for docs 18–22
└── README.md
```

## `app/Discussion/` in Detail

This module is the one part of the codebase organized by *domain concept* rather than by Laravel's conventional *technical role* (Controllers/Models/Services split) — a deliberate choice matching the frozen design spec's own folder tree, since the module's internal structure (Contracts → Infrastructure → Support → Personas/Subjects) needed to communicate the Subject × Persona × Provider extensibility story on its own, independent of how the rest of the app is organized. See [08-ai-architecture.md](08-ai-architecture.md) for what each subfolder's classes are responsible for.

## Where Things Are Not

- **No `app/Http/Resources/`** — the application returns Blade views or plain JSON arrays from controllers (the Discussion routes), not API Resource transformers; the JSON surface is small enough that a transformation layer wasn't judged to earn its complexity.
- **No `app/Jobs/`** — no `ShouldQueue` jobs exist anywhere in the application; every write path is synchronous. See [25-deployment-guide.md](25-deployment-guide.md).
- **No `storage/app/public` usage** — no file-storage code path is active; see [28-maintenance-guide.md](28-maintenance-guide.md#known-limitations).
