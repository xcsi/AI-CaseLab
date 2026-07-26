# AI CaseLab — Business Requirements

## 1. Project Vision

AI CaseLab is an interactive training platform that teaches software engineering the way it is actually practiced: by diagnosing and resolving realistic production incidents. Instead of reading a chapter on debugging or database performance, a student opens a *case* — a simulated workplace scenario complete with a support ticket, logs, code, and data — and works it the way a junior engineer would on their first on-call rotation.

The long-term vision is a growing library of scenario-based cases that CS programs, bootcamps, and self-learners can use as a hands-on substitute (or companion) to theoretical coursework, with automated, consistent evaluation of how well a student investigates and diagnoses a problem — not just whether they got the "right answer."

## 2. Problem Statement

- CS curricula teach isolated concepts (SQL, HTTP, data structures) but rarely teach the *investigative workflow* engineers use to resolve real incidents: reading a ticket, correlating logs, forming a hypothesis, checking evidence, and writing a diagnosis.
- Students graduate able to write code but not to debug someone else's, under ambiguity, with incomplete information — which is most of real engineering work.
- Existing platforms (LeetCode-style judges) test algorithmic correctness, not diagnostic reasoning, root-cause analysis, or communication of findings.
- Instructors have no scalable way to simulate "a bug happened in production, go find it" for a class of 100+ students, nor a consistent rubric to grade the investigation process itself.

## 3. Proposed Solution

A web platform where each **Case** is a self-contained simulated incident containing multiple **Evidence** artifacts (ticket, logs, code snippets, DB snapshot, API responses, screenshots) and optional **Hints**. Students:

1. Browse a case catalog filtered by category/difficulty.
2. Open a case and read the initial support ticket.
3. Investigate by opening evidence items (logs, code, DB snapshot, etc.) in dedicated viewers.
4. Take investigation notes as they go.
5. Optionally unlock hints (at a scoring cost).
6. Submit a **Diagnosis** (root cause + proposed fix) as a structured final report.
7. Receive an **Evaluation** — automated scoring against a rubric, with feedback — and see it reflected on their dashboard/progress.

Instructors/Admins author cases, evidence, and rubrics, and monitor student performance across cohorts.

## 4. Target Users

| User | Description | Primary Goal |
|---|---|---|
| **Student** | CS student, bootcamp learner, or self-taught developer | Practice realistic debugging/diagnosis, build a portfolio of solved cases, track skill progress |
| **Instructor** | University lecturer / bootcamp mentor | Assign cases, monitor cohort progress, review submitted diagnoses, calibrate rubrics |
| **Admin (Content Author)** | Platform maintainer or senior instructor | Author and publish cases, evidence, hints, and evaluation rubrics; manage users and categories |
| **(Future) Guest/Visitor** | Prospective user | Preview a sample case before registering |

For v1, Instructor and Admin may be the same role (`admin`) with Instructor-level reporting as a permission subset — see Actors in the SRS.

## 5. Functional Requirements

**Authentication & Accounts**
- FR1: Users can register, log in, log out, and reset their password.
- FR2: Roles: `student`, `instructor`, `admin`, enforced via authorization policies.

**Case Catalog**
- FR3: Students can browse/search/filter cases by category, difficulty, and status (not started / in progress / completed).
- FR4: Each case has a title, short description, category, difficulty, estimated time, and cover state.

**Case Investigation**
- FR5: A case detail page presents the initial support ticket and lists available evidence items.
- FR6: Students can open each evidence item in a type-appropriate viewer (log viewer, code viewer with syntax highlighting, DB table viewer, API response/JSON viewer, image/screenshot viewer).
- FR7: The system tracks which evidence a student has viewed and how long they spent investigating.
- FR8: Students can request hints; each hint may reduce the maximum achievable score for that case.
- FR9: Students can write and persist free-text investigation notes tied to a case attempt, saved incrementally (autosave/draft).

**Diagnosis & Evaluation**
- FR10: Students submit a structured final report (root cause, evidence cited, proposed fix, confidence level).
- FR11: The system evaluates the submission against a case-specific rubric and produces a score plus per-criterion feedback.
- FR12: Students can view past attempts, their evaluation, and correct-solution explanation after submission (configurable per case).
- FR13: Students can re-attempt a case (policy-configurable: unlimited vs. limited attempts).

**Progress & Dashboard**
- FR14: Student dashboard shows overall progress, completed cases, scores, streaks/badges (optional v1.x), and recommended next case.
- FR15: Instructor dashboard shows cohort/class progress, per-case statistics (avg score, avg time, common wrong diagnoses).

**Content Administration**
- FR16: Admin can create/edit/publish/archive cases, evidence items, hints, and rubrics through an admin UI.
- FR17: Admin can manage categories/tags, users, and roles.
- FR18: Admin can view platform-wide analytics (most attempted cases, completion rates, average scores).

**Notifications (optional, later milestone)**
- FR19: Users receive in-app notification when an evaluation is ready (relevant if evaluation is ever asynchronous/queued).

## 6. Non-Functional Requirements

- **NFR1 – Scalability:** Architecture must support adding new case types/evidence types without schema rewrites (see Database & Architecture docs — polymorphic evidence model, Strategy pattern for evaluators).
- **NFR2 – Maintainability:** Clean Code, SOLID, MVC with Service + Repository layers; controllers stay thin.
- **NFR3 – Performance:** Case list and dashboard pages should respond in <300ms server-side under normal load (indexed queries, eager loading to avoid N+1).
- **NFR4 – Security:** Standard Laravel protections (CSRF, mass-assignment guarding, authorization via Policies/Gates), role-based access control, input validation via Form Requests.
- **NFR5 – Usability:** Evidence viewers must resemble real developer tools (terminal-style log viewer, code viewer with line numbers) to reinforce authenticity.
- **NFR6 – Extensibility:** New evaluation strategies (keyword-matching, rubric-scoring, later AI-assisted scoring) must be pluggable without touching controllers.
- **NFR7 – Auditability:** All student actions relevant to grading (evidence viewed, hints used, submission time) are logged for evaluation and analytics.
- **NFR8 – Portability:** Runs on standard LAMP/LEMP stack (PHP 8.2+, MySQL 8, Laravel 11), deployable to shared hosting or containers.
- **NFR9 – Accessibility:** Bootstrap-based responsive UI usable on laptop and tablet screens; keyboard-navigable where reasonable.

## 7. Project Scope

**In Scope (v1 — graduation project deliverable)**
- Student and Admin/Instructor roles with authentication.
- Case CRUD (admin) and case browsing/investigation (student).
- Evidence types: Support Ticket, Logs, Code Snippet, DB Snapshot, API Response, Screenshot, Hint.
- Investigation notes, hint unlocking, diagnosis submission.
- Rule/rubric-based automated evaluation (not free-form AI grading in v1).
- Student dashboard + Instructor dashboard with basic analytics.
- Admin content-authoring UI.

**Out of Scope (v1, candidates for future versions)**
- Real AI/LLM-graded free-text diagnosis (v1 uses structured rubric scoring; architecture leaves room for an `AiEvaluationStrategy` later).
- Live collaborative multi-user case-solving.
- Payment/subscription billing.
- Mobile native app (responsive web only).
- Public case marketplace / user-submitted cases.
- Real-time chat/mentor support.

**Assumptions**
- Single institution / single deployment initially (multi-tenancy not required for v1).
- Content (cases, evidence) is authored by admins, not generated dynamically at runtime.
