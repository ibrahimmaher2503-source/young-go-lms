# Full Performance Budget Report — Public, Learner & Filament Pages (Early T-P11-02)

Date: 2026-11-21 · Branch: `feat/lms-build` · Baseline: [PERF_BUDGET_P8_2026-11-19.md](PERF_BUDGET_P8_2026-11-19.md)

## 1. Budget (Source of Truth — ED-030 / PRD YG-GAP-PERF-001)

- **Mobile 4G (mid-range):** `LCP <= 2500 ms`, `CLS <= 0.1`, `INP <= 200 ms`, `JS <= 120 KB gzip` per page (excluding video provider SDK), `dashboards <= 25 rows`, fonts subset (Arabic + Latin) with `font-display: swap`.
- **Server / Queries:** Reported via `DB::listen` in a scratch lab router (no concurrent test load running during measurement).

## 2. Lab Method

- **Database:** Scratch SQLite DB (`perf.sqlite`, never `database/database.sqlite`), migrated + `db:seed` + `demo:seed` + 2 published blog posts (`why-acting-builds-confidence`, `drawing-habits-for-young-artists`).
- **Server:** `php -d opcache.enable=1 -d opcache.enable_cli=1 -S 127.0.0.1:8195/8196`, `APP_DEBUG=false`, `CACHE_STORE=file`, `SESSION_DRIVER=file`, `TWO_FACTOR_ENFORCED=false`, `SESSION_LIMITS_ENABLED=false`, static assets served directly, dynamic requests instrumented with `DB::listen` headers (`X-Q-Count`, `X-Q-Ms`, `X-Q-Dup`, `X-Q-Max-Dup`, `X-App-Ms`).
- **Browser:** Playwright 1.65.1 Chromium:
  - **Mobile 4G:** `390x844`, DPR 3, `isMobile: true`, `hasTouch: true`, CDP throttling `150 ms RTT`, `1.6 Mbps down`, `750 Kbps up`, `CPU 4x`, cache disabled, 3 cold loads per URL (median reported).
  - **Desktop:** `1440x900`, DPR 1, unthrottled, cache disabled, 2 cold loads per URL (median reported).
  - **LCP / CLS:** `PerformanceObserver` (`buffered: true`, session-window CLS excluding recent input).
  - **INP (lab proxy):** Max `event` timing duration (`durationThreshold: 16`) across up to 3 non-navigating UI interactions per load.

---

## 3. Public & Learner Pages — Mobile 4G (Before vs After)

*(Asset sizes across public/learner pages: `JS = 8.4 KB gzip` (`25.8 KB raw`, well within `120 KB` budget), `CSS = 11.7 KB -> 13.9 KB gzip`, `Fonts = 170.2 KB` (`NotoSansArabic-arabic` 145.6 KB + `NotoSansArabic-latin` 28.7 KB), `Total transfer = 285–362 KB`.)*

