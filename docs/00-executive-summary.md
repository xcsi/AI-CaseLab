# 00 — Executive Summary

> **Audience:** anyone opening this repository for the first time — a new engineer, a maintainer, an evaluator. Read this first.
> **Related:** [01-project-vision](01-project-vision.md) · [04-system-architecture](04-system-architecture.md) · [18-development-phases](18-development-phases.md)

## Project Overview

**AI CaseLab** is a web platform where computer science students practice software-engineering incident response by investigating simulated, realistic workplace incidents — login failures, API 500 errors, database performance regressions, payment failures, upload bugs — using the same kind of evidence a real engineer would use: support tickets, application logs, database snapshots, API responses, and screenshots. A student forms a diagnosis, submits it, and receives a rubric-based evaluation. In Version 2, before submitting, a student can optionally defend their reasoning to an AI reviewer in a Socratic dialogue — the **Engineering Discussion** — before committing to a final answer.

The product is deliberately framed as a **Virtual Engineering Office**, not a Learning Management System: students are "junior engineers," the case catalog is "Assigned Incidents," the dashboard is the "Inbox," and the final result screen is a "Performance Review." This narrative framing is a first-class product requirement, not cosmetic (see [09-workplace-terminology.md](09-workplace-terminology.md) if migrated, and [16-design-system.md](16-design-system.md) for the visual identity built on top of it).

## Purpose

Traditional CS coursework teaches students to write code from a specification. It rarely teaches them to **read** an unfamiliar codebase under time pressure, correlate evidence across systems (logs, DB, API traces), and defend a root-cause hypothesis against scrutiny — all core skills of real incident response and on-call engineering. AI CaseLab exists to close that gap by simulating the investigative half of software engineering, not just the construction half.

## Business Value

| Stakeholder | Value delivered |
|---|---|
| Students | A safe, repeatable environment to practice diagnostic reasoning against realistic (not toy) incidents, with immediate, rubric-based feedback and a Socratic AI reviewer that resists a shallow answer instead of just grading it. |
| Instructors | An authoring platform (case CRUD, evidence, hints, rubric criteria, publish workflow) plus a manual-review queue for judgment-based rubric criteria, and an analytics dashboard for cohort-level insight (completion rates, score distribution, hint usage, re-attempt rates). |
| Institution / program | A portfolio-quality, extensible teaching tool that can grow new incident categories and review personas (Security Review, System Design Interview, Code Review) without re-architecture — see [1.5 in the AI design doc](../docs/13-ai-discussion-engine-design.md#15-two-independent-extensibility-axes-subject--persona) and [29-future-roadmap.md](29-future-roadmap.md). |

## Target Users

- **Students** (role `student`) — the primary user; investigates cases, submits diagnoses, engages the AI reviewer.
- **Instructors** (role `instructor`) — authors and publishes cases (read/write on content), performs manual review of judgment-based rubric criteria, views analytics. Cannot manage users.
- **Admins** (role `admin`) — everything an instructor can do, plus platform-level administration.
- **Guests** — can browse one sample incident (`cases.index`/`cases.show`) to see what the platform does before registering.

## Key Capabilities

1. **Investigation Workspace** — a multi-pane workbench (Evidence Explorer, Evidence Viewer with type-specific renderers, Engineering Notebook, live timer/progress) modeled deliberately as a developer tool, not a quiz UI.
2. **Evidence Management** — five evidence types (support ticket, log excerpt, DB snapshot, API response, screenshot), each with view tracking so a diagnosis can be checked against what a student actually looked at.
3. **Hint System** — hints cost points when unlocked, so a student trades score for help deliberately, not accidentally.
4. **Diagnosis Submission & Evaluation Engine** — a pluggable Strategy-pattern evaluator (keyword matching, evidence-citation matching, manual review) computes a rubric-based score automatically, with instructor override for judgment criteria.
5. **Engineering Discussion (Version 2)** — an AI reviewer (Mentor or Interviewer persona) that challenges a student's stated root cause before it is submitted, backed by a cost-safe, provider-agnostic LLM fallback chain that defaults to free/local models and never silently reaches a paid provider. See [08-ai-architecture.md](08-ai-architecture.md).
6. **Performance Review** — the scored result screen: per-criterion breakdown, case average comparison, model-solution recap, and (if used) the full Engineering Discussion transcript.
7. **Admin CMS & Analytics** — case/category/hint/rubric authoring, publish-invariant enforcement, manual review queue, and a cohort analytics dashboard.

## Current Status

As of this documentation package (2026-08-03), **Version 1** (Phases 1–12: the full student/admin case-investigation platform) and **Version 2** (Phases 13–21: the AI Discussion Engine) are both complete, with a combined automated test suite of **498 passing tests**. Phase 22 (documentation/deployment/release) and a subsequent visual-identity implementation effort are in progress on top of the completed feature set. See [18-development-phases.md](18-development-phases.md) for the full phase-by-phase history and [19-milestones.md](19-milestones.md) for the milestone-level breakdown.

## How to Use This Documentation Set

This documentation library (`docs/00`–`docs/39` plus this README) is the **engineering handover package** — written so a senior engineer with no access to prior project conversations can understand, maintain, and extend AI CaseLab. It is distinct from `docs/AI-CaseLab-Final-Project-Report.md`, which is a formal academic report written for a university supervisor and graduation committee. Start at [docs/README.md](README.md) for the full index.
