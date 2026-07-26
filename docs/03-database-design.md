# AI CaseLab — Database Design

No migrations yet — this is the logical/physical design we will implement from later. Two design decisions worth calling out up front, because they drive scalability (NFR1) and the Repository/Strategy patterns used later:

**Decision 1 — Evidence is one polymorphic-ish table, not one table per evidence type.**
A naive design would create `evidence_logs`, `evidence_code_snippets`, `evidence_db_snapshots`, `evidence_api_responses`, `evidence_screenshots` as separate tables (class-table inheritance). That's more strictly typed, but every new evidence type = a new migration + new model + new join everywhere evidence is listed. Since "add new case/evidence types without schema rewrites" is an explicit NFR, we instead use a single `evidence_items` table with an `evidence_type` reference and a `payload` JSON column holding type-specific data. A `EvidenceRenderer` (Strategy pattern, see Architecture doc) interprets `payload` per type on the frontend/backend. We accept slightly weaker column-level typing in exchange for zero-migration extensibility — the right trade for a content library that will keep growing.

**Decision 2 — Rubric-based evaluation, not free text grading.**
`rubric_criteria` belongs to a case; `evaluations` + `evaluation_criterion_results` store the graded outcome per attempt per criterion. This keeps grading deterministic/explainable (a constraint from the SRS) while leaving room for an AI-assisted `EvaluationStrategy` later that still writes into the same result tables.

## 1. ER Diagram

```mermaid
erDiagram
    ROLES ||--o{ USERS : "has"
    USERS ||--o{ CASES : "authors"
    CATEGORIES ||--o{ CASES : "groups"
    EVIDENCE_TYPES ||--o{ EVIDENCE_ITEMS : "classifies"
    CASES ||--o{ EVIDENCE_ITEMS : "contains"
    CASES ||--o{ HINTS : "offers"
    CASES ||--o{ RUBRIC_CRITERIA : "defines"
    CASES ||--o{ CASE_ATTEMPTS : "attempted via"
    USERS ||--o{ CASE_ATTEMPTS : "makes"
    CASE_ATTEMPTS ||--o| INVESTIGATION_NOTES : "has"
    CASE_ATTEMPTS ||--o| DIAGNOSES : "produces"
    CASE_ATTEMPTS ||--o{ EVIDENCE_VIEWS : "logs"
    EVIDENCE_ITEMS ||--o{ EVIDENCE_VIEWS : "viewed in"
    CASE_ATTEMPTS ||--o{ HINT_UNLOCKS : "logs"
    HINTS ||--o{ HINT_UNLOCKS : "unlocked in"
    CASE_ATTEMPTS ||--o| EVALUATIONS : "graded as"
    DIAGNOSES ||--o| EVALUATIONS : "graded by"
    EVALUATIONS ||--o{ EVALUATION_CRITERION_RESULTS : "breaks down into"
    RUBRIC_CRITERIA ||--o{ EVALUATION_CRITERION_RESULTS : "scored against"

    ROLES {
        bigint id PK
        string name
    }
    USERS {
        bigint id PK
        bigint role_id FK
        string name
        string email
        string password
        timestamp email_verified_at
    }
    CATEGORIES {
        bigint id PK
        string name
        string slug
    }
    CASES {
        bigint id PK
        bigint category_id FK
        bigint created_by FK
        string title
        string slug
        text ticket_content
        enum difficulty
        int estimated_minutes
        enum status
        text model_solution_summary
        decimal max_score
        boolean allow_reattempt
        timestamp deleted_at
    }
    EVIDENCE_TYPES {
        bigint id PK
        string code
        string label
    }
    EVIDENCE_ITEMS {
        bigint id PK
        bigint case_id FK
        bigint evidence_type_id FK
        string title
        text description
        int sequence_order
        json payload
    }
    HINTS {
        bigint id PK
        bigint case_id FK
        int order_index
        text content
        decimal score_penalty
    }
    RUBRIC_CRITERIA {
        bigint id PK
        bigint case_id FK
        string title
        text description
        decimal weight
        enum matching_type
        json expected_data
    }
    CASE_ATTEMPTS {
        bigint id PK
        bigint case_id FK
        bigint user_id FK
        enum status
        timestamp started_at
        timestamp submitted_at
        timestamp completed_at
        decimal score_earned
        decimal max_possible_score
    }
    INVESTIGATION_NOTES {
        bigint id PK
        bigint case_attempt_id FK
        longtext content
        timestamp updated_at
    }
    DIAGNOSES {
        bigint id PK
        bigint case_attempt_id FK
        longtext root_cause_text
        longtext proposed_fix_text
        json cited_evidence_ids
        enum confidence_level
        timestamp submitted_at
    }
    EVIDENCE_VIEWS {
        bigint id PK
        bigint case_attempt_id FK
        bigint evidence_item_id FK
        int view_count
        timestamp first_viewed_at
        timestamp last_viewed_at
    }
    HINT_UNLOCKS {
        bigint id PK
        bigint case_attempt_id FK
        bigint hint_id FK
        timestamp unlocked_at
    }
    EVALUATIONS {
        bigint id PK
        bigint case_attempt_id FK
        bigint diagnosis_id FK
        decimal total_score
        decimal max_score
        text feedback_summary
        string strategy_used
        timestamp evaluated_at
    }
    EVALUATION_CRITERION_RESULTS {
        bigint id PK
        bigint evaluation_id FK
        bigint rubric_criterion_id FK
        decimal score_awarded
        decimal max_score
        text feedback_text
    }
```

