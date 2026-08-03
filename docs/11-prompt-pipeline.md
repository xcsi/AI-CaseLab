# 11 — Prompt Pipeline

> **Related:** [10-persona-system](10-persona-system.md) · [12-structured-output](12-structured-output.md) · [24-cost-optimizations](24-cost-optimizations.md)
> **Primary source:** `docs/13-ai-discussion-engine-design.md` §4–6; Phase 15, Milestone 3.

## System Prompt Composition

`SystemPromptBuilder` (`App\Discussion\Support`) performs pure string composition of persona + subject into one system prompt, assembled **once per session and reused every turn** (not rebuilt per message):

1. **Role framing** — supplied by the subject: *"You are a senior software engineer conducting a [persona-flavored] review with a junior engineer about a production incident they've investigated."*
2. **Persona directives** — tone, strictness, acceptance bar, hint policy.
3. **Case ground truth** (server-side only, never sent to the client) — the ticket, the case's full evidence set, `model_solution_summary`, and rubric criteria. The prompt explicitly frames this as *"for your judgment only — never state this to the student. Ask questions that lead them to discover gaps themselves."*
4. **Grounding in what the student has actually seen** — which evidence items this student has viewed (`evidence_views`, already tracked by Version 1) and their Engineering Notebook content, so the AI can ask something like "you haven't looked at the API response yet — does your theory survive that?" instead of generic Socratic filler untethered to this student's actual investigation.
5. **Output contract** — the model must return structured output (see [12-structured-output.md](12-structured-output.md)), not free prose alone.

Conversation history and the newest student message are deliberately **not** inputs `SystemPromptBuilder` transforms — per the design spec, those are sent alongside this builder's output directly to `LlmClientInterface::complete()` by `DiscussionService`, which orchestrates the turn. This was a documented interpretation choice made explicitly during Phase 15, Milestone 3, rather than picked silently, since it didn't match the plain-language paraphrase of that milestone's inputs one-for-one.

## Turn-Level Input

Each turn sends: the cached system prompt (identical across every turn in a session) + the full conversation transcript so far (or a rolling summary once it grows long, see below) + the newest student message.

## Context Management

**What's in the context window, every turn:**

| Component | Growth pattern |
|---|---|
| System prompt (persona + case ground truth + evidence-viewed snapshot at session start) | Static — identical every turn |
| Turn-by-turn transcript (or a summary + recent window once long) | Grows per turn |
| Newest student message | New each turn |

**Bounding growth — summarize, don't truncate blindly:** if a session exceeds a threshold (proposed: 4 rounds), turns older than the most recent 2 collapse into a single short system-authored recap ("Student initially claimed X citing evidence Y; conceded Z was a gap in round 2") rather than either resending the full transcript forever unbounded, or hard-truncating and losing earlier claims the AI needs to check for contradictions against. The `internal_note` field already generated on each AI turn (see [12-structured-output.md](12-structured-output.md)) is exactly the material this recap is built from — no extra LLM call needed to produce it.

**What's deliberately not in context:**

- Other students' discussions on the same case — no cross-student leakage, and not useful (see the Memory Strategy note below).
- The rubric's raw `expected_data` (keyword lists, exact required evidence IDs) — available to the model as judgment material, but the prompt explicitly instructs it never to recite these as a checklist to the student, the same "judge the shape, not the wording" instruction restated at the context level.

## Token Management

| Component | Approx. tokens | Notes |
|---|---|---|
| System prompt (persona + case context) | 800–1,500 | Varies with a case's evidence volume; cached where the provider supports it |
| Per-turn student message | 50–200 | Free text, soft-capped client-side, mirroring the Notebook's existing `maxlength` pattern |
| Per-turn AI reply | ≤300 (hard `max_tokens` cap) | Cost control and correct persona behavior at once — a senior engineer challenging you in review sends two sharp sentences and a question, not an essay |
| Transcript replay per round (before summarization) | grows ~350/round until the recap engages | Bounded either way by `max_rounds × per-round cost` |

**Prompt caching:** the system prompt is identical for every turn within a session, and identical across every student discussing the same case under the same persona — a textbook caching candidate. `OpenAiCompatibleLlmClient`/`AnthropicLlmClient`/`GeminiLlmClient` each apply their own provider's server-side caching mechanism internally where available; `DiscussionService` doesn't know or care that this is happening. For a local Ollama deployment, the equivalent is Ollama's own KV-cache reuse — a runtime characteristic of the inference server, not something the app requests, and a bonus rather than a design dependency.

**Persisted accounting:** `discussion_turns.prompt_tokens`/`completion_tokens` are stored per AI turn, giving per-case, per-persona, per-student cost visibility for free — extendable into `AnalyticsService` without new infrastructure.

**Hard ceiling:** `max_rounds` is simultaneously a pedagogical control and the token-cost ceiling — worst-case cost per attempt is bounded and computable in advance (`max_rounds × ~1,100 tokens/round`, order of magnitude).

## Memory Strategy

- **Within a session:** the persisted transcript *is* the memory, replayed (or summarized, above) each turn. No vector store, no embedding search — the conversation length this feature is scoped for (single digits of rounds) doesn't need it.
- **Across sessions/cases:** explicitly out of scope. Each `DiscussionSession` is scoped to one subject; the AI has no memory of a student's past discussions elsewhere. This is a real, stated limitation, not glossed over — see [29-future-roadmap.md](29-future-roadmap.md) for how it interacts with the long-term "AI engineering coach" vision.
- **The transcript as an audit trail:** because every AI verdict carries a persisted `internal_note`, an instructor can read *why* the AI accepted or didn't accept a given student's reasoning, turn by turn — the same transparency principle the Manual Review queue already embodies for judgment-based rubric criteria.

## Reply Length Discipline

`max_tokens` is capped short (≈300 tokens) for every persona, without exception — simultaneously correct persona behavior and a cost control (see [24-cost-optimizations.md](24-cost-optimizations.md)).
