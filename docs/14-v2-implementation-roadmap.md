# AI CaseLab — Version 2 Implementation Roadmap

Sequences the frozen architecture in `docs/13-ai-discussion-engine-design.md` into build phases, the same discipline `docs/07-implementation-roadmap.md` applied to Version 1: **each phase ends with something demoable and requires confirmation before the next begins.** Phase numbering continues from Version 1 (Phases 1–12) rather than restarting, so the repo's history reads as one continuous project rather than two unrelated ones.

**Binding rules for every phase below, per direct instruction:**

- Version 1 is preserved completely — no phase modifies a Version 1 file beyond the touchpoints `docs/13` itself designates: the additive `cases` column set (§9.3 — `discussion_enabled`/`discussion_default_persona`/`discussion_max_rounds`) and the three explicitly-designed UI integration points (§11.1–§11.3 — Investigation Workspace, Performance Review, Admin Case Editor). Any phase that seems to need more than that is a signal to stop and revisit `docs/13`, not to proceed.
- Commits stay small and reviewable — one milestone below is one commit (or a small handful of tightly related commits), not one phase in a single commit.
- The full automated test suite stays green after every milestone, not just every phase. A milestone that leaves tests red is not done.
- One feature at a time — a milestone does not bundle unrelated work because it happened to be convenient to touch the same file.
- No unnecessary complexity — if a milestone's implementation needs something `docs/13` didn't specify (a new abstraction, a new pattern, a new dependency), that's a real implementation issue worth pausing on per the standing rule ("a real implementation issue discovered while building can prompt a revision") — not a reason to quietly add it.

Every phase cites the `docs/13` sections it implements, so implementation traces back to the frozen spec, not to memory of the conversation that produced it.

---

## Phase 13 — Discussion Engine Foundations (Schema, Models, Contracts)

**Objective:** the database and domain foundation the whole engine sits on, provably wired, before any AI logic exists — mirrors how Version 1's Phase 3 built the full schema before any UI touched it.

**Implements:** `docs/13` §1.1 (module shape), §1.5 (subject/persona contracts), §9 (database).

