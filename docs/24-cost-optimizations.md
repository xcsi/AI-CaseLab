# 24 — Cost Optimizations

> **Related:** [13-provider-abstraction](13-provider-abstraction.md) · [11-prompt-pipeline](11-prompt-pipeline.md) · [03-non-functional-requirements](03-non-functional-requirements.md)
> **Primary source:** `docs/13-ai-discussion-engine-design.md` §12.

AI CaseLab's only variable-cost runtime dependency is the LLM layer behind the Engineering Discussion. Cost optimization here is treated as a first-class architectural concern, not an afterthought — the following levers are listed in the order the design spec itself ranks their impact.

## 1. The Default Configuration Costs Nothing, Structurally

By a wide margin, the single biggest cost lever: the fallback chain tries three independently-free tiers (Ollama, OpenRouter free model, Gemini free tier) before a paid one is even a candidate, and the paid tier **does not exist in the chain at all** unless the operator explicitly enables it. Every other lever below optimizes cost *within* a tier that is already, by design, either free or deliberately opted into. Full mechanism in [13-provider-abstraction.md](13-provider-abstraction.md).

## 2. Prompt Caching Where the Provider Offers It

The largest lever *among the hosted paid options* — the static system prompt (persona + case ground truth) is the majority of every request's input tokens and is identical across every turn in a session and across every student discussing the same case under the same persona. Each hosted provider client applies its own provider's server-side caching mechanism internally where available; `DiscussionService` never knows this is happening. See [11-prompt-pipeline.md](11-prompt-pipeline.md#token-management).

## 3. Hard `max_tokens` Cap on Replies

A cost control and correct persona behavior at once — capped at ≈300 tokens for every persona. A senior engineer challenging you in review sends two sharp sentences and a question, not an essay. Equally relevant on local inference (shorter replies mean lower latency on modest hardware, not just lower dollar cost).

## 4. `max_rounds` Ceiling

Bounds worst-case cost per attempt, computable in advance regardless of provider (`max_rounds × ~1,100 tokens/round`, order of magnitude) — simultaneously a pedagogical control (see [09-discussion-engine.md](09-discussion-engine.md)) and a cost ceiling.

## 5. Structured Output in a Single Call

One round trip produces both the student-facing reply and the state-machine verdict, rather than a separate "generate reply" call plus a second "classify the verdict" call. Doubly important on free-tier/local models, where a parse-failure repair-retry path already risks a second call on a bad turn (see [12-structured-output.md](12-structured-output.md)).

## 6. Per-Persona Model Routing Is Possible but Never Bypasses the Paid-Safety Gate

A persona config *could* carry a preferred tier or model — e.g., a future stricter persona leaning on a stronger model where rigor is the actual point — but this is a preference *within* whichever tiers are actually enabled, never a way around the cost-safety gate. With `LLM_ALLOW_PAID_FALLBACK=false` (the default), every persona gets exactly the same free/local tiers, full stop — persona identity is never a backdoor to paid usage.

## 7. Turn-Level Token *and* Provider Accounting, Persisted From Day One

`discussion_turns.prompt_tokens`/`completion_tokens`/`provider`/`model` are recorded per AI turn, so cost isn't a mystery discovered later — `AnalyticsService` can surface "cost per case," "cost per persona," or "cost per provider" the same way it already surfaces completion rate and hint usage, without new infrastructure. See [29-future-roadmap.md](29-future-roadmap.md) for this as a concrete, low-effort future extension.

## Application-Level Cost Considerations (Version 1)

Outside the AI subsystem, the application has effectively zero variable runtime cost — no third-party paid services, no queue infrastructure to provision, no file storage service in active use (see [25-deployment-guide.md](25-deployment-guide.md)). The one deliberate cost-vs-simplicity trade-off is `AnalyticsService::categoryAggregates()` re-running its per-category queries rather than batching (see [23-performance-optimizations.md](23-performance-optimizations.md)) — a query-count cost accepted in exchange for zero duplicated aggregation logic, judged acceptable at this project's actual category counts.

## Operational Guidance

An operator running purely on Ollama, with every hosted-tier `.env` block left blank, has a fully-supported, zero-marginal-cost configuration — not a degraded one. Enabling any hosted free tier (OpenRouter, Gemini) is also free until that provider's own quota is exhausted, at which point the chain simply falls through to the next tier rather than failing. Enabling the paid tier is a single, deliberate, two-value act (`LLM_ALLOW_PAID_FALLBACK=true` plus naming exactly one provider) and should be treated as an explicit budget decision, not a "just in case" default. See [26-configuration-reference.md](26-configuration-reference.md) and [38-operational-runbook.md](38-operational-runbook.md).
