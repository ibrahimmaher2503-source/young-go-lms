# Performance budget measurement: P8 public pages (T-P8-MG criterion 5)

Date: 2026-11-19. Branch `feat/lms-build`. Measured, not a gate pass. No app code was changed and nothing was committed.

## Budget (source of truth)

ED-030 (`docs/13_ENGINEERING_DECISIONS.md` line 137, `docs/11_DESIGN_SYSTEM.md` line 226, PRD YG-GAP-PERF-001): mid-range mobile on 4G, LCP <= 2.5 s, CLS <= 0.1, INP <= 200 ms, JS <= 120 KB gzip per page, dashboards <= 25 rows. The query count and response time lines are not PRD thresholds (`docs/lms-findings.md` says the old "100 ms" idea is a proposal only); they are reported for information.

## Method

- Scratch SQLite DB (not the real DB): migrated and seeded with `db:seed` + `demo:seed` (8 courses, 1 plan, order 6 pending for `learner.laila@demo.younggo.test`).
- Served with `php -S 127.0.0.1:8097`, 4 workers, `APP_DEBUG=false`, opcache on (a first run without opcache gave TTFB of 0.4 to 2.1 s from per-request framework boot; discarded for the verdicts, kept for reference as `pw_noopcache.json`). A scratch router (outside the repo) boots the real `public/index.php` path and adds `X-Q-Count`, `X-Q-Ms`, `X-App-Ms` headers via `DB::listen`.
- Playwright 1.65.1 (Chromium), viewport 390x844, DPR 3, mobile UA, touch. CDP throttling: 150 ms RTT, 1.6 Mbps down, 750 Kbps up, CPU 4x slowdown, cache disabled. 4 cold loads per page; median reported.
- LCP and CLS from PerformanceObserver (buffered). INP is a **proxy**: max `event` timing duration (threshold 16 ms) over 3 to 7 taps/focus/typing interactions per page (filter details, menu, search typing, buttons). It is not a field INP.
- Checkout page needs auth: logged in as the demo learner who owns order 6. Authenticated pages render in the learner's locale (AR); the public pages rendered EN. AR/RTL public pages were not measured separately.
- Query counts also cross-checked in-process (4 repeated renders per URL): no query repeated 3 or more times on any page (`X-Q-Dup` = 0).
- Caveats: Windows dev server, SQLite, 8 demo courses with 8 KB placeholder PNG covers. Query counts scale with page content, not with row count, so they should hold on a larger catalog; timings and image sizes will not. Numbers are indicative, run once on one machine while other agents were using the CPU.

## Results

| Page | Metric | Value | Budget | Result |
|---|---|---|---|---|
| /courses (catalog) | LCP | 2564 ms (max 3056) | <= 2500 ms | FAIL (marginal) |
| /courses | CLS | 0.107 | <= 0.1 | FAIL |
| /courses | INP proxy | 128 ms (7 interactions) | <= 200 ms | PASS |
| /courses | JS gzip | 8.4 KB (25 KB raw) | <= 120 KB | PASS |
| /courses | Queries / response | 18 queries, 47 ms SQL, 405 ms app (warm) | info | no N+1 |
| /courses?format=live&access=paid&sort=price_asc | LCP | 1808 ms | <= 2500 ms | PASS |
| same | CLS | 0.135 | <= 0.1 | FAIL |
| same | INP proxy | 104 ms | <= 200 ms | PASS |
| same | JS gzip | 8.4 KB | <= 120 KB | PASS |
| same | Queries / response | 18 queries, 33 ms SQL, 418 ms | info | no N+1 |
| /courses/introduction-to-acting | LCP | 2372 ms (max 2396) | <= 2500 ms | PASS (thin margin) |
| same | CLS | 0.008 | <= 0.1 | PASS |
| same | INP proxy | 136 ms | <= 200 ms | PASS |
| same | JS gzip | 8.4 KB | <= 120 KB | PASS |
| same | Queries / response | 21 queries, 198 ms SQL (outlier run), 602 ms | info | no N+1 (in-process: 15 to 16 queries, ~8 ms SQL) |
| /plans | LCP | 1536 ms | <= 2500 ms | PASS |
| same | CLS | 0.006 | <= 0.1 | PASS |
| same | INP proxy | 72 ms (1 interaction) | <= 200 ms | PASS |
| same | JS gzip | 8.4 KB | <= 120 KB | PASS |
| same | Queries / response | 10 queries, 29 ms SQL, 319 ms | info | ok |
| /checkout/orders/6 (auth) | LCP | 2484 ms (max 2780) | <= 2500 ms | PASS on median, 1 of 4 runs over (FAIL risk) |
| same | CLS | 0.006 | <= 0.1 | PASS |
| same | INP proxy | 80 ms (5 interactions) | <= 200 ms | PASS |
| same | JS gzip | 8.4 KB | <= 120 KB | PASS |
| same | Queries / response | 18 queries, 38 ms SQL, 448 ms | info | no N+1 |

