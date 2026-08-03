# 29 — Future Roadmap

> **Related:** [08-ai-architecture](08-ai-architecture.md) · [28-maintenance-guide](28-maintenance-guide.md) · [02-functional-requirements](02-functional-requirements.md)
> **Primary source:** `docs/13-ai-discussion-engine-design.md` §13–14 for the AI subsystem's roadmap; known gaps documented elsewhere in this package for the rest of the platform.

## Near-Term, Low-Effort Extensions

These are extension points the architecture was explicitly built to support without a redesign — see [28-maintenance-guide.md](28-maintenance-guide.md#safe-extension-points) for the mechanism behind each:

- **Evidence-item authoring UI.** Currently evidence exists only via seeding/factory/tinker. The polymorphic-shaped `evidence_items` schema (see [07-database-design.md](07-database-design.md)) was specifically designed so this is "an admin CRUD screen," not a schema change.
- **User management UI.** `/admin/users` is currently a placeholder screen — genuine admin CRUD for managing accounts/roles is a natural, contained next step.
- **In-app notifications.** FR19 (notify when an evaluation is ready) was never implemented, in part because evaluation is currently synchronous — becomes more relevant once/if evaluation or the Discussion Engine moves to an async model.
- **Discussion Engine cost/usage analytics.** `discussion_turns` already persists per-turn token counts and provider/model — a new `AnalyticsService` method surfacing "cost per case/persona/provider" needs no new schema, only new aggregation code, the same shape as every existing analytics metric.

## AI Subsystem — Planned Extensibility (from the frozen design spec)

- **Async/streaming as the first real scalability upgrade.** If usage grows past comfortable synchronous request/response, the natural next step is a queued job + Server-Sent Events for token-by-token streaming replies — the deployment's existing `QUEUE_CONNECTION=database` headroom (currently unused) becomes load-bearing for the first time. Explicitly not needed at launch.
- **Personas move from a config file to database-backed, admin-authorable content** — the same evolution the Rubric Builder already represents for rubric criteria, turning "add a persona" from a code change into a content-authoring task. The current config-object shape was deliberately chosen so this migration is additive (the config file becomes seed data for a new table), not a rewrite.
- **A fifth or sixth free/cheap provider tier.** Because `OpenAiCompatibleLlmClient` already covers any OpenAI-wire-compatible endpoint, most new hosted providers with a free tier plug in as an additional configured tier, not new code — only a genuinely different wire protocol needs a new concrete class, the bar `GeminiLlmClient` cleared.
- **Owner-configurable tier order.** The fixed order is a deliberate constraint today, not a technical limitation — revisit only with an actual, considered reason to reorder.
- **Smarter tier selection than "first available."** A later refinement could weigh recent quality/latency per tier — deliberately not built now, since it would reintroduce health-scoring complexity the ordered chain currently avoids while already satisfying the actual requirement (cost safety).
- **Cross-case discussion analytics** (which misconceptions recur across students on a given case) — a natural extension of the existing `AnalyticsService`/Admin Analytics Dashboard, explicitly out of scope now and explicitly easy later, since the transcript data already persisted contains everything such an aggregate would need.

## New Personas (Named, Not Yet Built)

Security Review, System Design Interview, Code Review, Architecture Review, DevOps Review — each additive: new `config/discussion_personas.php` entries plus, at most, a small strategy class, per the Subject × Persona extensibility split (see [10-persona-system.md](10-persona-system.md)).

## New Subjects (Named, Not Yet Built)

A code submission, a design brief, or an open-ended topic with no underlying platform record at all — each a new `DiscussionSubjectInterface` implementation, per [08-ai-architecture.md](08-ai-architecture.md#module-boundary).

## The Long-Term Vision: An AI Engineering Coach

The frozen design spec states this explicitly as a bet, not a guarantee: the long-term direction for the Discussion Engine isn't "AI discusses a simulated incident," it's a general **AI engineering coach** — something that challenges a person's technical reasoning, asks for evidence, catches contradictions, and pushes toward better thinking, usable independent of whether a person is inside an AI CaseLab case at all. Version 2 builds none of this directly; what it does is ensure reaching that vision later is a matter of **adding**, not **redesigning** — new modes are new personas, new material to discuss is a new subject, cost stays safe by construction regardless of scale, and the core state machine and Socratic behaviors are already persona/subject-agnostic. This is stated as a well-supported bet, not a proven one — it won't actually be proven until a second subject type is built for real.

**One real, stated gap this vision does not yet solve:** cross-session memory. Each `DiscussionSession` is scoped to one subject with no memory of a student's past discussions elsewhere — a genuine long-term coach that remembers a specific person's recurring weak spots across many sessions over months eventually needs *some* form of cross-session memory. What to remember, how long, how it's surfaced without feeling surveillance-y, and how it interacts with per-course/per-institution data boundaries is a real, separate design problem, explicitly not solved by the current per-session scoping and not attempted here.

## Explicitly Not Planned

Real-time voice interaction, cross-session persistent AI memory of a given student across cases, and student-authored personas are explicitly called out in the design spec as **not** natural extensions of this architecture — they would be meaningfully different products, not incremental additions.

## Platform-Level Future Work (beyond the AI subsystem)

- **Real AI/LLM-assisted diagnosis scoring** — deliberately out of scope for both Version 1 and Version 2 (see [01-project-vision.md](01-project-vision.md)); `evaluations.metadata`/`evaluation_criterion_results.metadata` remain an unused, documented extensibility seam should this ever be revisited as a deliberate, separate decision.
- **Multi-tenancy** — a single-institution deployment is assumed throughout the current design; not attempted.
- **A public case marketplace / user-submitted cases** — explicitly out of scope, would require a substantially different trust/moderation model than the current admin-authored content model.
