# 32 — API Reference

> **Related:** [05-backend-architecture](05-backend-architecture.md) · [09-discussion-engine](09-discussion-engine.md) · [15-security-architecture](15-security-architecture.md)
> This is a server-rendered application (Blade), not a JSON API product — "API" here means the full HTTP route surface, including the JSON-returning endpoints the frontend's own `fetch()` calls use. All routes are defined in `routes/web.php`. Route names are shown in `name()` form.

## Public / Guest Routes

| Method | Path | Route name | Controller | Auth |
|---|---|---|---|---|
| GET | `/` | — | closure → `welcome` view | Guest |
| GET | `/incidents` | `cases.index` | `Student\CaseCatalogController@index` | Guest-accessible |
| GET | `/incidents/{case:slug}` | `cases.show` | `Student\CaseCatalogController@show` | Guest-accessible (`withTrashed()`) |

## Authentication (Breeze, standard)

Login, registration, password reset, email verification — `routes/auth.php`, unmodified Breeze conventions.

## Authenticated (any role)

| Method | Path | Route name | Controller | Middleware |
|---|---|---|---|---|
| GET | `/dashboard` | `dashboard` | `Student\DashboardController@index` | `auth`, `verified` |
| GET | `/profile` | `profile.edit` | `ProfileController@edit` | `auth` |
| PATCH | `/profile` | `profile.update` | `ProfileController@update` | `auth` |
| DELETE | `/profile` | `profile.destroy` | `ProfileController@destroy` | `auth` |
| POST | `/incidents/{case:slug}/start` | `attempts.store` | `Student\CaseAttemptController@store` | `auth` |
| GET | `/performance-review/{attempt}` | `performance-review.show` | `Student\PerformanceReviewController@show` | `auth`, `attempt.owner` |
| GET | `/work-history` | `progress.index` | closure → `placeholder` view | `auth` |

## Investigation Workspace (`/investigation/{attempt}/*`, all `auth` + `attempt.owner`)

| Method | Path | Route name | Controller |
|---|---|---|---|
| GET | `/investigation/{attempt}` | `investigation.show` | `Student\CaseAttemptController@show` |
| POST | `/investigation/{attempt}/evidence/{evidenceItem}/view` | `investigation.evidence.view` | `Student\EvidenceController@recordView` |
| PATCH | `/investigation/{attempt}/notes` | `investigation.notes.update` | `Student\NotebookController@update` |
| POST | `/investigation/{attempt}/hints/{hint}/unlock` | `investigation.hints.unlock` | `Student\HintController@unlock` |
| GET | `/investigation/{attempt}/report` | `investigation.diagnosis.create` | `Student\DiagnosisController@create` |
| POST | `/investigation/{attempt}/report` | `investigation.diagnosis.store` | `Student\DiagnosisController@store` |
| POST | `/investigation/{attempt}/discussion` | `investigation.discussion.start` | `Student\DiscussionController@start` |
| GET | `/investigation/{attempt}/discussion` | `investigation.discussion.show` | `Student\DiscussionController@show` |
| POST | `/investigation/{attempt}/discussion/messages` | `investigation.discussion.respond` | `Student\DiscussionController@respond` — additionally `throttle:discussion-messages` |
| POST | `/investigation/{attempt}/discussion/end` | `investigation.discussion.end` | `Student\DiscussionController@end` |

**Note:** there is no `{session}` URL parameter for any Discussion route — `DiscussionController` always derives the relevant session from the already-verified `{attempt}`, never accepts a client-supplied session ID. See [09-discussion-engine.md](09-discussion-engine.md).

### Discussion Endpoint Response Shapes

| Endpoint | Success | Failure modes |
|---|---|---|
| `POST .../discussion` (start) | 200, session state (active, round 1, opening turns) | 409 already active; 422 validation; 503 chain exhausted/leak (generic message) |
| `POST .../discussion/messages` (respond) | 200, updated session state + new turns | 409 not active/terminal; 422 validation; 429 rate limited; 503 chain exhausted/leak |
| `POST .../discussion/end` | 200, terminal session state | 409 not active |
| `GET .../discussion` (show) | 200, current session state | 404 no session ever started |

All routes in this group: guest → redirected to login; a different (non-owning) student → 403 via `attempt.owner`; the owning student → normal responses above.

## Admin (`/admin/*`, `auth` + `role:admin,instructor`, name prefix `admin.`)

| Method | Path | Route name | Controller |
|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | `Admin\DashboardController@index` |
| Resource (except `show`) | `/admin/cases` | `admin.cases.*` | `Admin\CaseController` |
| POST | `/admin/cases/{case}/publish` | `admin.cases.publish` | `Admin\CaseController@publish` |
| Shallow resource (store/update/destroy) | `/admin/cases/{case}/hints`, `/admin/hints/{hint}` | `admin.hints.*` | `Admin\HintController` |
| POST | `/admin/hints/{hint}/move-up` | `admin.hints.move-up` | `Admin\HintController@moveUp` |
| POST | `/admin/hints/{hint}/move-down` | `admin.hints.move-down` | `Admin\HintController@moveDown` |
| Shallow resource | `/admin/cases/{case}/rubric-criteria`, `/admin/rubric-criteria/{criterion}` | `admin.rubric-criteria.*` | `Admin\RubricCriterionController` |
| Resource (except create/show/edit) | `/admin/categories` | `admin.categories.*` | `Admin\CategoryController` |
| Resource (index/edit/update only) | `/admin/evaluations` | `admin.evaluations.*` | `Admin\EvaluationReviewController` |
| GET | `/admin/users` | `admin.users.index` | closure → `placeholder` view (see [29-future-roadmap.md](29-future-roadmap.md)) |
| GET | `/admin/analytics` | `admin.analytics.index` | `Admin\AnalyticsController@index` |

## Authorization Summary by Route Group

| Group | Enforcement |
|---|---|
| `/admin/*` | `role:admin,instructor` middleware, plus Policy checks per action (`CasePolicy`, `CategoryPolicy`, `EvaluationPolicy`) |
| `/investigation/{attempt}/*` | `attempt.owner` middleware (blocks every non-owner, including admins, before controller code runs) |
| Discussion routes specifically | `attempt.owner` middleware **plus** `DiscussionSessionPolicy` (`participate`/`view`) inside the controller — though the Policy's admin/instructor `view` allowance is currently unreachable through these routes, since `attempt.owner` runs first; see [15-security-architecture.md](15-security-architecture.md) |

Full route-by-route audit methodology and results in [37-security-review.md](37-security-review.md).