## 2. Tables (detail)

### roles
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string, unique | `student`, `instructor`, `admin` |

### users
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| role_id | bigint FK → roles.id | restrict on delete |
| name | string | |
| email | string, unique | |
| password | string (hashed) | |
| email_verified_at | timestamp, nullable | |
| remember_token | string, nullable | Laravel standard |
| timestamps | | |

### categories
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | e.g. "Backend", "Database", "Frontend", "DevOps" |
| slug | string, unique | |
| description | text, nullable | |

### cases
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| category_id | bigint FK → categories.id | restrict on delete |
| created_by | bigint FK → users.id | admin author; restrict on delete |
| title | string | |
| slug | string, unique | |
| ticket_content | text | the initial support ticket shown to the student |
| difficulty | enum(`easy`,`medium`,`hard`) | |
| estimated_minutes | int | |
| status | enum(`draft`,`published`,`archived`) | default `draft` |
| model_solution_summary | text, nullable | shown after completion if `allow_reattempt` policy permits |
| max_score | decimal(5,2) | sum of rubric weights, denormalized for quick display |
| allow_reattempt | boolean | default true |
| deleted_at | timestamp, nullable | **soft delete** — cases are archived, never hard-deleted, to preserve historical attempts |
| timestamps | | |

### evidence_types
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| code | string, unique | `support_ticket` (rare, usually inline), `log`, `code_snippet`, `db_snapshot`, `api_response`, `screenshot` |
| label | string | display name |

*Seeded once; adding a new evidence type in the future = one new row + one new frontend renderer, no migration.*

### evidence_items
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_id | bigint FK → cases.id | cascade on delete |
| evidence_type_id | bigint FK → evidence_types.id | restrict on delete |
| title | string | e.g. "Nginx error log — prod-3" |
| description | text, nullable | short caption |
| sequence_order | int | display order in the evidence explorer |
| payload | json | type-specific data (log text, code+language, table schema + sample rows, HTTP request/response, image path) |
| timestamps | | |

### hints
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_id | bigint FK → cases.id | cascade on delete |
| order_index | int | hints unlock in order |
| content | text | |
| score_penalty | decimal(5,2) | deducted from max achievable score when unlocked |

