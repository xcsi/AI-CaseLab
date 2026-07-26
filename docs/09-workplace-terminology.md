# AI CaseLab — Workplace Terminology Glossary

## Principle

AI CaseLab must feel like a **Virtual Engineering Office**, not a Learning Management System. Students are junior software engineers; every screen should reinforce that fiction. This document is the single source of truth for that narrative language, so it stays consistent across every page instead of being improvised per feature.

## Two-Layer Naming (why code doesn't get renamed)

We apply the workplace narrative to **what the user reads**, not to **what the code is called**. Two reasons:

1. **Rule 18 — maintainability.** A future developer opening this codebase will search for `Case`, `Evidence`, `Evaluation` — the precise, conventional domain terms already fixed in `03-database-design.md` and `04-architecture.md`. Renaming the `Case` model to `Incident` or a controller to `InboxController` for flavor would fight every Laravel convention (route-model binding, factory names, migration names) for zero functional benefit, and would confuse the next maintainer more than it delights the current student.
2. **Rule 14 — naming consistency** is about *not mixing vocabularies within a layer*, not about forcing one vocabulary across all layers. Precise technical names in the database/backend layer, and consistent narrative names in the presentation layer, are each internally consistent — that satisfies the rule at the layer where it matters for each audience (maintainers vs. students).

So: **models, tables, routes' internal names, service/repository classes stay technical.** **Page titles, navigation labels, empty states, button text, and (where natural) URL paths adopt the workplace terms below.**

## Glossary

| Technical concept (code/DB) | Workplace-facing label (UI copy) | Notes |
|---|---|---|
| Student-facing app shell as a whole | **Engineering Office** | The overall branded experience for students (nav shell, tone). Matches roadmap Phase 5 name. |
| Student dashboard / home page | **Inbox** | Landing page after login — "here's what's waiting for you," like walking in and checking your inbox. |
| Case catalog (browse/filter cases) | **Assigned Incidents** | The queue of incidents available to work. Filters (category/difficulty/status) stay functionally identical. |
| Case details (pre-investigation ticket page) | **Incident Briefing** | *Proposed extension, not explicitly specified — flag for confirmation.* Sits between Assigned Incidents and the Workspace; shows the ticket and "what you'll investigate" before starting the clock. |
| Investigation page (3-pane workspace) | **Investigation Workspace** | Explicitly specified. Core screen; internal route/controller names (`CaseAttemptController`) stay technical. |
| Notes panel | **Engineering Notebook** | Explicitly specified. Same autosave behavior, narrative label only. |
| Evaluation result page | **Performance Review** | Explicitly specified. Score + per-criterion feedback, framed as a review conversation rather than a "grade." |
| List of past attempts / completed cases | **Work History** | Explicitly specified. Could live as a dashboard section and/or its own nav item once Phase 11 analytics exist. |
| Admin dashboard / CMS | **Admin Console** | *Not specified by the user — kept conventional.* Admins are the "back office," not role-playing junior engineers, so this stays plain and utilitarian unless told otherwise. |

## Applied Navigation (Student "Engineering Office" shell)

```
Engineering Office
├── Inbox                     (home / dashboard)
├── Assigned Incidents        (case catalog)
├── Work History              (past attempts + scores)
└── [inside an active case]
    ├── Incident Briefing     (case details, pre-start)
    ├── Investigation Workspace
    │   └── Engineering Notebook   (notes panel within the workspace)
    └── Performance Review    (post-submission result)
```

## URL Convention

Where it reads naturally in the address bar, routes reflect the narrative without renaming the underlying model:

- `/office` or `/dashboard` → Inbox (either is fine; pick one and keep it — see open question below)
- `/incidents` → Assigned Incidents (catalog) — Eloquent model remains `CaseModel` / table `cases`
- `/incidents/{case}` → Incident Briefing
- `/investigation/{attempt}` → Investigation Workspace
- `/investigation/{attempt}/report` → diagnosis submission
- `/performance-review/{attempt}` → Performance Review
- `/work-history` → Work History

Admin routes stay conventional: `/admin/cases`, `/admin/cases/{case}/evidence`, etc.

## Open Questions (flagged, not blocking)

1. Is "Incident Briefing" the right name for the pre-investigation page, or do you have a preferred term?
2. Should the dashboard route/URL literally be `/inbox`, or is `/dashboard` fine with just the on-page heading saying "Inbox"?

These don't block Phase 1 (no UI copy is written yet) and can be answered whenever Phase 5 (Student Engineering Office) starts.
