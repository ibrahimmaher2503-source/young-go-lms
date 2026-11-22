# Tests prompt — T-ADMIN-DASH-V2 (admin overview dashboard redesign)

Write Pest tests only. Do NOT change production code. Run with `php -d memory_limit=2G vendor/bin/pest`.
Use `RefreshDatabase`, seed `RolePermissionSeeder`, `$this->withoutVite()`, `Config::set('reports.dashboard_cache_seconds', 0)` (see tests/Feature/Filament/G7FixesDateDashboardCssTest.php for the pattern).

## What changed
- `app/Filament/Pages/WorkspaceDashboard.php`: `buildSummary()` no longer adds the net-revenue item (and lost its `$kpis` param). Summary strip is rendered with `'variant' => 'tiles'`.
- `resources/views/filament/shared/summary-strip.blade.php`: optional `variant` prop → adds `is-tiles` class; without it markup is unchanged (list pages via `WorkspaceListRecords`).
- `resources/views/filament/staff/dashboard-kpis.blade.php`: x-axis label density, empty-KPI cell class, all-clear queue rows (compact, chevron, no "0" pill).
- `resources/views/filament/staff/dashboard-links.blade.php`: label span class + `title`.
- `resources/css/filament-theme.css`: appended dashboard-scoped block.

## Acceptance to cover
- A1 Admin `/admin`: summary strip has class `is-tiles`; contains learners/courses/open-help items; does NOT contain the revenue summary item; `data-kpi="revenue"` still present in the KPI row.
- A2 A list page using `WorkspaceListRecords` renders the summary strip WITHOUT `is-tiles`.
- A3 All queues empty: `data-queues-clear` present, every queue has `data-queue-link`, no `data-queue=` attribute, no `yg-attention-pill is-zero` text "0" in the clear list.
- A4 One pending queue (e.g. a pending manual payment proof): that row has `data-queue` + `data-queue-count` > 0 and class `is-pending`; `data-queues-clear` absent.
- A5 Chart period: Livewire `setChartDays(7|30|90)` sets `chartDays`; invalid value (e.g. 15) falls back to 30.
- A6 CSS file still contains `.yg-summary-strip.is-tiles` rule with `--yg-dash-pad` defined on it, `.yg-overview-metrics` with `flex-wrap: wrap`, quick-access grid using `auto-fit`, and the existing `.fi-ta .yg-date`/`.yg-ai-table`/`.yg-ai-scroll` rules.
- A7 Non-admin panel (instructor) dashboard still renders (no `is-tiles` regression errors) and shows its own summary items.

SQLite note: tests run on SQLite; avoid DB-specific date functions in fixtures.