**Milestones:**
1. Migrations: `discussion_sessions` (polymorphic `discussable_type`/`discussable_id`, `persona` as a validated string — not an enum, per §9.1's explicit reasoning — `status`, `round_count`, `max_rounds`, `started_at`/`ended_at`, `outcome_summary`) and `discussion_turns` (`discussion_session_id`, `sequence_order`, `role`, `content`, `verdict`, `internal_note`, `evidence_referenced`, `prompt_tokens`/`completion_tokens`, `provider`/`model`, `fallback_log`).
2. Migration: `cases.discussion_enabled` / `discussion_default_persona` / `discussion_max_rounds` — the one Version-1-table touchpoint, additive and nullable/defaulted so every existing row is unaffected.
3. Enums: `DiscussionStatus`, `DiscussionTurnRole`, `DiscussionVerdict` (`app/Enums`) — only the three that §9.2 specifies as genuinely stable; `persona` is deliberately not among them.
4. Models: `DiscussionSession` (`morphTo('discussable')`, `hasMany` turns) and `DiscussionTurn` (`belongsTo` session), plus factories.
5. Empty contracts only, no implementations: `LlmClientInterface`, `AiPersonaInterface`, `DiscussionSubjectInterface` (`app/Discussion/Contracts`) — method signatures per §1.3/§1.5, bodies come in later phases.
6. A wiring test mirroring Phase 3's `DomainGraphWiringTest`: build a `DiscussionSession` attached to a real seeded `CaseAttempt` via the polymorphic relation, with several `DiscussionTurn`s, assert every relationship resolves both directions.

**Dependencies:** none beyond existing Version 1 schema (`CaseAttempt`).

**Testing:** the wiring test (milestone 6) plus standard model/factory tests. Full suite re-run at phase close — still 100% green, count unchanged except for this phase's additions.

**Expected Deliverables:** `php artisan migrate:fresh --seed` runs clean with the new tables; the wiring test passes; no Version 1 test's behavior changed.

**Estimated Time:** 2–3 days.

---

## Phase 14 — Provider-Agnostic LLM Client Layer

**Objective:** build and prove the cost-safe fallback chain in complete isolation from discussion logic. This is the highest-novelty infrastructure in Version 2 — external HTTP, multiple wire formats, a safety-critical invariant — so it is isolated as its own phase precisely so a bug here is never confused with a bug in the (much simpler) discussion state machine built afterward.

**Implements:** `docs/13` §1.4 in full.

**Milestones:**
1. `config/llm.php` per §1.4.7's shape (per-tier settings, all independently optional except tier 1); `FakeLlmClient` implemented and bound in the testing environment — this is what keeps every later phase's tests network-free.
2. `OpenAiCompatibleLlmClient` (serves `openai`/`openrouter`/`ollama` via a `base_url` parameter) — unit-tested against `Http::fake()` for success, HTTP 429, and timeout responses. No real network call anywhere in this milestone's tests.
3. `GeminiLlmClient` and `AnthropicLlmClient` — same treatment, same `Http::fake()` discipline.
4. Typed exceptions (`LlmProviderUnavailableException`, and the distinct "genuine request error" path) and `ChainedLlmClient`, implementing §1.4.2–§1.4.3's ordered-try/typed-exception logic.
5. `LlmClientFactory`, assembling the chain from config and enforcing §1.4.4's paid-tier gate. **This milestone's test is the single most important test in Version 2:** assert the container never constructs a paid-tier client, and `ChainedLlmClient`'s candidate array never contains one, when `LLM_ALLOW_PAID_FALLBACK=false` — even with valid-looking paid API keys present in config. This is the literal "never silently spend money" guarantee, made executable.
6. `StructuredOutputParser` — native-mechanism path and strict-JSON-prompt-plus-repair-retry fallback path, per §1.4.6.

**Dependencies:** Phase 13 (the `LlmClientInterface` contract exists).

**Testing:** every milestone above is provable with `Http::fake()` and zero real network calls — including the full chain-fallthrough scenario (tier 1 unreachable → tier 2 rate-limited → tier 3 succeeds) and the exhausted-chain scenario (`NoLlmProviderAvailableException`). If a local Ollama instance happens to be available on the development machine, a manual (not automated, not required to pass CI) smoke check against it is worthwhile once, to sanity-check the real wire format — documented as manual, not folded into the automated suite.

**Expected Deliverables:** the entire fallback chain's behavior — ordering, typed-failure handling, and the paid-tier gate — is provable without spending money or needing any real provider account. Full suite green, still network-free.

**Estimated Time:** 4–5 days (the largest phase in Version 2).

---

## Phase 15 — Personas & System Prompt Construction

**Objective:** build the "who/how" axis and the prompt-assembly pipeline, fully testable without a live model.

**Implements:** `docs/13` §1.5 (`DiscussionSubjectInterface`), §3 (personas), §4.1–§4.3 (prompt composition, structured output fields).

**Milestones:**
1. `config/discussion_personas.php` (Mentor + Interviewer, per §3.1's field table) and `MentorPersona`/`InterviewerPersona` implementing `AiPersonaInterface`; `PersonaResolver`.
2. `CaseAttemptDiscussionSubject` implementing `DiscussionSubjectInterface` — `framingText()`/`groundTruthContext()`/`progressContext()` reading from existing, unmodified `CaseAttempt`/`CaseModel`/`EvidenceItem`/`RubricCriterion` models.
3. `SystemPromptBuilder`, assembling persona + subject + turn history into the request payload — tested by asserting the assembled prompt's shape/contents against fixtures, not a live call.
4. `TurnClassifier` — parses `verdict`/`evidence_gap_detected`/`contradiction_detected`/`internal_note` from a fixture structured response (both the native-shape and fallback-JSON-shape fixtures).
5. `LeakageGuard` — unit tests asserting it catches verbatim and near-verbatim `model_solution_summary` text in sample reply fixtures, and passes clean replies through unchanged.

**Dependencies:** Phase 13 (contracts, models); Phase 14 (the response shape `TurnClassifier` parses is defined by `LlmClientInterface`/`LlmTurnResult`).

**Testing:** entirely fixture-driven — a given `CaseAttempt` + persona produces an expected prompt shape; a given fixture reply produces an expected classification or leakage verdict. No live LLM call anywhere in this phase.

**Expected Deliverables:** the full prompt-construction and response-parsing pipeline is provably correct against fixtures before it's ever pointed at a real chain.

**Estimated Time:** 3 days.

---

## Phase 16 — DiscussionService & State Machine

**Objective:** orchestrate a complete discussion turn end-to-end against `FakeLlmClient` — the core product logic, still headless (no HTTP, no UI).

**Implements:** `docs/13` §2 (state machine), §1.5's event-driven side-effect pattern.

**Milestones:**
1. `DiscussionService::start()` / `respond()` / `end()` — round counting, and the four-state transition (`Active` → `Accepted` / `EndedByStudent` / `MaxRoundsReached`) per §2.1.
2. `DiscussionAccepted` event, fired on the `Accepted` transition — subject-agnostic, per §1.5.
3. `PrefillDiagnosisFromAcceptedDiscussion` listener — the one piece of `CaseAttempt`-specific behavior, deliberately kept outside `DiscussionService` itself.
4. `DiscussionSessionPolicy` (`view`, `participate`), mirroring `CaseAttemptPolicy`'s ownership-check shape.
5. Feature tests driving complete scripted sessions purely through the service layer against `FakeLlmClient` — start→accept, start→end-by-student, start→max-rounds-reached — asserting the correct terminal state, round count, and (for the accept path) that the event fired and the diagnosis was pre-filled.

**Dependencies:** Phases 13–15.

**Testing:** service-layer feature tests only at this phase — no route/controller exists yet. Every scripted `FakeLlmClient` response sequence from milestone 5 is a small, fast, deterministic test.

**Expected Deliverables:** a complete discussion session, start to finish, provably correct through the service layer alone.

**Estimated Time:** 3–4 days.

---

## Phase 17 — HTTP Layer: Routes, Controllers, Requests

**Objective:** expose `DiscussionService` over HTTP, matching this codebase's existing route/controller/policy conventions exactly.

**Implements:** `docs/13` §10.

**Milestones:**
1. Routes per §10's table, `DiscussionController`, Form Requests (student-message length validation, etc.), middleware wiring (`auth`, `EnsureAttemptBelongsToUser`, `DiscussionSessionPolicy`).
2. Feature tests hitting the real routes with `FakeLlmClient` bound — a full student journey through raw HTTP: start, message, message, accept, verifying response/redirect shape at each step.
3. Authorization tests: guest redirected, a different student blocked from another student's session — mirroring the Phase 12 authorization-audit pattern (including the guest-coverage gap that audit specifically found and fixed in Version 1, so it isn't quietly reintroduced here).
4. Rate limiting on the messages endpoint, per §8's cost-abuse mitigation.

**Dependencies:** Phase 16.

**Testing:** HTTP-level feature tests for the full journey plus explicit negative/authorization tests. Still zero real network calls — `FakeLlmClient` throughout.

**Expected Deliverables:** a student can drive a complete discussion purely via HTTP requests, proven by automated tests, with ownership/authorization enforced and proven the same way.

**Estimated Time:** 2–3 days.

---

## Phase 18 — Investigation Workspace UI

**Objective:** the actual "Engineering Discussion" panel students interact with.

**Implements:** `docs/13` §11.1, §11.5.

**Milestones:**
1. Entry point in the workspace top bar ("Start Engineering Discussion"), gated on `case.discussion_enabled`.
2. Chat-style panel (dark-panel visual language per §11.1), fetch-driven JS mirroring the existing Notebook-autosave/Hint-unlock patterns; round counter; "AI is thinking…" state.
3. "End Discussion" confirmation flow, mirroring the existing exit-confirmation modal; the accepted→diagnosis-prefill transition moment.
4. "AI Discussion Unavailable" state (§11.5) for the chain-exhausted case.

**Dependencies:** Phase 17.

**Testing:** this is UI work — per this project's standing rule, a manual smoke test in a real browser is required and automated tests alone are not a substitute. Walk a complete Mentor session and a complete Interviewer session in-browser (against `FakeLlmClient` in the local environment, or a real local Ollama instance if available) before considering this phase done; note explicitly in the phase close-out whether the check was automated, manual, or both, the same discipline the Version 1 Phase 12 smoke test used.

**Expected Deliverables:** a working, demoable "Engineering Discussion" panel for both personas, manually verified end to end.

**Estimated Time:** 3–4 days.

---

## Phase 19 — Performance Review Integration & Admin Configuration

**Objective:** close the loop — instructors/admins can configure discussion on a case, and a completed discussion is visible after the fact.

**Implements:** `docs/13` §11.2, §11.3.

**Milestones:**
1. "Engineering Discussion" section on Performance Review (transcript, outcome, round count, persona used) — placed alongside, not inside, the existing rubric breakdown.
2. Admin case editor fields: `discussion_enabled` toggle, default persona, max-rounds override, grouped with the existing Publish-workflow controls.
3. Feature tests for both: Performance Review renders correctly for each outcome type (accepted / ended-by-student / max-rounds-reached / no discussion at all); admin form validation tests for the new fields.

**Dependencies:** Phase 18 (a real completed session to display).

**Testing:** automated feature tests for both surfaces; a brief manual check that the admin toggle actually gates the workspace entry point end to end.

**Expected Deliverables:** an admin can enable and configure discussion on a case; a student's completed discussion is visible and readable on Performance Review.

**Estimated Time:** 2 days.

---

## Phase 20 — Cost-Safety & Observability Hardening

**Objective:** prove the "never silently spend money" and auditability guarantees under more realistic, multi-tier-failure conditions, and close remaining observability gaps before real providers are involved.

**Implements:** `docs/13` §1.4.5, §9.1's `fallback_log`, §1.4.4 (extended end-to-end).

**Milestones:**
1. `fallback_log` persistence wired end-to-end — a `FakeLlmClient` scripted to fail tier 1 and succeed tier 2 produces a correctly populated log, verified through the full `DiscussionService` path (not just the factory in isolation, which Phase 14 already covered).
2. Structured application logging on full chain exhaustion (§1.4.5), for operational visibility.
3. An end-to-end regression test — through `DiscussionService`, not just `LlmClientFactory` in isolation — asserting a paid-tier client is never reached when `LLM_ALLOW_PAID_FALLBACK=false`, extending Phase 14's factory-level test to the full request path.

**Dependencies:** Phase 14 (chain), Phase 16 (service).

**Testing:** this entire phase *is* a testing phase — its deliverable is additional regression coverage, not new user-facing behavior.

**Expected Deliverables:** the cost-safety invariant is covered by a test that would fail loudly if a future change ever violated it — the test that protects "never silently spend money" for the life of the project, not just at launch.

**Estimated Time:** 1–2 days.

---

## Phase 21 — Provider Behavioral Conformance Validation

**Objective:** build the §15.6 harness and run it for real, for the first time, against live providers — the one phase that necessarily touches real infrastructure and real (if free) API calls, deliberately sequenced last among the build phases so it validates behavior on top of plumbing that's already proven correct, rather than debugging both at once.

**Implements:** `docs/13` §15 in full.

**Milestones:**
1. Implement the golden-transcript harness (§15.4) as a separate, manually-invoked Artisan command — explicitly not part of the standard automated test suite, per §15.6.
2. Run it against a real local Ollama model (e.g. `qwen2.5:7b`); record pass/fail per §15.6's criteria for every golden transcript.
3. Run it against OpenRouter's free tier and Gemini's free tier; record results the same way.
4. Document results (which providers/models validated, when, against which contract version) — this is where reality determines which tiers actually ship as `.env.example` defaults, per §15.6's "not added to shipped config unless it passes" rule. If a tier fails conformance for a specific persona (most plausibly: a small model failing Interviewer's stricter bar), narrow its documented use rather than loosening the contract, per §15.7.

