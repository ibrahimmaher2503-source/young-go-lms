# T-G7-FIXES-A regression checks (D1, D2, D3, D7, D8, D9, D10)

Write Pest tests only; no application code changes. Run per folder with `php -d opcache.enable_cli=0 vendor/bin/pest -d extension=sockets -d memory_limit=2G <folder>`.

## D1 dashboards under a serialising cache (CRITICAL)
- Set `Config::set('cache.default', 'file')` (and `cache.stores.file.path` to a temp dir), `reports.dashboard_cache_seconds` > 0. Sign in as admin, finance and instructor (2FA satisfied as in existing tests); GET `/admin`, `/finance`, `/instructor` twice each: both responses are 200 (the second is a cache hit) and `data-kpi` tiles are identical.
- `Cache::get('dashboard:v2:admin:'.$user->id)` is an array of arrays (no objects). `DashboardKpi::fromArray($kpi->toArray())` equals the original for count, percent and money (with `secondary`).
- Poison the key with `Cache::put($key, [new stdClass])`, then `Cache::put($key, 'x')`, then `Cache::put($key, [['key' => 'x']])`: the dashboard still returns 200 and rebuilds (treated as a miss). `DashboardKpi::fromArray(null|'x'|[])` returns null.
- `serializable_classes` stays `false` in config/cache.php. Grep guard: no `Cache::remember/put` in `app/` stores an object (only arrays, strings, scalars: ReviewSummary array, sitemap string, CircuitBreaker scalars).

## D2 KPI strip at 390 (browser or CSS assertion)
- At 390px on `/admin`, `/finance`, `/instructor` and the admin instructor ledger (`.yg-summary-strip`): `getComputedStyle(.yg-kpi-strip).gridTemplateColumns` has 2 tracks; no item `scrollWidth > clientWidth`; label font-size >= 13px; no horizontal page scroll. Odd last tile spans both columns. At 1440 it is 3 columns.
- Same in AR (dir=rtl).

## D3 learner bottom nav
- At 390 on `/my`, `/my/payments`, `/my/subscription`, `/checkout/*` (AR and EN): `[data-bottom-nav] li` all share one `getBoundingClientRect().top` (single row), nav height < 80px, 5 items, label font-size 12px, labels truncated not wrapped, every link has a `title` equal to its label. Parent role shows the parent item set in one row too.

## D7 bidi isolation
- Checkout order page, fake gateway page, `/my/payments`, finance reconciliation table: order reference is wrapped in `<bdi dir="ltr" class="yg-ltr">` (component `x-ui.ltr`) in AR; `.yg-ltr` computed `unicode-bidi` is `isolate`. Receipt PDF HTML already uses `.num` with `unicode-bidi: isolate` (unchanged). Blade render test of `<x-ui.ltr>` escapes HTML in the slot.

## D8 locale-aware display name
- `User` with `name_ar='ليلى'`, `name_en='Layla Demo'`, `display_name=null`: `display_name` is 'Layla Demo' under `app()->setLocale('en')`, 'ليلى' under `ar`. With only `name_ar` set, EN falls back to it; with neither, null (callers fall back to `name`). An explicit `display_name` always wins. `getRawOriginal('display_name')` stays null (the profile form prefill uses it; saving profile with an empty field keeps it null).
- After `demo:seed`, `/finance` in EN greets 'Welcome, Demo Finance' (no Arabic characters), in AR the Arabic name; `/checkout/order` EN shows 'Layla Demo'. Demo users have both `name_ar` and `name_en` set and `display_name` null.

## D9 empty states
- Admin refunds, settlements, statements, ledger, instructor my-statements and my-earnings with no rows render `.yg-empty-state` (from `x-ui.empty-state`) with an icon, title and hint, `data-empty`, and not Filament's default empty heading. EN titles are sentence case: 'No refunds', 'No settlement batches', 'No instructor statements', 'No ledger entries'. AR titles present. `/admin/reports` with no rows shows `[data-report-empty]` as an `x-ui.empty-state`.

## D10 brand spelling and report dates
- `grep -rn "يانغ\|يونغ" lang/` has no hits; `lang/ar/settings.php` title is 'إعدادات يونج جو'; `app:check-translations` passes.
- Reports page (AR and EN): the caption 'Showing/يعرض ... ' and datetime cells use `d/m/Y` (`ReportLabels::display('2026-10-19') === '19/10/2026'`, with time `'19/10/2026 14:30'` for datetime columns), text date columns (completed_on) too; non-dates are untouched; CSV export keeps ISO.

## Gates
Arch, Feature/Invariants, Feature/Filament, Feature/Reports, Feature/Demo green; `app:check-translations`, `app:check-rtl`, `app:check-brand-tokens`, phpstan (`--memory-limit=2G --debug`), `npm run build`.
