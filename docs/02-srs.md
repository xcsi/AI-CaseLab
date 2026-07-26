# AI CaseLab — Software Requirements Specification (SRS)

## 1. Actors

| Actor | Type | Description |
|---|---|---|
| **Student** | Primary human actor | Investigates cases, submits diagnoses, tracks own progress |
| **Instructor** | Primary human actor | Reviews cohort/class analytics, may review individual submissions, manages assigned cases (v1: read-mostly + case assignment) |
| **Admin** | Primary human actor | Superset of Instructor; authors/publishes cases, evidence, rubrics; manages users/roles/categories |
| **Evaluation Engine** | System actor (internal) | Automated component invoked on submission; scores a diagnosis against a case's rubric via a pluggable strategy |
| **Guest** | Secondary human actor | Unauthenticated visitor; can view marketing/landing page and a sample/demo case only |

Note: Instructor and Admin share the same `admin`-tier authentication guard in v1, differentiated by a `role` + Policy checks (`instructor` role gets read/analytics permissions; `admin` role gets full content-management permissions). This keeps the auth system simple while the SRS still models them as distinct actors for future separation.

## 2. Use Cases

### 2.1 Use Case Diagram (textual)

```
Guest ──────────────► (View Landing Page)
                       (View Demo Case)

Student ────────────► (Register / Login / Logout)
                       (Browse Case Catalog)
                       (View Case Details)
                       (Start Case Attempt)
                       (View Evidence Item)      ─include─► (Log Evidence View for Analytics)
                       (Take Investigation Notes)
                       (Request Hint)            ─include─► (Apply Score Penalty)
                       (Submit Diagnosis)        ─include─► (Trigger Evaluation)
                       (View Evaluation Result)
                       (View Own Dashboard / Progress)
                       (Re-attempt Case)         ─extend──► (Submit Diagnosis)

Instructor ──────────► (Login / Logout)
                       (View Cohort Analytics)
                       (View Student Submission Detail)
                       (Assign Case to Class)         [v1.x candidate]

Admin ───────────────► (Login / Logout)
                       (Create/Edit/Publish/Archive Case)
                       (Manage Evidence Items)
                       (Manage Hints)
                       (Define Evaluation Rubric)
                       (Manage Categories/Tags)
                       (Manage Users & Roles)
                       (View Platform Analytics)

Evaluation Engine ───► (Score Submission)   ◄── invoked by (Submit Diagnosis)
```

### 2.2 Key Use Case Descriptions

**UC-01: Start Case Attempt**
- Actor: Student
- Precondition: Student authenticated; case is published.
- Flow: Student opens a case → system creates a `CaseAttempt` record (status `in_progress`) → student redirected to Investigation Page.
- Postcondition: Attempt exists; timer/started_at recorded.

**UC-02: View Evidence Item**
- Actor: Student
- Precondition: Active `CaseAttempt` exists.
- Flow: Student selects an evidence item → system renders the correct viewer component based on `evidence_type` → logs an `EvidenceView` event (attempt_id, evidence_id, viewed_at).
- Postcondition: Evidence marked viewed for this attempt; contributes to "thoroughness" metric.

**UC-03: Request Hint**
- Actor: Student
- Precondition: Active attempt; hint not already unlocked; hints remaining for case.
- Flow: Student clicks "Reveal Hint" → confirmation of score penalty shown → on confirm, system creates `HintUnlock` record and reveals hint content.
- Postcondition: Hint visible; max achievable score for this attempt reduced by hint's penalty weight.

**UC-04: Submit Diagnosis**
- Actor: Student; System actor: Evaluation Engine
- Precondition: Active attempt not already submitted.
- Flow: Student fills structured report form (root cause, evidence references, proposed fix, confidence) → submits → system creates `Diagnosis` record, marks attempt `submitted`, invokes Evaluation Engine synchronously → Engine runs the case's configured `EvaluationStrategy` against rubric → creates `Evaluation` record with score + per-criterion feedback → attempt marked `completed`.
- Postcondition: Student redirected to Evaluation Result page.
- Alternate flow: If case allows re-attempts and student re-enters, a new `CaseAttempt` is created; history retained.

