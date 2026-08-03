# 31 — Architecture Decision Records

> **Related:** [20-design-decisions](20-design-decisions.md) (narrative form) · [18-development-phases](18-development-phases.md)
> Format: Context → Problem → Alternatives Considered → Decision → Consequences → Future Impact. These are the highest-stakes decisions in the project, presented in strict ADR form; [20-design-decisions.md](20-design-decisions.md) covers a broader set in narrative form.

---

## ADR-001: Evidence as a Single Polymorphic-Shaped Table

**Context:** the platform's core scalability requirement (NFR1) is that new case/evidence types must not require schema rewrites.

**Problem:** a naturally-typed design would create one table per evidence type (`evidence_logs`, `evidence_code_snippets`, `evidence_db_snapshots`, ...) — class-table inheritance.

**Alternatives considered:**
- *Class-table inheritance* (one table per type) — stronger column-level typing, but every new evidence type means a new migration, a new model, and a new join everywhere evidence is listed.
- *Single Table Inheritance* (one giant table with every type's columns, mostly null) — avoids new migrations for new types but produces an unbounded, mostly-empty column set as types accumulate.

**Decision:** a single `evidence_items` table with an `evidence_type_id` reference and a `payload` JSON column holding type-specific data; a per-type renderer interprets `payload` on the frontend.

**Consequences:** weaker column-level typing (payload shape is enforced at the application layer, not the database), in exchange for zero-migration extensibility — adding a new evidence type is one seed row plus one new Blade renderer.

**Future impact:** this decision is what makes the never-built-but-designed-for evidence-authoring admin UI (see [29-future-roadmap.md](29-future-roadmap.md)) a contained, additive piece of work rather than a schema migration project.

---

## ADR-002: Repository Pattern Applied Selectively, Not Uniformly

**Context:** the project's standing architecture rules call for a Service + Repository layered architecture (see [04-system-architecture.md](04-system-architecture.md)).

**Problem:** applying the Repository Pattern to every model, including trivial lookup tables (`Category`, `Role`, `EvidenceType`), would add an interface and an Eloquent implementation for classes with no real query complexity.

**Alternatives considered:**
- *Apply uniformly to every model* — maximal consistency, but ceremony without payoff for trivial CRUD.
- *Apply to none, use Eloquent directly everywhere* — simpler, but Services querying Eloquent directly for the six real aggregates would be harder to test in isolation and would blur the Service/persistence boundary.

**Decision:** Repository interfaces + Eloquent implementations only for the six aggregates with real query complexity and business rules (`CaseModel`, `CaseAttempt`, `EvidenceItem`, `Diagnosis`, `Evaluation`, `Hint`); trivial lookups use Eloquent directly.

**Consequences:** a stated, defensible dividing line rather than a uniform rule — requires a judgment call each time a new model is added, but avoids six-plus needless interface/implementation pairs for tables that will never need query-complexity abstraction.

**Future impact:** the two Version-2 discussion models (`DiscussionSession`, `DiscussionTurn`) deliberately did **not** get Repository wrappers — `DiscussionService` accesses them via Eloquent directly — because their query patterns never grew complex enough to warrant one, consistent application of the same dividing line to a new subsystem.

---

## ADR-003: The AI Discussion Engine's Cost-Safety Guarantee Is Structural, Not a Runtime Check

**Context:** the Engineering Discussion feature introduces the project's only variable-cost, third-party-dependent runtime behavior.

**Problem:** "never silently spend money on a paid LLM provider" needs to be an actual, provable guarantee, not an aspiration that a future bug could quietly violate.

**Alternatives considered:**
- *A runtime feature flag checked before every paid-tier call* — simpler to implement, but a bug anywhere in that check (a missed guard on a new code path, an inverted condition) could silently violate the guarantee, and the violation would only be discovered when a bill arrived.
- *An environment-level network block on paid-provider domains* — infrastructure-level enforcement, but outside the application's own control and not portable across deployment environments.

**Decision:** the paid-tier client's constructor call (`buildPaidClient()`) has exactly one call site in `LlmClientFactory`, lexically inside the `paid_fallback.allowed` config guard — there is no code path that constructs a paid-tier client when the flag is false, provable by reading the source, not just by testing behavior.

**Consequences:** adding a new call site to `buildPaidClient()` anywhere outside the existing guard would need to be a deliberate, reviewable code change — the guarantee's integrity is enforced by the smallness and singularity of the guarded code path, not by comprehensive testing of every possible call site (though the guarantee is also tested, at both the factory level and, later, end-to-end through `DiscussionService`).

**Future impact:** this is the template for any future "must never happen" requirement in the codebase — prefer making the dangerous code path structurally unreachable over relying on a check that could have a bug.

---

## ADR-004: Structured Output Over Prose Parsing for LLM Responses

**Context:** `DiscussionService` needs to reliably extract a machine-actionable verdict (`continue`/`accept`/`end_unresolved`) from every AI turn.

**Problem:** free-text model output parsed via regex/keyword-matching for a verdict is fragile — a model saying "that's correct!" in a sentence structure a regex doesn't anticipate silently fails to register as an acceptance.

**Alternatives considered:**
- *Regex/keyword parsing of free text* — no extra prompt engineering required, but unreliable and impossible to fully test against every phrasing a model might produce.
- *A second LLM call to classify the first call's output* — reliable in principle, but doubles latency and cost on every single turn.

**Decision:** every provider is asked to return a small, strictly-required JSON payload (`reply_text`, `verdict`, plus optional fields) in one call — via native structured output where supported, via a strict-JSON prompt contract with defensive parsing otherwise — with `StructuredOutputParser` never inferring a missing required field.

**Consequences:** one round trip produces both the student-facing reply and the state-machine verdict; a parse failure has a well-defined, tested safe fallback rather than an undefined one. Cost: extra prompt-engineering discipline, and a documented, currently-unbuilt gap (no live repair-retry round trip on a parse failure — falls back to the safe default immediately instead).

**Future impact:** the repair-retry gap is an explicit, scoped future improvement (see [12-structured-output.md](12-structured-output.md)), not a hidden one.

---

## ADR-005: Discussion Happens Before Diagnosis Submission, Not After

**Context:** the Engineering Discussion could plausibly be attached either before a diagnosis is finalized or as commentary on an already-submitted one.

**Problem:** where in the student's workflow does an AI reviewer add the most value, and does either position risk undermining the feature's purpose?

**Alternatives considered:** attaching discussion **after** submission, as commentary on an already-final answer — considered and explicitly rejected, because it would make the AI a critic of a done deal rather than a participant in reaching the conclusion, which doesn't match the real-world analogue this feature is modeled on (a code review or postmortem happens before a fix is merged, not as a formality after).

**Decision:** the student drafts their position, defends it in discussion, and only the accepted/ended/round-capped result becomes the formally-submitted diagnosis.

**Consequences:** the diagnosis form's pre-fill-from-accepted-discussion mechanism (via `DiscussionAccepted` → `PrefillDiagnosisFromAcceptedDiscussion`) only makes sense in this ordering; discussion outcome is recorded as attempt metadata, never a blocking gate, so ending early or hitting the round cap never traps a student who can't reach acceptance.

**Future impact:** any future discussion "subject" beyond `CaseAttempt` should preserve this before-not-after ordering relative to whatever the analogous "final submission" concept is for that subject, to keep the feature's core value proposition consistent.

---

## ADR-006: Personas as Config Data + Thin Strategy Classes, Not Hardcoded Prompts

**Context:** the design spec's long-term vision names five more personas beyond the two Version 2 ships (Security Review, System Design Interview, Code Review, Architecture Review, DevOps Review).

**Problem:** should each persona be a bespoke, hand-written prompt embedded in `DiscussionService`, or a more structured, addable unit?

**Alternatives considered:** hardcoding each persona's prompt text directly where it's used — fastest to build two personas, but every new persona would mean editing `DiscussionService` itself, violating Open/Closed and making the two existing personas' prompt text harder to review and compare side by side.

**Decision:** a persona is a config record (tone, strictness, hint policy, acceptance bar) resolved through `PersonaResolver`, with a class implementing `AiPersonaInterface` only where actual *behavior* (not just config values) differs — reusing the same Strategy-pattern shape the Evaluation Engine already established.

**Consequences:** `MentorPersona` and `InterviewerPersona` exist as separate classes specifically because `shouldOfferHint()` behaves differently (real stall-threshold logic vs. trivially always false), not because every persona automatically needs its own class.

**Future impact:** adding any of the five named future personas is, in the common case, a new config entry with zero new code — see [27-developer-guide.md](27-developer-guide.md#adding-a-persona).

---

## ADR-007: Discussion Sessions Attach Polymorphically to Their Subject

**Context:** the design spec's stated long-term vision includes discussing subjects other than a `CaseAttempt` (a code submission, a design brief, eventually no platform record at all).

**Problem:** should `discussion_sessions` have a `case_attempt_id` foreign key, since that's the only subject type Version 2 actually ships?

**Alternatives considered:** a direct foreign key to `case_attempts` — simpler schema today, but would require a schema migration (and likely a parallel `discussion_sessions_v2` table, or a nullable-FK mess) the first time a second subject type is built.

**Decision:** `discussion_sessions.discussable_type`/`discussable_id` (Laravel `morphs()`), resolving to `CaseAttempt` for every session Version 2 creates.

**Consequences:** zero cost today (the polymorphism is unused in the sense that only one type exists), in exchange for zero schema change the first time a second subject type is added — only a new `DiscussionSubjectInterface` implementation is needed.

**Future impact:** directly enables ADR-005's before-not-after ordering and ADR-006's persona extensibility to generalize to non-`CaseAttempt` subjects without another database migration.

---

## ADR-008: Synchronous Request/Response for the Engineering Discussion, Not Streaming

**Context:** LLM chat interfaces commonly stream tokens as they're generated; the rest of AI CaseLab is a fully synchronous, non-queued application.

**Problem:** should the Discussion Engine's first version use streaming/async infrastructure to match modern chat-UI expectations?

**Alternatives considered:** building a queued job + Server-Sent Events pipeline for token-by-token streaming from day one — would better match user expectations set by consumer AI chat products, but introduces infrastructure (a queue worker, SSE handling) the rest of the application doesn't have at all, for a feature whose actual per-turn latency (a few seconds, given the hard `max_tokens` cap) doesn't yet demonstrate a real problem synchronous handling can't solve.

**Decision:** one bounded LLM call per turn, handled synchronously within the normal request/response cycle, exactly like every other write path in the application.

**Consequences:** the deployment guide's "no queue worker required" claim remains true for the whole application, not just Version 1; a discussion turn's latency is bounded and predictable but not instant.

**Future impact:** explicitly named as "the first scalability upgrade" if usage grows past comfortable synchronous handling — the `QUEUE_CONNECTION=database` configuration already present is unused headroom reserved for exactly this future change, not evidence of an oversight.

---

## ADR-009: A Formal Token-Based Design System, Introduced Mid-Project Rather Than at Launch

**Context:** by the time the Engineering Discussion UI shipped, the application had roughly a dozen phases of component-level CSS, written with real care but with colors and spacing repeated as literals across files.

**Problem:** should visual consistency continue to be maintained through developer discipline and precedent-following, or formalized into an explicit, enforced token system?

**Alternatives considered:** continuing page-by-page, precedent-based consistency indefinitely — worked reasonably well for a dozen phases, but each new component required a developer to know and correctly recall prior color/spacing choices from memory or by searching existing files, an increasing-cost strategy as the surface area grows.

**Decision:** a formal Design System v1 specification (palette, typography, spacing, radius, shadow, component specs, accessibility, responsive rules) was authored, approved, and is being rolled out as a token layer in `resources/sass/_variables.scss`/`app.scss`, verified milestone-by-milestone to introduce zero visual regression to existing components.

**Consequences:** new components now draw from named tokens by construction, rather than needing a developer to correctly recall or search for a prior literal value; the existing dark "Night" panel language (evidence viewers, discussion panel) was formalized as-is rather than redesigned, preserving continuity with already-shipped, already-approved visual decisions.

**Future impact:** the remaining rollout milestones (cards/nav chrome, empty/loading states, icon system, accessibility/responsive audit — see [19-milestones.md](19-milestones.md)) apply this same token layer incrementally across the rest of the application, rather than as a single large, unreviewable redesign.

---

## ADR-010: Two Vocabularies — Narrative UI Copy vs. Precise Technical Code

**Context:** the product's pedagogical framing (the "Virtual Engineering Office") requires student-facing copy that reads as a workplace, not a course.

**Problem:** should the narrative terminology (Inbox, Assigned Incidents, Engineering Notebook, Performance Review) also rename the underlying code (models, tables, controllers, routes) to match?

**Alternatives considered:** renaming code to match the narrative (e.g., `CaseModel` → `Incident`, `cases` table → `incidents`) — would make the codebase read consistently with the product's fiction throughout, but would break the precise, technical vocabulary future engineers rely on to reason about the system, and would need re-doing every time the narrative copy changes (a UI wording decision, likely to happen more often than a schema decision should).

**Decision:** the narrative vocabulary applies only to the presentation layer — Blade view text, navigation labels, page titles, and (where natural) URL paths — never to models, database tables, controllers, or services, which keep precise technical names regardless of what the UI calls the same concept.

**Consequences:** two vocabularies must be learned by a new engineer (see [39-glossary.md](39-glossary.md) for the mapping), but the code layer stays stable and searchable independent of product-copy iteration, and the product layer stays free to iterate its narrative language without touching application logic.

**Future impact:** this precedent directly shaped how the Engineering Discussion's own terminology was introduced — "Engineering Discussion" is UI copy; `DiscussionSession`/`DiscussionTurn`/`DiscussionService` are the precise underlying code, following the same two-vocabulary rule established four phases earlier.
