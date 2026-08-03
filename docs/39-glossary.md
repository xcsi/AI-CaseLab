# 39 — Glossary

> **Related:** [01-project-vision](01-project-vision.md) · [09-discussion-engine](09-discussion-engine.md) · `docs/09-workplace-terminology.md` (original source for the narrative glossary)

## Narrative (UI copy) ↔ Technical (code/DB) Terms

Per the project's Two-Layer Naming rule ([01-project-vision.md](01-project-vision.md#the-virtual-engineering-office-narrative)): narrative terms apply only to the presentation layer; the code layer keeps its precise technical name regardless.

| Narrative term (UI copy) | Technical concept (code/DB) | Notes |
|---|---|---|
| **Engineering Office** | Student-facing app shell as a whole | The overall branded student experience |
| **Inbox** | Student dashboard (`dashboard` route, `Student\DashboardController`) | Landing page after login |
| **Assigned Incidents** | Case catalog (`cases.index`, `Student\CaseCatalogController`) | Browse/filter cases |
| **Incident Briefing** | Case detail/pre-investigation page (`cases.show`) | Shows the ticket before starting the clock |
| **Investigation Workspace** | The 3-pane investigation page (`investigation.show`, `Student\CaseAttemptController`) | Core screen |
| **Engineering Notebook** | Notes panel (`InvestigationNote` model, `Student\NotebookController`) | Autosaved free text |
| **Performance Review** | Evaluation result page (`performance-review.show`, `Student\PerformanceReviewController`) | Score + per-criterion feedback |
| **Work History** | List of past attempts (`progress.index`) | |
| **Admin Console** | Admin dashboard/CMS (`/admin`) | Deliberately kept conventional — admins are back-office, not role-playing engineers |
| **Engineering Discussion** | The AI Discussion Engine feature (`DiscussionSession`/`DiscussionTurn`, `DiscussionService`) | See below |

## Domain Terms (Version 1)

| Term | Meaning |
|---|---|
| **Case** | A simulated incident (class `CaseModel`, table `cases` — `Case` is a PHP reserved word) |
| **Evidence Item** | A single artifact within a case (log, code snippet, DB snapshot, API response, screenshot); type-driven via `evidence_type_id` + a `payload` JSON column |
| **Attempt** | One student's investigation of one case (`CaseAttempt`) |
| **Diagnosis** | The student's final structured submission (root cause, proposed fix, confidence, cited evidence) |
| **Evaluation** | The scored outcome of a diagnosis, broken down per rubric criterion |
| **Rubric Criterion** | One scored dimension of a case's model answer, with a `matching_type` determining which strategy scores it |
| **Matching Type** | `keyword`, `evidence_citation`, or `manual` — which `EvaluationStrategyInterface` implementation scores a criterion |
| **Hint** | An optional, point-costing clue attached to a case |
| **Publish invariant** | The rule that a case needs at least one rubric criterion before it can move from draft to published |
| **Manual review** | The instructor workflow for scoring `manual`-matching-type criteria; produces an `instructor_score` that never overwrites the auto-computed `score_awarded` |
| **Activity log** | The generic, polymorphic admin/system audit trail, distinct from student-activity logging (`evidence_views`, `hint_unlocks`) |

## Domain Terms (Version 2 — AI Discussion Engine)

| Term | Meaning |
|---|---|
| **Discussion Session** | One instance of a student defending their reasoning to the AI reviewer, attached polymorphically to a subject (a `CaseAttempt`, in every session Version 2 creates) |
| **Discussion Turn** | One message within a session — student or AI, with the AI's turns carrying a `verdict` |
| **Round** | One student turn plus one AI turn |
| **Persona** | The AI reviewer's "who/how" — Mentor (supportive, offers hints after a stall) or Interviewer (rigorous, never offers hints) |
| **Subject** | The "what" being discussed — `CaseAttempt` today, pluggable via `DiscussionSubjectInterface` for future subjects |
| **Verdict** | The AI's per-turn judgment: `continue`, `accept`, or `end_unresolved` |
| **Fallback chain** | The fixed-order sequence of LLM provider tiers (`ChainedLlmClient`) tried until one answers |
| **Tier** | One provider/model in the fallback chain (Ollama, OpenRouter, Gemini, or the opt-in paid tier) |
| **Structured output** | The required JSON payload (`reply_text`, `verdict`, plus optional fields) every AI turn must produce |
| **Leakage Guard** | The deterministic, non-LLM check that an AI reply doesn't leak the model solution |
| **Golden transcript** | A fixed, scripted conversation used by the provider conformance harness to validate a model's behavior |
| **Chain exhaustion** | The state where every configured LLM tier has failed — surfaced to the student as "AI Discussion Unavailable" |
| **Outcome summary** | The accepted position + round count, written to `discussion_sessions.outcome_summary` on acceptance, read by the diagnosis form at render time |

## Architectural/Pattern Terms

| Term | Meaning in this codebase |
|---|---|
| **Service** | A class orchestrating one use case, calling Repositories/Models and applying business rules |
| **Repository** | The only place that queries Eloquent for a given aggregate root — applied to 6 aggregates, deliberately not to trivial lookup tables |
| **Strategy** | An interchangeable algorithm behind one interface — used for both evaluation scoring and (structurally, if not by literal shared code) LLM provider selection |
| **Policy** | Laravel's authorization mechanism — "can this user do X to this resource" |
| **Form Request** | A dedicated validation + authorization class for one write action |
| **Aggregate root** | A domain entity complex/central enough to warrant its own Repository (see [04-system-architecture.md](04-system-architecture.md)) |
| **Additive** | A change that introduces new tables/columns/classes without modifying existing ones — the standing rule for how Version 2 was built on top of Version 1 |
| **Structural guarantee** | A property enforced by the code's shape itself (e.g., a guarded constructor call with one call site), provable by reading the source, as opposed to a runtime check that could contain a bug |

## Process Terms

| Term | Meaning |
|---|---|
| **Phase** | A major, roadmap-level unit of work (1–22 across Version 1 and Version 2) |
| **Milestone** | A smaller, individually-reviewable, individually-testable unit within a phase |
| **Catch-up milestone** | A milestone discovered missing after its phase was prematurely declared closed, delivered as an explicitly-labeled later commit (see [21-problems-and-solutions.md](21-problems-and-solutions.md)) |
| **Conformance harness** | The manually-invoked, real-provider-calling tool that validates LLM behavior against the behavioral contract, kept separate from the always-green automated suite |
| **ADR** | Architecture Decision Record — a formal Context/Problem/Alternatives/Decision/Consequences write-up for a high-stakes decision (see [31-architecture-decision-records.md](31-architecture-decision-records.md)) |

## Design System Terms

| Term | Meaning |
|---|---|
| **Signal** | The one accent color (`#2952E3`) |
| **Slate** | The cool-tinted neutral color scale for the light shell |
| **Night** | The dark instrument-panel color scale (evidence viewers, Engineering Discussion) |
| **Token** | A named design value (color, spacing, radius, shadow) referenced by components instead of a repeated literal |

Full design-system term definitions in [16-design-system.md](16-design-system.md).
