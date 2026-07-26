# AI CaseLab — UI/UX Design (Planning Only, No HTML)

Design language: Bootstrap 5, utility-first spacing, a "developer tool" visual tone for investigation surfaces (dark code/log panels, monospace for evidence, light chrome for navigation/forms) so the experience reads as authentic engineering work, not a quiz app.

## 1. Navigation (Global Shell)

**Student shell (top navbar + optional sidebar on investigation pages)**
- Left: logo/wordmark "AI CaseLab".
- Center/left nav links: Dashboard, Case Catalog, My Progress.
- Right: notifications icon (future), user dropdown (Profile, Logout).
- On the Investigation Page only, the top navbar collapses to a slim bar (case title, timer/elapsed time, "Exit to Catalog" with confirm) to maximize vertical space for evidence — the investigation workspace is the product's core screen and shouldn't compete with global nav chrome.

**Admin shell**
- Persistent left sidebar: Dashboard, Cases, Categories, Evidence (contextual under a case), Hints (contextual), Rubrics (contextual), Users, Analytics.
- Top bar: breadcrumb (e.g. Cases / "API Returning 500" / Evidence), user dropdown.

**States:** authenticated vs guest nav differ (guest sees Login/Register only + a "View Demo Case" link). Active route highlighted.

## 2. Dashboard (Student)

**Purpose:** landing page after login; orient the student toward the next useful action.

**Layout:**
- Top: greeting + summary stat row (cases completed, average score, current streak/badge count — Bootstrap card row, 3–4 stat cards).
- Middle-left (main column): "Continue where you left off" card if an `in_progress` attempt exists; below it, "Recommended next case" (simple rule: next difficulty up from last completed, or same category).
- Middle-right (sidebar column): recent activity list (last 5 attempts with score + date), and a small progress-by-category chart (bar or radar — Bootstrap + Chart.js).
- Bottom: "Recently added cases" carousel/row.

**States:** first-login empty state (no attempts yet) shows a friendly onboarding card pointing at the catalog instead of empty stat cards showing zeros awkwardly.

## 3. Case List (Catalog)

**Purpose:** discovery and filtering.

**Layout:**
- Sticky filter bar: category (dropdown/chips), difficulty (chips: Easy/Medium/Hard), status (All / Not Started / In Progress / Completed), search input (debounced).
- Grid of case cards (Bootstrap `card` in a responsive grid, 3 columns desktop → 1 column mobile). Each card: category tag, difficulty badge (color-coded), title, one-line scenario teaser, estimated time, small progress indicator (not started / in-progress ring / completed check + score chip).
- Pagination or infinite scroll at bottom.

**States:** loading skeleton cards; empty state ("No cases match these filters" with a "Clear filters" action); a locked/coming-soon variant for `draft` cases visible only to admins previewing.

## 4. Case Details (Pre-Investigation)

**Purpose:** a "job assignment" screen before committing to start the timer/attempt — sets tone and expectations.

**Layout:**
- Header: title, category, difficulty badge, estimated time, "attempted before" indicator with past score if applicable.
- Body: the support ticket rendered as a realistic ticket card (reporter name, priority, timestamp, description) — this is the hook that makes it feel real.
- Sidebar: "What you'll investigate" (evidence type icons as a preview list, without revealing content), "Scoring" blurb (max score, hint penalty policy, reattempt policy).
- Primary CTA: "Start Investigation" (creates attempt) or "Resume Investigation" / "View Past Attempt" if one exists.

**States:** first-time vs returning student CTA differs; if `allow_reattempt` is false and already completed, CTA becomes "View Report" only.

## 5. Investigation Page (Core Workspace)

**Purpose:** the main working surface — must feel like a real debugging session.

**Layout (3-pane, IDE-like):**
- **Left pane (Evidence Explorer):** collapsible list of evidence items grouped/iconified by type (ticket pinned at top, then logs/code/DB/API/screenshots), each with a small "viewed" checkmark once opened. A "Hints" section at the bottom, locked hints shown grayed out with a lock icon and the penalty amount visible before unlocking.
- **Center pane (Evidence Viewer):** tabbed — clicking an evidence item opens it in a tab (multiple can stay open, like an editor); renders via the type-specific viewer (see §6).
- **Right pane (Notes):** always-visible notes panel (see §7), collapsible to give the center pane more room.
- **Top slim bar:** case title, elapsed timer, evidence-viewed progress ("4/7 viewed"), "Submit Diagnosis" primary button (opens the Final Report page/modal).

**States:** hint confirmation as a modal ("Unlocking this hint will reduce your max score by X points — continue?"); "Submit" button disabled with tooltip until at least the ticket has been read (configurable minimum-engagement rule, optional); exit-confirmation modal if leaving with unsaved notes.

**Responsive:** on tablet, panes stack with tab switcher (Evidence / Notes) instead of 3 columns side-by-side; investigation workspace is desktop-first by design (real debugging happens at a desk), tablet is "usable," phone is "read-only view of a completed report," not a full workspace.

## 6. Evidence Viewer (per type, rendered inside the center pane)

