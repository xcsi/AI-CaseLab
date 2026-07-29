# AI CaseLab

**An Interactive Software Engineering Training Platform** — a virtual engineering office where students investigate realistic production incidents (login failures, API 500s, database performance issues, payment failures) using support tickets, logs, code, and data, instead of reading theory.

> **Status:** All 12 roadmap phases complete. Full student and admin journeys work end to end against real data; 291 automated tests passing; deployment-ready (see [`docs/12-deployment-guide.md`](docs/12-deployment-guide.md)). See [Roadmap](#roadmap) below and the `[Unreleased]` section of [`CHANGELOG.md`](CHANGELOG.md) for the complete build history.

## Why This Exists

CS curricula teach isolated concepts but rarely the investigative workflow engineers use every day: read a ticket, correlate logs, form a hypothesis, check evidence, write a diagnosis. AI CaseLab simulates that workflow directly, with automated, rubric-based evaluation of the investigation and diagnosis — not just a pass/fail answer check.

## How It Works

1. A student opens an **Incident Briefing** — a realistic support ticket.
2. They investigate in the **Investigation Workspace**: logs, code snippets, database snapshots, API responses, and screenshots, each rendered in a type-appropriate viewer.
3. They record reasoning in the **Engineering Notebook** as they go, optionally spending points to unlock **Hints**.
4. They submit a structured diagnosis (root cause, proposed fix, confidence, cited evidence).
5. They receive a **Performance Review** — an automated, rubric-based score with per-criterion feedback.

See [`docs/09-workplace-terminology.md`](docs/09-workplace-terminology.md) for the full workplace-language glossary used throughout the UI.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, Laravel 11 |
| Database | MySQL 8 |
| Frontend | Blade, Bootstrap 5, vanilla/light JavaScript (Vite) |
| Architecture | MVC + Service layer + Repository pattern (where it earns its keep) + Strategy pattern (evaluation engine) |

## Documentation

All design and process documentation lives in [`docs/`](docs) and is the source of truth for this project:

| Doc | Contents |
|---|---|
| [`01-business-requirements.md`](docs/01-business-requirements.md) | Vision, problem statement, functional/non-functional requirements, scope |
| [`02-srs.md`](docs/02-srs.md) | Actors, use cases, system features, user stories, constraints |
| [`03-database-design.md`](docs/03-database-design.md) | ER diagram, tables, relationships, keys |
| [`04-architecture.md`](docs/04-architecture.md) | Folder structure, MVC + Service/Repository/Strategy layering, request traces |
| [`05-ui-ux-design.md`](docs/05-ui-ux-design.md) | Every page's layout, states, and interactions |
| [`06-task-breakdown.md`](docs/06-task-breakdown.md) | Fine-grained, day-by-day task reference |
| [`07-implementation-roadmap.md`](docs/07-implementation-roadmap.md) | The 12-phase build plan (source of truth for sequencing) |
| [`08-implementation-rules.md`](docs/08-implementation-rules.md) | Coding standards and delivery process for this project |
| [`09-workplace-terminology.md`](docs/09-workplace-terminology.md) | The "Virtual Engineering Office" UI language glossary |
| [`10-git-workflow.md`](docs/10-git-workflow.md) | Branch strategy, commit convention, milestone checklist |
| [`11-implementation-summary.md`](docs/11-implementation-summary.md) | Phase 1–5 implementation summary reference |
| [`12-deployment-guide.md`](docs/12-deployment-guide.md) | Production deployment, environment/migration verification, checklists |
| [`13-ai-discussion-engine-design.md`](docs/13-ai-discussion-engine-design.md) | **Version 2** — frozen architecture spec for the AI Discussion Engine |
| [`14-v2-implementation-roadmap.md`](docs/14-v2-implementation-roadmap.md) | **Version 2** — the Phase 13–22 build plan |

## Roadmap

| Phase | Name | Status |
|---|---|---|
| 1 | Laravel Project Setup | Done |
| 2 | Authentication & Roles | Done |
| 3 | Database Schema & Models | Done |
| 4 | Admin CMS | Done |
| 5 | Student Engineering Office | Done |
| 6 | Incident Investigation Workspace | Done |
| 7 | Evidence Management | Done (student-facing viewers only — see note below) |
| 8 | Investigation Notes | Done |
| 9 | Diagnosis Submission | Done |
| 10 | Evaluation Engine | Done |
| 11 | Analytics & Performance Dashboard | Done |
| 12 | Testing & Deployment | Done |

**Note on Phase 7:** the student-facing evidence viewers (log, code, DB snapshot, API response, screenshot) are fully built. The admin authoring UI for evidence was a deliberate scope trade-off and was not built — evidence exists via seeders (see `DemoDataSeeder`) rather than a CMS form. This is a documented, known limitation, not an oversight; see `docs/12-deployment-guide.md` §5.

Full detail (objectives, dependencies, deliverables) for each phase is in [`docs/07-implementation-roadmap.md`](docs/07-implementation-roadmap.md).

### Version 2 — AI Discussion Engine (design frozen, implementation not started)

A Socratic AI reviewer (Mentor Review / Technical Interview personas) that challenges a student's investigation before their diagnosis is accepted, rather than a chatbot or an auto-grader. Architecture is frozen in [`docs/13-ai-discussion-engine-design.md`](docs/13-ai-discussion-engine-design.md); build sequence is [`docs/14-v2-implementation-roadmap.md`](docs/14-v2-implementation-roadmap.md).

| Phase | Name | Status |
|---|---|---|
| 13 | Discussion Engine Foundations (Schema, Models, Contracts) | Not started |
| 14 | Provider-Agnostic LLM Client Layer | Not started |
| 15 | Personas & System Prompt Construction | Not started |
| 16 | DiscussionService & State Machine | Not started |
| 17 | HTTP Layer: Routes, Controllers, Requests | Not started |
| 18 | Investigation Workspace UI | Not started |
| 19 | Performance Review Integration & Admin Configuration | Not started |
| 20 | Cost-Safety & Observability Hardening | Not started |
| 21 | Provider Behavioral Conformance Validation | Not started |
| 22 | Documentation, Deployment Update & Release | Not started |

Version 1 (Phases 1–12) is preserved completely and unmodified by Version 2's design — see `docs/13` §9.4 for the specific database boundary.

## Getting Started

Requirements: PHP 8.2+, Composer, Node.js/npm, MySQL 8 (or a wire-compatible MariaDB).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# create the database named in DB_DATABASE, then:
php artisan migrate --seed
npm run build   # or: npm run dev
php artisan serve
```

The seeders create the three roles (`student`, `instructor`, `admin`) and one
local-development admin account: `admin@aicaselab.test` / `password` (never
created outside a non-production environment).

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for branch strategy, commit conventions, and code standards. See [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) for community expectations.

## Security

See [`SECURITY.md`](SECURITY.md) for how to report a vulnerability.

## License

Released under the [MIT License](LICENSE).