**UC-05: Create/Publish Case (Admin)**
- Actor: Admin
- Flow: Admin creates case shell (title, category, difficulty, ticket text) → adds evidence items (per type, via type-specific forms) → adds hints with penalty weights → defines rubric criteria (each criterion: description, keywords/expected-answer data, weight) → sets status to `draft` → previews → publishes (`status = published`).
- Postcondition: Case visible to students only when `published`.

**UC-06: View Cohort Analytics (Instructor/Admin)**
- Actor: Instructor, Admin
- Flow: Selects a class/cohort or case → system aggregates attempts (completion rate, avg score, avg time, most-used hints, common wrong root causes) → renders charts/tables.

## 3. System Features

1. **Authentication & Authorization** — Laravel Breeze/Fortify-based auth, role-based Policies, middleware guards per route group.
2. **Case Catalog & Browsing** — searchable/filterable list, difficulty badges, progress indicators.
3. **Case Investigation Workspace** — ticket panel, evidence explorer, multi-type evidence viewers, notes panel — the core "IDE-like" experience.
4. **Hint System** — progressive disclosure with score-penalty economics.
5. **Notes System** — autosaving free-text notes scoped to a case attempt.
6. **Diagnosis Submission & Structured Reporting** — guided form capturing root cause, evidence citations, fix proposal.
7. **Evaluation Engine** — pluggable, strategy-based scoring against per-case rubrics; produces detailed feedback.
8. **Student Dashboard** — progress, history, scores, recommendations.
9. **Instructor/Admin Analytics Dashboard** — cohort and platform-level statistics.
10. **Content Authoring (Admin CMS)** — full CRUD over cases, evidence, hints, rubrics, categories.
11. **User & Role Management (Admin)**.
12. **Activity Logging** — evidence views, hint unlocks, submissions, for both grading and analytics.

## 4. User Stories

**Student**
- As a student, I want to filter cases by difficulty so I can start with something appropriate for my level.
- As a student, I want to see a realistic support ticket first so the investigation feels like a real job, not a quiz.
- As a student, I want to view logs and code side by side so I can correlate an error message with the code that produced it.
- As a student, I want to take notes without leaving the page so I don't lose my train of thought.
- As a student, I want to know a hint will cost me points before I open it so I can make an informed trade-off.
- As a student, I want structured feedback after submitting (what I got right/wrong per criterion) so I actually learn from the attempt, not just see a number.
- As a student, I want a dashboard showing my completed cases and average score so I can track my growth over time.

**Instructor**
- As an instructor, I want to see which cases my class struggles with most so I know what to cover in the next lecture.
- As an instructor, I want to see an individual student's investigation trail (evidence viewed, hints used, notes) so I can give targeted feedback beyond the auto-score.

**Admin**
- As an admin, I want to author a new case without touching code so non-technical staff (or me, quickly) can expand the case library.
- As an admin, I want to define a rubric with weighted criteria so scoring is consistent and defensible.
- As an admin, I want to keep a case in `draft` while I build it, and only expose it to students when I publish it.

## 5. Constraints

**Technical**
- Must use Laravel + PHP + MySQL + Bootstrap + vanilla/light JS (no mandated SPA framework) per project requirements.
- Must follow MVC with an added Service + Repository layer; no business logic in controllers or Blade views.
- Must support SOLID and Repository Pattern where it adds value (not applied dogmatically to trivial models — see Architecture doc for where it is and isn't used).

**Project/Academic**
- Solo (or small team) graduation/internship project — features must be sequenced into small, independently demoable milestones (see Task Breakdown doc).
- Must be demoable with seeded sample data (at least 3–5 fully authored cases across categories) for evaluation/defense.

**Business**
- v1 evaluation must be deterministic and explainable (rubric/keyword-based) rather than opaque AI scoring, so student feedback is defensible in an academic setting.

**Assumptions**
- Single-language UI (English or Arabic — decide once, i18n not required for v1 unless requested).
- Single deployment environment; no multi-tenant school separation needed initially.