**Dependencies:** Phases 13–20 fully working end-to-end — this phase validates behavior, not plumbing, and plumbing must already be proven correct first.

**Testing:** this phase's "testing" is the conformance suite itself, run manually against real providers — not added to CI, per §15.6's explicit separation from the fast automated suite.

**Expected Deliverables:** a real, evidence-based answer to "does switching providers change the educational behavior" — not a theoretical claim.

**Estimated Time:** 2–3 days of engineering time (plus real-world calendar time if a free-tier model needs troubleshooting or a wait on rate limits).

---

## Phase 22 — Documentation, Deployment Update & Release

**Objective:** close Version 2 the same way Version 1's Phase 12 did — verified, documented, ready to demo.

**Milestones:**
1. Update `docs/12-deployment-guide.md` with LLM provider setup (the `.env.example` block from §1.4.7), an Ollama installation note, and a pointer to Phase 21's conformance results.
2. `CHANGELOG.md` entry summarizing Version 2 (mirroring the "Roadmap Phase N, Milestone M" entry style already established); `README.md` status/roadmap table update.
3. Full manual smoke test of the complete Version 2 feature end to end (mirroring the Phase 12 smoke-test discipline exactly), plus a full automated-suite run.
4. Final review: confirm the full test suite is still 100% green, and confirm no Version 1 file was modified beyond the touchpoints `docs/13` itself designates (§9.3's additive `cases` columns and §11.1–§11.3's UI integration points) — the literal check that "preserve Version 1 completely" held for the whole of Version 2, not just in intent.

