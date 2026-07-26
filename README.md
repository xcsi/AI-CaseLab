# AI CaseLab

**An Interactive Software Engineering Training Platform** — a virtual engineering office where students investigate realistic production incidents (login failures, API 500s, database performance issues, payment failures) using support tickets, logs, code, and data, instead of reading theory.

> **Status:** Design phase complete. Implementation in progress — see [Roadmap](#roadmap) below.

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

## Roadmap

| Phase | Name | Status |
|---|---|---|
| 1 | Laravel Project Setup | Not started |
| 2 | Authentication & Roles | Not started |
| 3 | Database Schema & Models | Not started |
| 4 | Admin CMS | Not started |
| 5 | Student Engineering Office | Not started |
| 6 | Incident Investigation Workspace | Not started |
| 7 | Evidence Management | Not started |
| 8 | Investigation Notes | Not started |
| 9 | Diagnosis Submission | Not started |
| 10 | Evaluation Engine | Not started |
| 11 | Analytics & Performance Dashboard | Not started |
| 12 | Testing & Deployment | Not started |

Full detail (objectives, dependencies, deliverables) for each phase is in [`docs/07-implementation-roadmap.md`](docs/07-implementation-roadmap.md).

## Getting Started

Setup instructions will be added here once Phase 1 (Laravel Project Setup) is complete.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for branch strategy, commit conventions, and code standards. See [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) for community expectations.

## Security

See [`SECURITY.md`](SECURITY.md) for how to report a vulnerability.

## License

Released under the [MIT License](LICENSE).