Other numbers (all pages): CSS 76 KB, JS 25 KB, **font 845 KB**, total transfer about 0.96 to 1.04 MB per page; TBT proxy 230 to 670 ms; TTFB 325 to 610 ms with opcache.

Overall: **CLS fails on the catalog (2 of 2 variants), LCP is marginal on catalog, course detail and checkout. INP and JS budgets pass everywhere. The criterion is not met yet.**

## Findings

1. **CLS from web-font swap (catalog fail, reproducible).** The shift is recorded when the font arrives (about 7.4 s on throttled 4G): the page h1/intro text and the filter-chip `<li>` row move by 7 to 52 px. Cause: one 845 KB uncompressed TTF (`Noto Sans Arabic`, variable, all weights) loaded with `font-display: swap`, no preload and no metric-matched fallback. Files: `resources/css/tokens.css` lines 7 to 13 (`@font-face`), `resources/fonts/noto-sans-arabic/NotoSansArabic-Variable.ttf`, `resources/views/components/layouts/base.blade.php` (add preload). It also costs about 85% of every page's bytes and delays LCP. Suggested fix: woff2 (Arabic and Latin subsets via `unicode-range`), `<link rel="preload" as="font" crossorigin>`, and a `size-adjust`/`ascent-override` fallback face. Not a bug in filter logic: the same shift appears on the unfiltered catalog and is only masked on fast networks.
2. **Catalog LCP image is lazy-loaded.** The LCP element on `/courses` is the first course card cover, rendered `loading="lazy"` with no `fetchpriority` and no width/height (all 8 imgs lazy). Files: `resources/views/components/learning/course-card.blade.php` line 16 (also `resources/views/catalog/index.blade.php`). Suggested: eager + `fetchpriority="high"` for the first card only (or first row). `aspect-video` is set, so layout does not shift. On the course detail page the hero (`resources/views/catalog/show.blade.php` line 67) and on checkout the summary thumb (`resources/views/checkout/_summary.blade.php` line 8, also lazy) are the LCP element too; the course hero is eager but has no `fetchpriority`.
3. **No responsive/modern image formats.** Covers are served as the original PNG from `/storage/courses/` with no `srcset`, `sizes`, WebP/AVIF (ED-030 asks for responsive WebP/AVIF). The demo files are only about 8 KB, so this is unmeasured at real cover sizes; expect it to dominate LCP with real artwork. Not oversized in the demo data.
4. **No N+1 found.** Catalog 18 queries (12 cards, 8 in demo) and course detail 21 queries are flat across repeats and across content; nothing repeats 3 or more times. Minor, low-cost items: the catalog runs four independent facet queries on every request that could be cached (`age_bands`, distinct `level`, distinct `content_language`, `categories`, instructors subquery) in `app/Http/Controllers/PublicCatalogController.php` lines 51 to 66; course detail loads `curriculum_items` with a four-way `exists` on lessons/quizzes/assignments/live_sessions (`PublicCourseController`). Catalog search uses `search_text LIKE '%q%'` (no index use): check at 1k+ courses.
5. **Server cost depends on opcache.** Without opcache the same pages took 0.4 to 2.1 s TTFB on this dev box, which alone would break LCP. Production must run opcache (and route/config/view caches). The catalog JS is tiny (8.4 KB gzip) and well inside budget.
6. **Static assets not compressed by this server.** CSS 76 KB raw (about 15 KB gzip typical) and the TTF are measured uncompressed here; JS gzip was computed manually. Confirm gzip/brotli at the real host.
7. **Checkout order page** is at the edge for LCP (median 2484 ms, max 2780 ms) mostly from the font plus the lazy summary thumbnail; CLS and INP are fine.

## Not covered / limits

- AR/RTL public pages, dark mode, real (larger) cover images, a 12+ course page (pagination), and the checkout product page `/checkout/{type}/{id}` were not measured.
- No field data; INP is a lab proxy.
- No payment gateway call was made (the Pay action was not exercised).

## Raw data