**Dependencies:** Phase 21.

**Testing:** full automated suite green + full manual smoke test, exactly mirroring Phase 12's close-out checklist.

**Expected Deliverables:** Version 2 shippable, documented, demoable, with Version 1 provably untouched.

**Estimated Time:** 2 days.

---

## Summary Table

| Phase | Name | Depends On | Est. Time |
|---|---|---|---|
| 13 | Discussion Engine Foundations (Schema, Models, Contracts) | Version 1 complete | 2–3 days |
| 14 | Provider-Agnostic LLM Client Layer | 13 | 4–5 days |
| 15 | Personas & System Prompt Construction | 13, 14 | 3 days |
| 16 | DiscussionService & State Machine | 13, 14, 15 | 3–4 days |
| 17 | HTTP Layer: Routes, Controllers, Requests | 16 | 2–3 days |
| 18 | Investigation Workspace UI | 17 | 3–4 days |
| 19 | Performance Review Integration & Admin Configuration | 18 | 2 days |
| 20 | Cost-Safety & Observability Hardening | 14, 16 | 1–2 days |
| 21 | Provider Behavioral Conformance Validation | 13–20 | 2–3 days |
| 22 | Documentation, Deployment Update & Release | 21 | 2 days |

**Total: ~24–30 working days.** Roughly two-thirds of Version 1's total scope, consistent with Version 2 being one deep feature rather than a whole platform — group by phase, not by day count, and don't start phase *N+1* until phase *N*'s automated suite is green and its deliverable actually works, the same rule Version 1 was built under.
