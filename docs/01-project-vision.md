# 01 — Project Vision

> **Related:** [00-executive-summary](00-executive-summary.md) · [02-functional-requirements](02-functional-requirements.md) · [09-workplace-terminology (original)](09-workplace-terminology.md) · [16-design-system](16-design-system.md)
> **Primary source:** `docs/01-business-requirements.md` §1–3 (original business requirements, predates Version 2).

## Product Philosophy

AI CaseLab's founding premise is that **CS curricula teach isolated concepts but rarely teach the investigative workflow engineers actually use**: reading a ticket, correlating logs, forming a hypothesis, checking evidence, and defending a diagnosis under scrutiny. Algorithmic judges (LeetCode-style platforms) test correctness of code the student wrote from scratch; they do not test the ability to read someone *else's* system under ambiguity and incomplete information — which is the majority of real engineering work, especially early-career work.

The product's answer is the **Case**: a self-contained simulated incident with a support ticket, a bounded set of evidence artifacts, and a rubric that grades the *investigation*, not just a final answer. A student is graded on whether they cited the evidence that actually supports their conclusion, not merely on whether their conclusion happens to be correct.

**Version 2 extends this philosophy rather than replacing it.** The Engineering Discussion (see [09-discussion-engine.md](09-discussion-engine.md)) adds a Socratic AI reviewer that challenges a student's stated reasoning *before* they commit to a diagnosis — but it does not grade the diagnosis itself. Rubric-based scoring (keyword matching, evidence-citation matching, manual review) remains the scoring authority; the AI's role is adversarial rehearsal, not an oracle. This preserves the original scope decision in `docs/01-business-requirements.md` §7 that ruled out "real AI/LLM-graded free-text diagnosis" for automated scoring, while still using an LLM productively elsewhere in the product.

## The "Virtual Engineering Office" Narrative

Every student-facing surface is deliberately framed as a workplace, not a course:

| Generic LMS term | AI CaseLab term |
|---|---|
| Student home/dashboard | **Inbox** |
| Course catalog | **Assigned Incidents** |
| Assignment detail page | **Incident Briefing** |
| Assignment workspace | **Investigation Workspace** |
| Notes panel | **Engineering Notebook** |
| Grade/result page | **Performance Review** |
| Past assignments list | **Work History** |
| Admin dashboard | **Admin Console** (admins are back-office staff, not role-playing engineers — this one screen deliberately breaks the fiction) |

This vocabulary is a **binding UI-copy requirement**, not a style preference: it is applied to Blade view text, navigation labels, page titles, and (where natural) URL paths — never to the code layer. Models, tables, controllers, and services keep precise technical names (`CaseModel`, `cases` table, `CaseAttemptController`) regardless of what the UI calls the same concept. Two vocabularies exist on purpose: the narrative layer sells the pedagogical fiction to the student; the technical layer stays maintainable for engineers. See [39-glossary.md](39-glossary.md) for the full term list and [27-developer-guide.md](27-developer-guide.md) for how to apply it when adding a new screen.

## Engineering Philosophy

Three process rules have governed every phase of this project and are visible throughout the codebase and commit history:

1. **Incremental, reviewable delivery.** Work proceeds phase by phase, milestone by milestone — never a large, unreviewable diff. Every milestone ends with the full automated test suite green, a description of what changed and why, and (for UI work) a manual smoke test, before the next milestone starts. See [17-testing-strategy.md](17-testing-strategy.md) and [18-development-phases.md](18-development-phases.md).
2. **Layered architecture with a stated exception policy.** MVC plus a Service layer for business logic, Repositories only for true aggregate roots with real query complexity (not trivial lookup CRUD), Form Requests for all input validation, and Policies for all authorization. See [04-system-architecture.md](04-system-architecture.md) for exactly which classes get which pattern and why.
3. **No silent redesigns.** If a better approach is found mid-implementation, it is proposed as an explicit trade-off (what changes, why, cost) before anything changes — never swapped in quietly. The [31-architecture-decision-records.md](31-architecture-decision-records.md) file exists specifically to make these moments traceable.

A fourth, Version-2-specific principle governs the AI subsystem: **cost-safety and provider-agnosticism are structural guarantees, not runtime checks.** The system is architected so that no code path can silently reach a paid LLM provider unless an operator has explicitly opted in via configuration — provable by reading the code, not just by testing its behavior. See [13-provider-abstraction.md](13-provider-abstraction.md).

## Design Philosophy

The platform's visual identity follows a parallel principle to its narrative one: it must read as **a real engineering tool a student is training to use**, not a generic AI-generated dashboard or a "learning platform" template. The Investigation Workspace's evidence and code viewers deliberately use a dark, monospace "developer tool visual tone" instead of a bright, friendly LMS look — evidence should feel like authentic engineering material, not quiz content. This was formalized into a complete token-based design system in August 2026; see [16-design-system.md](16-design-system.md) for the full specification (palette, typography, spacing, component styles, accessibility, and responsive rules) and [20-design-decisions.md](20-design-decisions.md) for why a systemic token layer was introduced rather than continuing with page-by-page CSS.

## Long-Term Vision

`docs/13-ai-discussion-engine-design.md` §14 describes the AI subsystem's long-term direction beyond Version 2's scope: additional review **personas** (Security Review, System Design Interview, Code Review, Architecture Review, DevOps Review) and additional **subjects** beyond `CaseAttempt` (e.g., reviewing a pull request or a design document directly), enabled by the Subject × Persona extensibility split built into the architecture from Phase 15 onward (see [10-persona-system.md](10-persona-system.md)). The broader platform vision in `docs/01-business-requirements.md` §1 describes a growing library of scenario-based cases usable by CS programs, bootcamps, and self-learners as a hands-on companion to theoretical coursework. See [29-future-roadmap.md](29-future-roadmap.md) for a concrete, realistic near-term extension list.