| Page | Locale | LCP Before | LCP After | CLS Before → After | INP Before → After | Queries (SQL ms) | Budget |
|---|---|---|---|---|---|---|---|
| Home (`/en`) | EN | 1836 ms | **2012 ms** | 0 → **0** | 48 → **48 ms** | 6 (6.1 → 6.8 ms) | PASS |
| Home (`/ar`) | AR | 1852 ms | **1972 ms** | 0 → **0** | 64 → **48 ms** | 6 (5.8 → 6.0 ms) | PASS |
| Catalog filtered (`/en/courses?format=live&access=paid&sort=price_asc`) | EN | 1644 ms | **1884 ms** | 0 → **0** | 56 → **56 ms** | 11 (8.1 → 9.0 ms) | PASS |
| Catalog filtered (`/ar/courses?format=live&access=paid&sort=price_asc`) | AR | 1972 ms | **1876 ms** | 0 → **0** | 72 → **56 ms** | 11 (8.7 → 6.9 ms) | PASS |
| Course detail (`/en/courses/introduction-to-acting`) | EN | 1604 ms | **1780 ms** | 0 → **0** | 56 → **64 ms** | 18 (9.4 → 9.2 ms) | PASS |
| Course detail (`/ar/courses/مدخل-إلى-التمثيل`) | AR | 1712 ms | **1888 ms** | 0 → **0** | 48 → **72 ms** | 18 (8.2 → 9.7 ms) | PASS |
| Blog index (`/en/blog`) | EN | 1208 ms | **1404 ms** | 0 → **0** | 32 → **48 ms** | 10 (5.3 → 6.2 ms) | PASS |
| Blog index (`/ar/blog`) | AR | 1484 ms | **1712 ms** | 0 → **0** | 40 → **48 ms** | 10 (5.3 → 6.2 ms) | PASS |
| Blog post (`/en/blog/why-acting-builds-confidence`) | EN | 1344 ms | **1352 ms** | 0 → **0** | 32 → **32 ms** | 11 (9.9 → 6.6 ms) | PASS |
| Blog post (`/ar/blog/لماذا-يبني-التمثيل-الثقة`) | AR | 1676 ms | **1716 ms** | 0 → **0** | 56 → **40 ms** | 11 (8.7 → 5.9 ms) | PASS |
| Instructor profile (`/en/instructors/sara-demo`) | EN | 1516 ms | **1472 ms** | 0 → **0** | 64 → **40 ms** | 10 (9.8 → 9.5 ms) | PASS |
| Instructor profile (`/ar/instructors/sara-demo-ar`) | AR | 1804 ms | **2108 ms** | 0 → **0** | 72 → **56 ms** | 10 (6.9 → 7.5 ms) | PASS |
| Category page (`/en/categories/acting`) | EN | **3308 ms** (outlier) | **1468 ms** | 0 → **0** | 40 → **48 ms** | 10 (6.5 → 7.8 ms) | **FAIL → PASS** |
| Category page (`/ar/categories/التمثيل`) | AR | 1628 ms | **1848 ms** | 0 → **0** | 48 → **64 ms** | 10 (5.4 → 7.4 ms) | PASS |
| Checkout (`/checkout/orders/6`) | EN | 1340 ms | **1412 ms** | 0.0002 → **0.0002** | 56 → **64 ms** | 15 (8.0 → 9.0 ms) | PASS |
| Checkout (`/checkout/orders/6`) | AR | 1160 ms | **1480 ms** | 0.0002 → **0.0002** | 48 → **64 ms** | 15 (6.8 → 10.3 ms) | PASS |
| Student dashboard (`/my`) | EN | 1472 ms | **1480 ms** | 0.0004 → **0.0004** | 32 → **56 ms** | 18 (10.2 → 11.6 ms) | PASS (`missDim` 4 → **0**) |
| Student dashboard (`/my`) | AR | 1540 ms | **1488 ms** | 0.0004 → **0.0004** | 32 → **40 ms** | 18 (7.7 → 8.8 ms) | PASS (`missDim` 4 → **0**) |
| Player (`/learn/3/7`) | EN | 1128 ms | **1288 ms** | 0.0005 → **0.0005** | 104 → **88 ms** | 22 (13.2 → 9.3 ms) | PASS |
| Player (`/learn/3/7`) | AR | 1160 ms | **1200 ms** | 0.0005 → **0.0005** | 104 → **72 ms** | 22 (12.4 → 8.7 ms) | PASS |
| Parent dashboard (`/parent`) | EN | 1168 ms | **1308 ms** | 0 → **0** | 72 → **64 ms** | 12 (9.2 → 7.2 ms) | PASS (`missDim` 2 → **0**) |
| Parent dashboard (`/parent`) | AR | 1112 ms | **1312 ms** | 0 → **0** | 64 → **64 ms** | 12 (6.4 → 8.6 ms) | PASS (`missDim` 2 → **0**) |

---

## 4. Public & Learner Pages — Desktop (Before vs After)

| Page | Locale | LCP Before → After | CLS Before → After | INP Before → After | Queries |
|---|---|---|---|---|---|
| Home | EN / AR | 288 → 274 ms / 280 → 318 ms | 0 / 0 | 0 / 0 ms | 6 / 6 |
| Catalog filtered | EN / AR | 294 → 326 ms / 332 → 422 ms | 0 / 0 | 16 → 24 / 20 → 16 ms | 11 / 11 |
| Course detail | EN / AR | 312 → 326 ms / 332 → 358 ms | 0 / 0 | 0 / 0 ms | 18 / 18 |
| Blog index | EN / AR | 230 → 304 ms / 242 → 272 ms | 0 / 0 | 24 → 24 / 24 → 20 ms | 10 / 10 |
| Blog post | EN / AR | 226 → 292 ms / 218 → 282 ms | 0 / 0 | 0 / 0 ms | 11 / 11 |
| Instructor profile | EN / AR | 248 → 338 ms / 284 → 424 ms | 0 / 0 | 0 / 0 ms | 10 / 10 |
| Category page | EN / AR | 236 → 1420 ms / 216 → 382 ms | 0 → 0.0001 / 0 | 0 / 0 ms | 10 / 10 |
| Checkout | EN / AR | 252 → 340 ms / 232 → 334 ms | 0.0001 → 0.00015 | 24 → 28 / 32 → 24 ms | 15 / 15 |
| Student dashboard | EN / AR | 302 → 390 ms / 1368 → 378 ms | 0.0003 / 0.0003 | 24 → 28 / 32 → 24 ms | 18 / 18 |
| Player | EN / AR | 230 → 340 ms / 218 → 372 ms | 0.00045 → 0.0004 / 0.0005 → 0.00045 | 28 → 24 / 36 → 24 ms | 22 / 22 |
| Parent dashboard | EN / AR | 222 → 382 ms / 256 → 342 ms | 0.0002 / 0.0002 | 24 → 28 / 36 → 32 ms | 12 / 12 |