### rubric_criteria
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_id | bigint FK → cases.id | cascade on delete |
| title | string | e.g. "Correctly identifies N+1 query as root cause" |
| description | text, nullable | |
| weight | decimal(5,2) | points this criterion contributes |
| matching_type | enum(`keyword`,`evidence_citation`,`manual`) | which `EvaluationStrategy` handles it |
| expected_data | json | e.g. `{"keywords": ["n+1","eager load"]}` or `{"required_evidence_ids":[4,7]}` |

### case_attempts
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_id | bigint FK → cases.id | restrict on delete |
| user_id | bigint FK → users.id | restrict on delete |
| status | enum(`in_progress`,`submitted`,`completed`,`abandoned`) | |
| started_at | timestamp | |
| submitted_at | timestamp, nullable | |
| completed_at | timestamp, nullable | |
| score_earned | decimal(5,2), nullable | |
| max_possible_score | decimal(5,2) | case's max_score minus any hint penalties incurred |
| timestamps | | |

### investigation_notes
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_attempt_id | bigint FK → case_attempts.id, **unique** | 1:1 with attempt (v1); could evolve to 1:N timestamped entries later |
| content | longtext | autosaved free text |
| timestamps | | `updated_at` doubles as "last autosave" |

### diagnoses
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_attempt_id | bigint FK → case_attempts.id, **unique** | 1:1 |
| root_cause_text | longtext | |
| proposed_fix_text | longtext | |
| cited_evidence_ids | json | array of `evidence_items.id` the student referenced |
| confidence_level | enum(`low`,`medium`,`high`) | |
| submitted_at | timestamp | |

### evidence_views
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_attempt_id | bigint FK → case_attempts.id | cascade on delete |
| evidence_item_id | bigint FK → evidence_items.id | cascade on delete |
| view_count | int | increments on each open |
| first_viewed_at | timestamp | |
| last_viewed_at | timestamp | |

Unique constraint on `(case_attempt_id, evidence_item_id)` — one row per evidence item per attempt, counters increment.

### hint_unlocks
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_attempt_id | bigint FK → case_attempts.id | cascade on delete |
| hint_id | bigint FK → hints.id | cascade on delete |
| unlocked_at | timestamp | |

Unique constraint on `(case_attempt_id, hint_id)`.

### evaluations
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| case_attempt_id | bigint FK → case_attempts.id, **unique** | 1:1 |
| diagnosis_id | bigint FK → diagnoses.id | |
| total_score | decimal(5,2) | |
| max_score | decimal(5,2) | |
| feedback_summary | text | |
| strategy_used | string | e.g. `RubricKeywordEvaluationStrategy` — recorded for auditability |
| evaluated_at | timestamp | |

### evaluation_criterion_results
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| evaluation_id | bigint FK → evaluations.id | cascade on delete |
| rubric_criterion_id | bigint FK → rubric_criteria.id | restrict on delete |
| score_awarded | decimal(5,2) | |
| max_score | decimal(5,2) | |
| feedback_text | text | per-criterion explanation shown to student |

## 3. Relationships Summary

- `roles` 1—N `users`
- `categories` 1—N `cases`
- `users` (as author) 1—N `cases`
- `cases` 1—N `evidence_items`, `hints`, `rubric_criteria`, `case_attempts`
- `evidence_types` 1—N `evidence_items`
- `users` 1—N `case_attempts`
- `case_attempts` 1—1 `investigation_notes`
- `case_attempts` 1—1 `diagnoses`
- `case_attempts` 1—1 `evaluations`
- `case_attempts` N—M `evidence_items` through `evidence_views`
- `case_attempts` N—M `hints` through `hint_unlocks`
- `diagnoses` 1—1 `evaluations`
- `evaluations` 1—N `evaluation_criterion_results`
- `rubric_criteria` 1—N `evaluation_criterion_results`

## 4. Indexing Notes (for later migrations)

- `cases`: index on `(status, category_id, difficulty)` for catalog filtering.
- `case_attempts`: index on `(user_id, status)` for dashboard queries; index on `case_id` for instructor analytics.
- `evidence_items`: index on `case_id`.
- `evidence_views` / `hint_unlocks`: composite unique indexes as noted above double as lookup indexes.
