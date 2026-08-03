# 23 — Performance Optimizations

> **Related:** [05-backend-architecture](05-backend-architecture.md) · [07-database-design](07-database-design.md) · [21-problems-and-solutions](21-problems-and-solutions.md)
> **Primary source:** Phase 12, Milestone 4 (the dedicated performance/N+1 audit).

## Methodology

Rather than optimizing speculatively, real query counts were measured via `DB::enableQueryLog()` against seeded data, **scaled up specifically to distinguish flat-cost pages from genuinely-scaling ones** — a page that runs the same number of queries at 5 records and 50 records is fine even if that number "looks high" in isolation; a page whose query count grows with record count is the actual problem, regardless of how small it looks at low record counts. Audited pages: Admin Dashboard, Analytics, Cases index/edit, Categories index, Evaluations index, and the student Catalog/Dashboard/Workspace/Performance Review.

## The One Genuine N+1 Found and Fixed

`Admin\DashboardController::needsAttention()` called `CaseCatalogService::publishInvariantErrors()` once per draft case, and that method itself ran two internal queries (`->rubricCriteria()->doesntExist()` + `->rubricCriteria()->sum()`) — 2N queries scaling with draft-case count, measured at 18 queries with 5 drafts and confirmed still 18 at 15 (proving it wasn't actually scaling at that point — the real growth was verified separately by checking against the draft count directly). Fixed by having the service read the already-loaded `rubricCriteria` relation collection instead of two separate query-builder calls, with `needsAttention()` eager-loading it once — now flat at 8 queries regardless of draft count. This also incidentally reduced the Case Edit page's own query count, since `_rubric.blade.php`'s `$case->rubricCriteria` access now reuses the same loaded relation instead of issuing a separate lazy query. Full write-up in [21-problems-and-solutions.md](21-problems-and-solutions.md).

## A Second, Smaller Fix

`AnalyticsService::categoryAggregates()`'s `->cases()->pluck('id')` query-builder call, run once per category, was replaced with a single batched `Category::with('cases:id,category_id')` eager load — reducing query count from 57 to 51 at 5 categories.

## A Deliberate, Documented Non-Fix

`AnalyticsService`'s per-category cost — `summary()` re-running its five scoped metric queries once per category rather than a single batched cross-category query — was **left as-is**, explicitly, as a Phase 6 Milestone 3 trade-off: trading query count for zero duplicated aggregation logic across the platform-wide/single-case/category scopes (every scope shares one implementation of `summary()`, called with a different `?array $caseIds`). Restructuring this into batched cross-category queries would be a substantial rework of the service, not a "safe" optimization, and category counts are small in practice at this project's scale. Carried forward explicitly in [12-deployment-guide.md](12-deployment-guide.md)/[25-deployment-guide.md](25-deployment-guide.md) as a known limitation, not silently accepted.

## Confirmed Already Correct

Every other audited page (Catalog, both Dashboards, Workspace, Case/Categories/Evaluations indexes, Case Edit, Performance Review) was already correctly eager-loaded, confirmed flat under 3× scale-up. Every index named in [07-database-design.md](07-database-design.md)'s Indexing Notes was confirmed already present in the actual migrations during this same audit — nothing was missing there.

## Verification Discipline

Both fixes were verified to return **identical data**, not just fewer queries — the full test suite (unchanged at 291/291) plus manual re-rendering of both fixed pages against real seeded MariaDB data, confirming byte-identical output before and after. A performance fix that changes *how* data is fetched must be proven to return the same data, not merely assumed to.

## AI Subsystem Performance Notes

The Engineering Discussion is synchronous by design (see [20-design-decisions.md](20-design-decisions.md)) — a discussion turn is one bounded LLM call with a hard `max_tokens` cap (~300 tokens), acceptable inline latency (a few seconds) for the feature's actual usage pattern. Context-window growth is bounded by summarization after 4 rounds rather than unbounded transcript replay (see [11-prompt-pipeline.md](11-prompt-pipeline.md)). Ollama's tier-1 liveness check uses a short cached TTL (~30s) specifically to avoid re-probing on every message within an active discussion, while the hosted free tiers' rate-limit backoff cache (~10–15s) avoids hammering an already-limited endpoint within a burst.

## Future Performance Work

If usage grows past comfortable synchronous request/response for the Discussion Engine, the design spec identifies async/streaming (a queued job + Server-Sent Events, using the currently-unused `QUEUE_CONNECTION=database` headroom) as the natural next step — explicitly not needed at launch. See [29-future-roadmap.md](29-future-roadmap.md).
