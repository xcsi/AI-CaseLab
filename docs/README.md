# AI CaseLab — Documentation Index

This is the complete engineering documentation library for AI CaseLab, written so a senior engineer with no access to prior project conversations can understand, maintain, and extend the system. It is distinct from **[`AI-CaseLab-Final-Project-Report.md`](AI-CaseLab-Final-Project-Report.md)**, a formal academic report written for a university supervisor and graduation committee — start there instead if that's your purpose.

**Legacy design documents** (`docs/01-business-requirements.md` through `docs/15-provider-conformance-results.md`) are the original, phase-contemporaneous planning and results documents this package draws from as primary sources; they remain in the repository and are cross-referenced throughout, not superseded.

## Foundations

| Doc | Contents |
|---|---|
| [00 — Executive Summary](00-executive-summary.md) | What AI CaseLab is, why it exists, key capabilities, current status |
| [01 — Project Vision](01-project-vision.md) | Product/engineering/design philosophy, the Virtual Engineering Office narrative |
| [02 — Functional Requirements](02-functional-requirements.md) | FR1–FR27 (Version 1 + Version 2), out-of-scope items |
| [03 — Non-Functional Requirements](03-non-functional-requirements.md) | NFR1–NFR16, constraints, assumptions |

## Architecture

| Doc | Contents |
|---|---|
| [04 — System Architecture](04-system-architecture.md) | High-level + layered architecture, SOLID rationale |
| [05 — Backend Architecture](05-backend-architecture.md) | Controllers, Services, Models, Repositories, validation, error handling |
| [06 — Frontend Architecture](06-frontend-architecture.md) | Blade structure, layout components, the Investigation Workspace, JS conventions |
| [07 — Database Design](07-database-design.md) | Full ER diagram, every table, design rationale, indexing |

## AI Subsystem

| Doc | Contents |
|---|---|
| [08 — AI Architecture](08-ai-architecture.md) | Overview, the seven architectural decisions, module boundary |
| [09 — Discussion Engine](09-discussion-engine.md) | Conversation flow, workspace UI, Performance Review integration |
| [10 — Persona System](10-persona-system.md) | Mentor vs. Interviewer, the Subject × Persona extensibility split |
| [11 — Prompt Pipeline](11-prompt-pipeline.md) | System prompt composition, context management, token budget |
| [12 — Structured Output](12-structured-output.md) | The JSON contract, parsing pipeline, the empty-reply bug case study |
| [13 — Provider Abstraction](13-provider-abstraction.md) | Ollama, OpenRouter, Gemini, the paid tier, the fallback chain, conformance results |
| [14 — State Machine](14-state-machine.md) | The four-state discussion lifecycle, transaction safety, guards |

## Cross-Cutting Concerns

| Doc | Contents |
|---|---|
| [15 — Security Architecture](15-security-architecture.md) | AuthN/authZ, AI safety layers, frontend hardening |
| [16 — Design System](16-design-system.md) | The full, approved Design System v1 — palette, type, spacing, components, accessibility, responsive |
| [17 — Testing Strategy](17-testing-strategy.md) | Test types, coverage discipline, network-free AI testing, manual testing |

## Development History

| Doc | Contents |
|---|---|
| [18 — Development Phases](18-development-phases.md) | All 22 phases — objectives, delivered scope, architectural reasoning, outcomes |
| [19 — Milestones](19-milestones.md) | Milestone-level index — purpose, key files, tests, per phase |
| [20 — Design Decisions](20-design-decisions.md) | The "why" behind major choices, narrative form |
| [21 — Problems and Solutions](21-problems-and-solutions.md) | Every significant engineering challenge — problem, root cause, investigation, alternatives, solution, prevention |
| [22 — Bug History](22-bug-history.md) | Compact chronological defect index |

## Operations

| Doc | Contents |
|---|---|
| [23 — Performance Optimizations](23-performance-optimizations.md) | The N+1 audit, methodology, fixes, deliberate non-fixes |
| [24 — Cost Optimizations](24-cost-optimizations.md) | The seven LLM cost levers, ranked |
| [25 — Deployment Guide](25-deployment-guide.md) | Server requirements, build/deploy steps, checklists |
| [26 — Configuration Reference](26-configuration-reference.md) | Every environment variable and config file |
| [27 — Developer Guide](27-developer-guide.md) | Local setup, testing, debugging, adding cases/personas/providers |
| [28 — Maintenance Guide](28-maintenance-guide.md) | Common tasks, upgrade strategy, extension points, known limitations, troubleshooting |

## Retrospective & Reference

| Doc | Contents |
|---|---|
| [29 — Future Roadmap](29-future-roadmap.md) | Near-term extensions, the AI subsystem's long-term vision, explicitly-not-planned items |
| [30 — Lessons Learned](30-lessons-learned.md) | Engineering retrospective — architecture, AI, testing, debugging, docs, design systems, UX, workflow, planning |
| [31 — Architecture Decision Records](31-architecture-decision-records.md) | 10 formal ADRs for the highest-stakes decisions |
| [32 — API Reference](32-api-reference.md) | Full route table, request/response shapes, authorization summary |
| [33 — Folder Structure](33-folder-structure.md) | Every major folder and its responsibility |
| [34 — Class Reference](34-class-reference.md) | Every Service, Controller, and AI-subsystem class |

## Flows, Glossary

| Doc | Contents |
|---|---|
| [35 — Sequence Diagrams](35-sequence-diagrams.md) | Investigation, Discussion, Diagnosis, Performance Review, Admin Review flows |
| [36 — Data Flow](36-data-flow.md) | End-to-end request/response flows, the full student journey, the discussion turn |
| [37 — Security Review](37-security-review.md) | Authorization audit results, known gaps, security posture summary |
| [38 — Operational Runbook](38-operational-runbook.md) | Incident triage, common symptoms and first checks |
| [39 — Glossary](39-glossary.md) | Narrative ↔ technical term mapping, domain terms, architectural/process/design-system terms |

## Suggested Reading Paths

- **New engineer onboarding:** 00 → 01 → 04 → 05/06/07 → 27 → 33/34
- **Understanding the AI subsystem specifically:** 08 → 09 → 10 → 11 → 12 → 13 → 14
- **Preparing to extend the platform:** 27 (Adding a Case/Persona/Provider) → 29 → 20/31
- **Operational/on-call reference:** 38 → 28 → 26 → 21/22
- **Academic/portfolio review:** [`AI-CaseLab-Final-Project-Report.md`](AI-CaseLab-Final-Project-Report.md), then 18/19/30 for supporting detail

## Document Status

All 40 documents (00–39) plus this index were authored in a single documentation pass (2026-08-03), sourced primarily from `CHANGELOG.md`, the complete git commit history, and the pre-existing `docs/01`–`15` design documents — not reconstructed from memory. See [30-lessons-learned.md](30-lessons-learned.md#documentation) for why this sourcing approach was possible and matters.