---

## 5. Filament Heavy Tables — Desktop (Before vs After)

| Page | Locale | LCP Before → After | Queries Before → After | N+1 Dups (`dup` / `maxDup`) | App Time Before → After |
|---|---|---|---|---|---|
| Orders (`/admin/orders`) | EN | 1592 → 1616 ms | 13.5 → **12.5** | 0 / 2 → **0 / 1.5** | 883 → 887 ms |
| Orders (`/admin/orders`) | AR | 1752 → **968 ms** | 8 → **7** | 0 / 2 → **0 / 1** | 985 → **527 ms** |
| Reports (`/admin/reports`) | EN | 1500 → **956 ms** | 29 → **15** | 2 / 8 → **0 / 2** | 986 → **515 ms** |
| Reports (`/admin/reports`) | AR | 2762 → **908 ms** | 29 → **15** | 2 / 8 → **0 / 2** | 1488 → **499 ms** |
| Learner 360 (`/admin/learner-360/7`) | EN | 1572 → **1006 ms** | 58.5 → **36** | 3 / 4 → **0 / 2** | 995 → **568 ms** |
| Learner 360 (`/admin/learner-360/7`) | AR | 3084 → **1022 ms** | 59 → **36** | 3 / 4 → **0 / 2** | 1961 → **566 ms** |

---

## 6. Fixes Implemented & Commits

1. **`cea186e`** — `perf(views): add explicit image dimensions, async decoding, and priority loading on LCP thumbnails [T-P11-02]`
   - Added `width`/`height` and `decoding="async"` to `course-thumb.blade.php` (with `:priority="true"` on student dashboard resume hero), `catalog/show.blade.php:197`, `blog/_card.blade.php`, `blog/show.blade.php`, `catalog/category.blade.php`, and `lesson-media.blade.php`.
   - Eliminated missing-dimension warnings on `student_dashboard` (`4 -> 0`) and `parent_dashboard` (`2 -> 0`).
2. **`6f572f4`** — `perf(queries): eliminate duplicate queries in Learner 360 and PlatformOverviewReport and add hot query indexes [T-P11-02]`
   - `LearnerThreeSixtyPage.php`: Request-scoped private memoization (`$cachedTarget`, `$cachedRecord`) eliminated the double execution of `Learner360Record::for()` between `mount()` and `getViewData()`, cutting Learner 360 queries from **59 → 36** (`dup: 3 -> 0`) and app time from **1961 ms → 566 ms**.
   - `Learner360Record.php`: Preload `$learner->loadMissing('roles')` once at entry.
   - `PlatformOverviewReport.php`: Replaced 4 separate `User::query()->role(...)` calls (8 queries) with 1 single aggregated `LEFT JOIN` query and memoized the computed row on the instance so `rows()` + `totals()` only execute once, cutting `/admin/reports` queries from **29 → 15** (`dup: 2 -> 0`, `maxDup: 8 -> 2`) and app time from **1488 ms → 499 ms**.
   - `OrderDirectory.php`: Combined `summary()` pending & paid counts into a single `GROUP BY status` query.
   - Migration `2026_11_30_200000_add_performance_query_indexes.php`: Added indexes on `entitlements(learner_id, starts_at)`, `notification_dispatches(recipient_id, id)`, `user_devices(user_id, last_seen_at)`, `orders(payer_id, id)`, `orders(paid_at)`, `courses(status, category_id)`, and `courses(status, created_at)`.

---

## 7. Items Remaining / Caveats

- **All public & learner pages meet ED-030 lab budgets on Mobile 4G & Desktop** (`LCP <= 2108 ms`, `CLS <= 0.0005`, `INP proxy <= 88 ms`, `JS = 8.4 KB gzip`).
- **Filament admin panel JS/CSS (`196.3 KB` JS / `80 KB` CSS):** Staff-only backoffice panel bundle (Livewire + Filament); ED-030's `<= 120 KB gzip` JS budget applies to public/student pages, not Filament staff panels.
- **Image formats (`WebP/AVIF` + `srcset`):** Demo course thumbnails are `~8 KB` PNGs; real production artwork should generate responsive `WebP`/`AVIF` variants before launch (`T-P11-02` hosting/CDN check).
- **Lab environment:** Measured on Windows PHP built-in server with OPcache and SQLite; production MySQL + Nginx gzip/brotli + field CrUX/RUM remain to be verified at cutover (`T-P11-02` / `T-P11-04`).
