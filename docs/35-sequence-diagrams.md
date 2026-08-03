# 35 — Sequence Diagrams

> **Related:** [36-data-flow](36-data-flow.md) · [09-discussion-engine](09-discussion-engine.md) · [14-state-machine](14-state-machine.md)

## Student Investigation (opening an evidence item)

```mermaid
sequenceDiagram
    participant U as Student (browser)
    participant R as routes/web.php
    participant MW as attempt.owner middleware
    participant C as EvidenceController
    participant S as EvidenceInvestigationService
    participant Ev as EvidenceViewed event
    participant L as RecordEvidenceView listener
    participant DB as MySQL

    U->>R: POST /investigation/{attempt}/evidence/{item}/view
    R->>MW: verify attempt.user_id === current user
    MW-->>C: authorized
    C->>S: recordView(attempt, evidenceItem)
    S->>Ev: fire EvidenceViewed
    Ev->>L: handle()
    L->>DB: upsert evidence_views (view_count++, last_viewed_at)
    C-->>U: 200 JSON
```

## Engineering Discussion (a full turn)

See the detailed sequence diagram (including `LeakageGuard` and transaction rollback behavior) in [14-state-machine.md](14-state-machine.md#discussionservice).

## Diagnosis Submission

```mermaid
sequenceDiagram
    participant U as Student
    participant C as DiagnosisController
    participant DS as DiagnosisSubmissionService
    participant Repo as DiagnosisRepositoryInterface
    participant ES as EvaluationService
    participant Strat as EvaluationStrategyResolver
    participant Evt as CaseAttemptCompleted

    U->>C: POST /investigation/{attempt}/report
    C->>C: StoreDiagnosisRequest validates
    C->>DS: submit(attempt, validated)
    alt diagnosis already exists for this attempt
        DS-->>C: return existing diagnosis (idempotent)
    else first submission
        DS->>Repo: create diagnosis
        DS->>ES: evaluate(attempt, diagnosis)
        loop each rubric_criterion
            ES->>Strat: resolve(matching_type)
            Strat-->>ES: KeywordMatchStrategy / EvidenceCitationStrategy / ManualReviewStrategy
            ES->>ES: score criterion → CriterionResult
        end
        ES->>ES: persist Evaluation + EvaluationCriterionResult[]
        ES->>DS: attempt marked Completed
        DS->>Evt: fire CaseAttemptCompleted
    end
    C-->>U: redirect to Performance Review
```

## Performance Review

```mermaid
sequenceDiagram
    participant U as Student
    participant C as PerformanceReviewController
    participant Repo as EvaluationRepositoryInterface
    participant DiscRepo as DiscussionSession (latest for attempt)

    U->>C: GET /performance-review/{attempt}
    alt no diagnosis submitted yet
        C-->>U: redirect to Investigation Workspace
    else diagnosis exists, no Evaluation row yet
        C->>C: evaluate on first view (retroactive)
        C-->>U: render with fresh score
    else Evaluation exists
        C->>Repo: load evaluation + criterion results
        C->>DiscRepo: load most recent discussion session (any outcome)
        C-->>U: render score, per-criterion breakdown, model solution, discussion transcript (if any)
    end
```

## Admin Review (Manual Review workflow)

```mermaid
sequenceDiagram
    participant A as Instructor/Admin
    participant C as Admin.EvaluationReviewController
    participant Pol as EvaluationPolicy
    participant MR as ManualReviewService
    participant ES as EvaluationService

    A->>C: GET /admin/evaluations (queue: awaiting instructor review)
    C->>Pol: authorize viewAny
    C-->>A: list of evaluations with manual criteria
    A->>C: GET /admin/evaluations/{evaluation}/edit
    A->>C: PATCH /admin/evaluations/{evaluation} (per-criterion score + comment)
    C->>Pol: authorize update
    C->>MR: review(evaluation, scores, comments)
    MR->>MR: persist instructor_score/instructor_comment (score_awarded untouched)
    MR->>ES: recalculateTotals()
    C-->>A: redirect to queue
```
