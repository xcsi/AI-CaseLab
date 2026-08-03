# 10 — Persona System

> **Related:** [08-ai-architecture](08-ai-architecture.md) · [09-discussion-engine](09-discussion-engine.md) · [11-prompt-pipeline](11-prompt-pipeline.md)
> **Primary source:** `docs/13-ai-discussion-engine-design.md` §1.5, §3; Phase 15, Milestone 1.

## Personas Are Data, Not Hardcoded Chatbots

A persona is a config record (tone, strictness, hint policy, acceptance bar, round defaults) resolved through the same Strategy-pattern shape the Evaluation Engine already established for rubric scoring — not two bespoke prompts written by hand. `config/discussion_personas.php` is the single source of truth for persona data; `MentorPersona`/`InterviewerPersona` are thin classes implementing `AiPersonaInterface`, and `PersonaResolver` (mirroring `EvaluationStrategyResolver`'s exact shape) resolves a config key to a concrete persona instance.

## Why Two Classes, Not One Config-Driven Class

`docs/13-ai-discussion-engine-design.md` §1.1's folder tree specifically calls for `MentorPersona`/`InterviewerPersona` as separate classes, because `shouldOfferHint()`'s *behavior* genuinely differs between them, not just its config values:

- **`MentorPersona`** implements real stall-threshold logic — after a student has been stuck (no progress in reasoning) for a configured number of rounds, the Mentor persona offers a hint.
- **`InterviewerPersona`**'s `shouldOfferHint()` is trivially always `false` — which is itself the correct implementation of "Never" from the persona comparison table, not a stub or an oversight.

`systemPromptFragment()` and `acceptanceBar()` are built from config, not hardcoded strings, so the actual tone/bar data lives in `config/discussion_personas.php` where it belongs, editable without touching either class.

## Persona Comparison

| Field | Mentor | Interviewer |
|---|---|---|
| Tone | Supportive, coaching | Rigorous, evaluative |
| Hint policy | Offers a hint after a stall threshold | Never offers a hint |
| Acceptance bar | More forgiving of imperfect wording, focused on directional correctness | Stricter — expects precise, evidence-grounded reasoning |
| Default max rounds | 8 | 5 |
| Intended feel | A senior engineer coaching a junior through their first incident | A technical interview panel probing a candidate's reasoning |

## `AiPersonaInterface` Contract

Per `docs/13` §3.1, every persona implements exactly three methods:

| Method | Purpose |
|---|---|
| `systemPromptFragment()` | The persona-specific portion of the system prompt (tone directives, strictness, hint policy framing) |
| `shouldOfferHint(DiscussionSession $session)` | Whether this persona offers a hint at the current point in the conversation |
| `acceptanceBar()` | The persona-specific instruction for how strict the AI's acceptance judgment should be |

## Extensibility: The Subject × Persona Split

Personas are one of two independent extensibility axes described in `docs/13` §1.5 (the other being *subject* — what's being discussed, see [08-ai-architecture.md](08-ai-architecture.md#module-boundary)). Because a persona only ever answers "who is reviewing and how strict are they," and a subject only ever answers "what ground truth and progress context exists," `SystemPromptBuilder`'s own tests prove the two compose independently: swapping the persona (Mentor ↔ Interviewer) changes tone-specific content while the ground truth (a case's model solution) is byte-identical either way; swapping the subject (two different cases) changes the ground truth while the persona's directives are identical either way. This is what makes future personas — Security Review, System Design Interview, Code Review, Architecture Review, DevOps Review (see [29-future-roadmap.md](29-future-roadmap.md)) — additive: new config plus, at most, a small strategy class, with zero changes to `DiscussionService`.

## Validation

`persona` is a plain, validated `string` column on both `discussion_sessions` and `cases.discussion_default_persona` — not a database enum — because the set of personas is expected to grow in a way the core state-machine enums (`DiscussionStatus`, `DiscussionTurnRole`, `DiscussionVerdict`) are not. `StartDiscussionRequest`/`RespondToDiscussionRequest` validate the submitted persona against `Rule::in(array_keys(config('discussion_personas')))` — the *live* config, not a hardcoded list — so a persona added later needs no Form Request change. `UnknownPersonaException` is thrown by `PersonaResolver` for a key that somehow bypasses that validation.

## Testing Notes

Persona logic has no LLM call in it at all — `MentorPersona`'s hint-stall logic is unit-tested directly at round counts 2 (no), 3 (yes, exactly at threshold), and 7 (still yes); `InterviewerPersona`'s "never" is proven at both round_count 0 and 100, so it is a verified behavior rather than one coincidental test case. `PersonaResolver` is proven to resolve both personas through the real container, not assumed.