Scratch files (not in the repo): `pw.json`, `pw_noopcache.json`, `q.json`, `perf.cjs`, `router.php`, `perfcls.cjs` in the session scratchpad directory. Server on 8097 stopped; scratch DB `perf.sqlite` left in the scratchpad.

## Fix batch F (2026-11-19): after numbers

Changes: (a) the 845 KB TTF is no longer served to browsers. Two woff2 subsets (Arabic 146 KB, Latin 29 KB; axes pinned to wdth 100 and wght 300-800) are declared with `unicode-range` in `tokens.css`, the page-language subset is preloaded in `layouts/base.blade.php`, and two `size-adjust` fallback faces (Arial for Latin at 107.7%, Tahoma/Geeza Pro for Arabic at 102.1%, ascent/descent overrides) sit in the font stack. The TTF stays only for PDFs. (b) The first card cover (catalog, wishlist), the first checkout summary thumbnail and the course hero are `fetchpriority="high"` (first two also `loading="eager"`), all card and thumbnail images have width/height, other cards stay lazy. (c) Catalog facet options (categories, age bands, levels, languages, instructors) are cached 5 minutes under `catalog:facets:{locale}:EG` as plain arrays.

Method: same as above (Playwright Chromium, 390x844, DPR 3, mobile UA, CDP 150 ms RTT / 1.6 Mbps / 750 Kbps, CPU 4x, cache disabled, 4 cold loads, median), scratch SQLite with `demo:seed`, `php -S` with opcache on port 8199 (8099 was occupied by other agents' servers), file cache store. Local machine, other agents running; indicative only. The "before" column is the table above (measured on 8097 with the same method).

| Page | Metric | Before | After | Budget |
|---|---|---|---|---|
| /courses | LCP | 2564 ms | 1760 ms (runs 1748 to 2012) | <= 2500 ms, PASS |
| /courses | CLS | 0.107 | 0 (all 4 runs) | <= 0.1, PASS |
| /courses?format=live&access=paid&sort=price_asc | LCP | 1808 ms | 1576 ms | PASS |
| same | CLS | 0.135 | 0 | PASS |
| /ar/courses (not measured before) | LCP / CLS | n/a | 2172 ms / 0 | PASS (thin margin) |
| /courses | transfer per page | about 1.0 MB (font 845 KB) | about 350 KB, of which fonts 174 KB (Latin 29 KB + Arabic 146 KB; the Arabic subset is also fetched on EN pages because the language switcher shows Arabic text) | info |

Not re-measured: course detail and checkout LCP (changes there are the hero `fetchpriority` and the summary thumbnail eager/priority), INP and JS (unchanged, 8.4 KB gzip). Catalog queries: the five facet queries are skipped on a warm cache; the per-request count was not re-recorded. Note: a first run of this measurement used a router that did not serve static files (fonts returned the 404 page) and gave meaningless numbers; it was discarded and redone with Laravel's `server.php` router.

## Course detail and order remeasurement (2026-11-19)

Revision `4bbdcc7`, Playwright 1.65.1 Chromium. Same 390 × 844 mobile
viewport, DPR 3, mobile UA, 150 ms RTT, 1.6 Mbps down, 750 Kbps up, CPU
4× and four cold browser loads per page. The Windows PHP server used OPcache
and separate seeded SQLite databases for the two runs. Checkout was signed in as
the demo learner Laila. Both measured responses were 200 and the priority
images decoded. CLS uses the maximum session window and excludes recent input.

| Run | Page | LCP samples (ms) | Median LCP | Median CLS | Lab budget |
|---|---|---|---|---|---|
| With concurrent Pest workers | `/courses/introduction-to-acting` (EN) | 2928, 1796, 2552, 3200 | 2740 ms | 0 | LCP exceeds budget |
| With concurrent Pest workers | `/checkout/orders/6` (AR) | 1940, 1840, 1632, 1728 | 1784 ms | 0.00015 | Within LCP/CLS budgets |
| After the Feature workers finished | `/courses/introduction-to-acting` (EN) | 1724, 1696, 1536, 1500 | 1616 ms | 0 | Within LCP/CLS budgets |
| After the Feature workers finished | `/checkout/orders/6` (AR) | 1264, 1284, 1384, 1528 | 1334 ms | 0.00015 | Within LCP/CLS budgets |

The course hero remained the LCP element. Course detail TTFB reached 1445 ms
under concurrent test load and 464 ms in the second run. These two sets measure
local lab behavior with demo covers. Production capacity and field INP remain
unverified. The reports `remeasured.json` and `quiet.json` are in the local
performance scratch directory. INP and JS were outside this remeasurement.
