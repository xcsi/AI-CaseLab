# 12 — Structured Output

> **Related:** [11-prompt-pipeline](11-prompt-pipeline.md) · [13-provider-abstraction](13-provider-abstraction.md) · [14-state-machine](14-state-machine.md) · [22-bug-history](22-bug-history.md)
> **Primary source:** `docs/13-ai-discussion-engine-design.md` §4.3, §1.4.6; Phase 14 Milestone 6 (delivered as a catch-up during Phase 15).

## Structured Output Over Prose Parsing

The model is asked to return a small JSON payload on every turn — via native tool-calling/JSON-schema output where the configured model supports it (`config('llm.*.supports_structured_output')`), or via a strict-JSON prompt contract with defensive parsing where it doesn't — never free text alone that gets regex-parsed. This is the difference between a reliable state machine and a chatbot that occasionally says "that's correct!" in a sentence a regex fails to catch.

| Field | Purpose | Persisted? |
|---|---|---|
| `reply_text` | What the student sees | Yes — `discussion_turns.content` |
| `verdict` | `continue` \| `accept` \| `end_unresolved` | Yes — `discussion_turns.verdict` |
| `evidence_gap_detected` | Did the student skip evidence relevant to their claim? | No — informs `internal_note` generation only |
| `contradiction_detected` | Does this reply conflict with an earlier student statement? | No — informs `internal_note` generation only |
| `internal_note` | Short, instructor-only rationale for the verdict, shown on Performance Review, never mid-discussion | Yes — `discussion_turns.internal_note` |

`reply_text` and `verdict` are **strictly required**; `evidence_gap_detected`/`contradiction_detected` inform how the AI's own reasoning gets summarized into `internal_note` but were deliberately not given their own database columns — read as intentional (per §10.1's field list and the `discussion_turns` schema), not a contradiction requiring a design change.

## The Parsing Pipeline

Three classes, each with one job, `App\Discussion\Infrastructure\Llm\Support` and `App\Discussion\Support`:

1. **`StructuredOutputParser`** — validates and normalizes. `reply_text` and `verdict` are strictly required; missing or invalid throws `StructuredOutputParseException` — **no defaulting, no inference.** Optional fields normalize to sensible types/defaults (normalizing an *optional* field is a different, narrower thing than inferring a *required* one that's actually absent).
2. **`ParsedStructuredOutput`** — the validated result type. By construction, `reply_text`/`verdict` are guaranteed present and well-formed by the time an instance exists.
3. **`TurnClassifier`** — extracts a `DiscussionVerdict` from an already-validated `ParsedStructuredOutput`. A direct, mechanical read (`DiscussionVerdict::from()`), not a decision — `StructuredOutputParser` already guaranteed validity, so there is nothing left to infer.

Each of the three provider clients (`OpenAiCompatibleLlmClient`, `AnthropicLlmClient`, `GeminiLlmClient`) only extracts the raw text from its own response envelope (OpenAI-style `choices[0].message.content`, Anthropic's `content[0].text`, Gemini's `candidates[0].content.parts[0].text` — necessarily provider-specific, that part can't move) and delegates everything else to the shared parser/classifier. This replaced three separate provisional `parseHappyPath()` methods that existed briefly during Phase 14, Milestones 2–3, before the shared parser was built.

## Failure-Mode Handling: The Safe Fallback

If a model's structured output fails to parse, each provider client applies the same documented safe fallback, independently:

```
verdict     = Continue
reply_text  = the raw model output (as returned, unparsed)
internal_note = flags that a default was applied
```

This fallback-construction step is **intentionally duplicated across the three clients** rather than pushed into `StructuredOutputParser` — a defaulted verdict is exactly the kind of inference that class must never perform, so the decision to *apply* a default belongs one layer up, at the call site that already knows it has exhausted its options.

**Known, documented gap:** the live repair-retry round trip described in the design spec (re-asking the model once on a parse failure before falling back) is not built — clients fall back to the safe default on the first parse failure rather than retrying over the network first. Flagged explicitly as a real, addressable gap left for a future, explicitly-scoped pass rather than expanding the original catch-up milestone further.

## Case Study: The Empty-Reply Bug

A real production bug, fixed 2026-08-02, is the clearest illustration of why the safe-fallback path matters and why it needed a second fix later. Manual testing of the Engineering Discussion found the AI reply bubble rendering as visibly empty.

- **Investigation:** confirmed against real database data, the exact fallback code path, and a live raw request/response capture that the frontend was rendering correctly — the server was persisting and returning a genuinely empty `reply_text`.
- **Root cause:** each provider client's parse-failure fallback sets `reply_text` to the raw model output. In real use, a reasoning-style model (OpenRouter's `nvidia/nemotron-nano-9b-v2:free`) spent its entire `max_tokens` budget on hidden reasoning tokens before ever writing visible content, leaving the raw output empty — a real model-architecture interaction, not a parsing defect.
- **Fix:** an `EMPTY_REPLY_PLACEHOLDER` constant added to all three provider clients. Only the `reply_text` line in each existing parse-failure fallback changed: a truly empty (post-`trim()`) raw reply becomes *"The AI's reply couldn't be read this round."* instead of `""`. A non-empty raw reply (even malformed JSON) is unaffected; `verdict`, `internal_note`, and every other part of the existing fallback are unchanged.
- **Regression protection:** a truly-empty-reply test plus a whitespace-only test (proving the check is a `trim()`-based emptiness check, not a naive `=== ''` check) added per client; the pre-existing non-empty "unparseable reply" test was re-run unmodified and still passes, confirming the non-empty path was untouched.
- **Follow-up:** an isolated experiment with `qwen2.5:7b` (a non-reasoning chat model) against the *unmodified* implementation confirmed the empty-content behavior is specific to reasoning-model architecture, not a general Ollama-integration defect — which is why the local Ollama default model was separately updated to a non-reasoning model, tracked outside this commit as a gitignored `.env` change.

Full write-up in [21-problems-and-solutions.md](21-problems-and-solutions.md) and [22-bug-history.md](22-bug-history.md).

## Testing

`StructuredOutputParserTest` covers every required-field failure mode individually, including a not-recognized verdict value — "the specific case a lenient parser would be tempted to default," proven it doesn't. `TurnClassifierTest` covers one test per `DiscussionVerdict` case. An integration test on `OpenAiCompatibleLlmClientTest` proves the full HTTP → parser → classifier → `LlmTurnResult` path degrades gracefully on an unparseable reply rather than throwing.
