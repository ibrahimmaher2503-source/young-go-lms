# 08: Performance of the public and learner pages (early T-P11-02)

> Read `docs/agent-prompts/00_COMMON.md` first. Baseline: `docs/reviews/PERF_BUDGET_P8_2026-11-19.md`. Repeat the same lab method it used.

## Pages
Home, the catalog with filters, course detail, the blog index and a post, an instructor profile, a category page, checkout, the student dashboard, the player and the parent dashboard. Measure each one on throttled mobile 4G and on desktop, in Arabic and in English.

## What to measure
- In the browser: LCP, CLS and INP (lab values), JS and CSS size, and the number and size of images.
- On the server: query count per request (use Debugbar or `DB::listen` in a scratch environment), N+1 queries, slow queries, missing indexes, and time per request.
- In Filament: the heavy tables (orders, reports, Learner 360).

## Fixes
Fix whatever exceeds the performance budget in `docs/`. Use eager loading, indexes (added through migrations), caching with clear invalidation, lazy-loaded images with explicit sizes, and removal of render-blocking assets. Do not change the design. Measure while no other test load is running: the earlier note recorded LCP going over budget under concurrent load.

## Output
- `docs/reviews/PERF_BUDGET_FULL_2026-11-xx.md`: before and after numbers for each page, the commits, and anything still over budget with the reason.
- A test prompt for the N+1 cases, with query-count assertions: `docs/test-prompts/PERF-QUERIES.md`.
- A summary in Arabic.
