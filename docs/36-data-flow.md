# 36 — Data Flow

> **Related:** [35-sequence-diagrams](35-sequence-diagrams.md) · [04-system-architecture](04-system-architecture.md) · [08-ai-architecture](08-ai-architecture.md)

## End-to-End Request Lifecycle (general shape)

```mermaid
flowchart LR
    Browser -->|HTTP request| Route[routes/web.php]
    Route --> MW1[auth middleware]
    MW1 --> MW2[role / attempt.owner middleware]
    MW2 --> FR[Form Request<br/>validate + authorize]
    FR --> Ctrl[Controller]
    Ctrl --> Svc[Service<br/>business logic]
    Svc --> Repo[Repository]
    Repo --> Model[Eloquent Model]
    Model --> DB[(MySQL)]
    Svc -.->|side effects| Event[Event]
    Event --> Listener[Listener]
    Ctrl --> View[Blade View]
    View --> Browser
```

Every write path in the application follows this exact shape — there is no path where a controller queries Eloquent directly for domain data, and no path where business logic lives in a Blade view beyond trivial display branching (e.g., which evidence-type renderer to show).

## The Student Investigation Journey (full data flow)

```mermaid
flowchart TD
    A[Browse Assigned Incidents] --> B[Open Incident Briefing]
    B --> C[Start Attempt<br/>CaseAttemptService::start]
    C --> D[Investigation Workspace]
    D --> E[Open evidence items<br/>evidence_views recorded]
    D --> F[Write notebook notes<br/>autosaved]
    D --> G[Unlock hints<br/>hint_unlocks recorded, max_possible_score reduced]
    D --> H{discussion_enabled?}
    H -->|yes| I[Engineering Discussion<br/>see 08-ai-architecture.md]
    I --> J[Accepted position<br/>pre-fills diagnosis form]
    H -->|no| K[Submit Diagnosis directly]
    J --> K
    K --> L[DiagnosisSubmissionService::submit<br/>idempotent]
    L --> M[EvaluationService::evaluate<br/>Strategy-resolved per criterion]
    M --> N[Performance Review<br/>score + breakdown + discussion transcript]
    N --> O{allow_reattempt?}
    O -->|yes| C
```

## The Admin Content Lifecycle

```mermaid
flowchart LR
    A[Create Case<br/>draft] --> B[Add evidence<br/>seeder/factory/tinker only]
    A --> C[Add Hints]
    A --> D[Add Rubric Criteria]
    B --> E{Publish invariant met?<br/>≥1 rubric criterion}
    C --> E
    D --> E
    E -->|yes| F[Published<br/>visible in catalog]
    E -->|no| G[Publish blocked<br/>CaseCatalogService::publish]
    F --> H[Students attempt]
    H --> I[Diagnoses submitted]
    I --> J{Any manual rubric criteria?}
    J -->|yes| K[Manual Review queue<br/>Admin EvaluationReviewController]
    J -->|no| L[Fully auto-scored]
    K --> M[ManualReviewService::review<br/>EvaluationService::recalculateTotals]
    F --> N[Edit published case]
    N --> O[cases.version increments]
```

## The Engineering Discussion Turn (data flow)

```mermaid
flowchart TD
    A[Student submits message] --> B[DiscussionService::respond]
    B --> C[Persist student turn<br/>in DB transaction]
    C --> D[SystemPromptBuilder<br/>persona + subject → system prompt]
    D --> E[LlmClientInterface::complete]
    E --> F{ChainedLlmClient<br/>tries tiers in fixed order}
    F --> G[Ollama]
    F -.->|if configured & Ollama unavailable| H[OpenRouter free]
    F -.->|if configured & above unavailable| I[Gemini free]
    F -.->|only if LLM_ALLOW_PAID_FALLBACK=true| J[Paid tier]
    F -->|all exhausted| K[NoLlmProviderAvailableException<br/>→ AI Discussion Unavailable, transaction rolled back]
    G --> L[StructuredOutputParser + TurnClassifier]
    H --> L
    I --> L
    J --> L
    L --> M[LeakageGuard.containsLeak?]
    M -->|yes| N[LeakedReplyException<br/>transaction rolled back]
    M -->|no| O[Persist AI turn<br/>verdict, tokens, provider, fallback_log]
    O --> P{verdict}
    P -->|continue| Q[round_count++, stay Active]
    P -->|accept| R[Accepted<br/>fire DiscussionAccepted]
    P -->|end_unresolved / round cap| S[MaxRoundsReached]
    R --> T[PrefillDiagnosisFromAcceptedDiscussion<br/>writes discussion_sessions.outcome_summary only]
```

## Analytics Data Flow

```mermaid
flowchart LR
    subgraph Sources
        CA[(case_attempts)]
        EV[(evaluations)]
        HU[(hint_unlocks)]
    end
    Sources --> Repo[Repository aggregate methods<br/>statusCounts, scorePercentages,<br/>usageCounts, averageCompletionSeconds,<br/>reattemptCounts]
    Repo --> AS[AnalyticsService<br/>summary / categoryAggregates]
    AS --> Ctrl[Admin AnalyticsController]
    Ctrl --> View[Analytics Dashboard]
```

Every `AnalyticsService` method accepts the same optional `?array $caseIds` scope — platform-wide (`null`), single-case, or category-rollup — composed from one implementation rather than three, per [20-design-decisions.md](20-design-decisions.md).
