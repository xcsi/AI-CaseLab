# 06 — Frontend Architecture

> **Related:** [04-system-architecture](04-system-architecture.md) · [16-design-system](16-design-system.md) · [09-discussion-engine](09-discussion-engine.md)

## Stack

Server-rendered Blade views styled with Bootstrap 5, compiled through Vite (`resources/sass/app.scss` → one bundled CSS file, `resources/js/app.js` → one bundled JS file). There is no SPA framework and no client-side router — every navigation is a real page load, and interactivity within a page (evidence tabs, notebook autosave, hint unlock, the Engineering Discussion panel) is small, hand-written `fetch`-driven JavaScript, not a component framework. This is a deliberate simplicity choice, not an oversight — see [20-design-decisions.md](20-design-decisions.md).

## Blade Structure

```
resources/views/
  layouts/
    app.blade.php              (student shell — top nav)
    admin.blade.php             (admin shell — left sidebar)
    guest.blade.php             (auth pages — login/register/password reset)
    workspace.blade.php         (Investigation Workspace shell — no global nav, distraction-free)
    navigation.blade.php        (student top-nav partial, included by app.blade.php)
    admin-navigation.blade.php  (admin sidebar partial, included by admin.blade.php)
  dashboard.blade.php           (Inbox)
  cases/                        (Assigned Incidents catalog + Incident Briefing)
  investigation/
    show.blade.php              (the Investigation Workspace: Evidence Explorer + Viewer + Notebook + Discussion panel)
    diagnosis.blade.php         (Submit Diagnosis form)
    performance-review.blade.php
  admin/
    dashboard, cases, categories, hints, rubric-criteria, evaluations, analytics
  components/                   (Blade components: x-nav-link, x-dropdown, x-application-logo, etc.)
  welcome.blade.php              (marketing/landing page)
```

## Layout Components

Four PHP-backed Blade components under `app/View/Components/` (`AppLayout`, `AdminLayout`, `GuestLayout`, `WorkspaceLayout`) wrap the corresponding `layouts/*.blade.php` file and expose typed constructor parameters rather than relying purely on Blade slots for structural data. `WorkspaceLayout` is the interesting one: it takes a nullable `discussionUrl` parameter — `null` renders the workspace exactly as it always has (every pre-Version-2 case, and every Version-2 case with `discussion_enabled = false`), and a real URL renders the "Start Engineering Discussion" entry point. This additive, nullable-by-default shape is the mechanism behind Version 2 never changing Version 1's rendered output for any case that hasn't opted in.

## Navigation

Two independent navigation partials, not a shared component with role-based branching, because the two shells' visual language and content differ enough (top nav vs. sidebar, "Inbox/Assigned Incidents/Work History" vs. "Dashboard/Cases/Categories/Reviews/Users/Analytics") that a shared component would need more conditional branching than it would save. They are, however, **reciprocally linked**: `layouts/navigation.blade.php` shows an "Admin Console" link (gated `hasRole(Admin)||hasRole(Instructor)`) so an admin landing on `/dashboard` (the Inbox is unconditionally every user's post-login destination) has a way into `/admin`; `layouts/admin-navigation.blade.php` shows a reciprocal "Student Workspace" link back to `/dashboard`. The admin sidebar's own gating is structural (the entire `/admin` route group already requires `role:admin,instructor`), so no redundant Blade-level role check is added there — see [15-security-architecture.md](15-security-architecture.md) for why that asymmetry between the two nav partials is intentional, not an inconsistency.

## The Investigation Workspace

The single most complex frontend surface in the application: three panes (Evidence Explorer sidebar, tabbed Evidence Viewer, Engineering Notebook), a live elapsed-time display computed client-side from the attempt's server-persisted `started_at` (never client/session state, so it survives a reload correctly), an evidence-viewed counter, and — on discussion-enabled cases — the Engineering Discussion panel. All of it lives in one Blade view (`investigation/show.blade.php`) with an inline JS IIFE, following the same `fetch()`-driven autosave/action pattern used consistently across the workspace:

- **Evidence tabs**: clicking an Evidence Explorer item opens (or activates an existing) tab; tab elements are built via `createElement`/`textContent`, not an `innerHTML` template literal — a defense-in-depth fix against a stored-XSS vector if an evidence title ever contains markup (found during the Phase 5 architectural review, see [21-problems-and-solutions.md](21-problems-and-solutions.md)).
- **Notebook autosave**: debounced `PATCH` to `investigation.notes.update`, with a saved/saving/error-saving indicator.
- **Hint unlock**: a confirm modal ("Ask a senior engineer?") before `POST`ing the unlock; the fetch chain explicitly checks `response.ok` before treating a response as success — a real gap the Phase 12 validation audit found and fixed.
- **Engineering Discussion**: a dark-panel chat-style modal, detailed in [09-discussion-engine.md](09-discussion-engine.md#the-workspace-panel).

Evidence type renderers (log viewer, code viewer, DB snapshot table, API response/JSON, screenshot) intentionally use a dark, monospace visual language — the "Night" token family in the Design System — so evidence reads as authentic engineering material rather than quiz content. See [16-design-system.md](16-design-system.md).

## Design System

The application's visual identity is governed by a formal, approved token system (palette, typography, spacing, radius, shadow, icon style, component specs, accessibility, and responsive rules), implemented as SCSS/Bootstrap variable overrides plus CSS custom properties in `resources/sass/_variables.scss` and `resources/sass/app.scss`. Full specification in [16-design-system.md](16-design-system.md); the rollout is tracked milestone-by-milestone in [19-milestones.md](19-milestones.md) under the Visual Identity effort.

## Responsive Behavior

Three tiers matching Bootstrap's existing `md` breakpoint (no custom breakpoint system): desktop (≥992px, full sidebar/nav, multi-column evidence panes), tablet (768–991px, collapsed nav, single-column), mobile (<768px, fully stacked, the existing hamburger + `.collapse` nav pattern). The admin sidebar's full-height-on-tall-pages behavior (a real bug found and fixed post-launch — see [22-bug-history.md](22-bug-history.md)) and the workspace top bar's `flex-wrap` behavior below the `sm` breakpoint (Phase 5, Milestone 5) are the two responsive-layout fixes worth knowing about if extending either surface.

## JavaScript Conventions

No bundler-managed component tree — each interactive Blade view owns a small, self-contained IIFE. Conventions applied consistently across all of them:

- `fetch()` with an explicit `response.ok` check before parsing JSON.
- CSRF token read from the page's `<meta name="csrf-token">` tag (Laravel's Breeze-provided convention).
- User-facing errors surface as an inline message near the affected control, never a raw exception or a silent failure.
- DOM construction for any content derived from user/admin-authored data uses `createElement`/`textContent`, never `innerHTML` string interpolation.