- **Support Ticket:** styled like a helpdesk ticket (reporter, priority chip, description) — usually shown once at the top of the workspace or as the default first tab, not hidden behind a click.
- **Log Viewer:** dark monospace panel, line numbers, color-coded log levels (ERROR red, WARN amber, INFO gray), a simple client-side text filter/search box above it.
- **Code Snippet Viewer:** syntax-highlighted (via a lightweight JS highlighter), filename + language tag header, optional highlighted line range to draw attention without giving away the answer outright.
- **DB Snapshot Viewer:** rendered as an actual data table (Bootstrap `table`) with a header showing table/schema name; optionally a second tab showing column types if the case is about schema issues.
- **API Response Viewer:** request/response split view — method+endpoint+status badge (color by status code range) on top, collapsible JSON tree below, headers in a secondary tab.
- **Screenshot Viewer:** image with caption, click-to-zoom lightbox.

**Shared states:** loading spinner while payload fetches (if lazy-loaded via JS/fetch rather than server-rendered); "mark as reviewed" happens automatically on open (no manual button — reduces friction, matches FR7).

## 7. Notes

**Purpose:** capture the student's reasoning trail.

**Layout:** simple textarea-like panel (could be a lightweight rich text/markdown-lite area) with an unobtrusive "Saved" / "Saving…" indicator (autosave on debounce, no manual save button to avoid lost work).
**States:** empty placeholder ("Jot down what you notice — referenced evidence, suspicions, dead ends"); saved/saving/error-saving indicator states.

## 8. Final Report (Diagnosis Submission)

**Purpose:** structured capture of the student's conclusion — separate page/modal from the workspace so it feels like a deliberate "closing the ticket" action.

**Layout:**
- Form fields: Root Cause (textarea), Proposed Fix (textarea), Confidence Level (segmented control: Low/Medium/High), "Evidence you relied on" (multi-select chips pulled from viewed evidence, pre-checked for items actually opened).
- Sidebar recap: hints used (list + total penalty), time spent, evidence viewed count — a last look before committing.
- Primary action: "Submit Diagnosis" with a confirm step (irreversible once submitted, unless reattempts allowed).

**States:** validation errors inline per field; disabled submit while a previous submit request is in flight; a "submitted, evaluating…" transient state if evaluation isn't instant.

## 9. Evaluation (Result)

**Purpose:** the learning moment — must feel like feedback, not just a grade.

**Layout:**
- Header: big score (e.g. "78 / 100"), pass/fail or grade-band styling (color-coded), comparison to case average (optional, motivating without being discouraging).
- Per-criterion breakdown: list of rubric criteria, each with score awarded / max, a short feedback line, and a visual check/partial/cross icon.
- "What actually happened" section: model solution summary revealed (respecting `allow_reattempt`/policy — if reattempts are off, always reveal; if on, maybe reveal only after N attempts or never before at least one submission).
- Footer actions: "Back to Catalog", "Re-attempt" (if allowed), "Try a harder case" (if score was high).

**States:** a `ManualReviewStrategy` criterion pending state ("Awaiting instructor review") shown distinctly from scored criteria if that strategy is used for a case.

## 10. Admin Dashboard

**Purpose:** content-authoring and monitoring home for admins/instructors.

**Layout:**
- Top stat row: total cases (draft/published/archived counts), total students, submissions today, platform average score.
- Main table: cases list (title, category, status badge, attempts count, avg score, last updated) with quick actions (Edit, View Evidence, View Rubric, Publish/Archive toggle) — this is the primary admin surface, so it's a data table, not cards.
- Sidebar/secondary: "Needs attention" widget — cases with 0 evidence or 0 rubric criteria (incomplete drafts), and any `ManualReviewStrategy` submissions pending grading.
- Nested pages (reached from a case row): **Case Editor** (title/ticket/settings form), **Evidence Manager** (list + add/edit per type with a type-specific sub-form), **Hint Manager** (ordered list, add/remove, penalty field), **Rubric Builder** (ordered criteria list, weight fields with a running total validated against 100%).
- **Analytics page:** cohort/case filters, charts for completion rate, average score distribution (histogram), average time-to-complete, most-unlocked hints, most-cited vs. never-cited evidence (signals a case's evidence balance is off).
- **User management page:** table of users, role dropdown per row, search/filter by role.

**States:** rubric weight total shown live with a warning if it doesn't sum to the case's `max_score`; "Publish" button disabled with an explanatory tooltip if invariants aren't met (no evidence / no rubric — mirrors the `CaseCatalogService::publish` business rule from the Architecture doc, so the UI never lets an admin attempt an action the backend will reject).

## 11. Cross-Cutting UX Notes

- **Color coding is consistent everywhere:** difficulty (green/amber/red), status (gray draft / green published / dark archived), score bands (red <50%, amber 50–75%, green >75%) — defined once as Bootstrap utility classes, reused across catalog, dashboard, and evaluation pages.
- **Empty/loading/error states are designed for every list-bearing page**, not an afterthought — catalog, evidence list, admin tables, analytics charts.
- **No page requires JavaScript for core functionality** except the investigation workspace's autosave/tab-switching, which are progressive enhancements over a server-rendered base (works with plain page reloads if JS fails, per the "no mandated SPA framework" constraint).
