# T-G7-FIXES-D: tests prompt (G7-P8 review 3 fixes)

Write Pest tests only; do not change application code. Run per folder, each under 10 minutes: `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest <path>`.

## Behaviour to cover
1. `PriceFormatter::formatDeduction`: AR returns `U+2066 "-50.00" NBSP U+2067 "ج.م." U+2069 U+2069` (sign next to the number, currency isolated RTL); EN returns `-EGP 50.00`; a negative `Money` and its absolute value give the same text; the market currency must still match (InvalidArgumentException otherwise).
2. `x-ui.price` with `negative`: output is one `<bdi dir="ltr">` with `yg-ltr whitespace-nowrap`; without `negative` it carries `whitespace-nowrap`. Checkout summary discount line (`data-discount`) shows the sign adjacent to the number in AR and EN.
3. `RefundResource::money()` with a negative minor value uses the deduction format; positive values are unchanged. Ledger and statements tables show reversals through it.
4. Checkout order page: `data-secure-order` badge present on the order step; in the DOM the `data-checkout-summary` section carries `order-first` and `lg:order-none` (mobile summary first); pay form still last.
5. Reports page: the From/To fields are text inputs showing d/m/Y (`#report-from` value `21/10/2026`), no `type="date"` on the visible input; typing `05/11/2026` and committing sets the Livewire property `from` to `2026-11-05`; the `data-range-caption` equals the input values; `datetime` cells are shown in the market timezone (Egypt default: a paid_at of `2026-11-18 23:54:00` UTC renders `19/11/2026 01:54`) and the column header reads `Paid at (Africa/Cairo)` instead of `(UTC)`; the CSV export stays UTC.
6. `ReportFilters::timezoneName()` returns `Africa/Cairo` with no market and the market zone otherwise.
7. Filament tables: a `span.yg-filter-state[data-active-filters="0"]` is rendered with no filters and `="1"` with one active filter (hook `filament.shared.table-filter-state`).
8. `DemoSeeder`: no course `short_description` (AR or EN) contains `Demo course` or `دورة تجريبية`; every demo course has `cover_image` `courses/demo-<key>.png` and the file exists on the public disk; `demo:reset` removes those files; the counts asserted in `DemoDatasetAndResetTest` still hold.
9. `ui.tabs` underline variant renders `flex-nowrap` and `overflow-x-auto` (no `flex-wrap`); the pills variant keeps `flex-wrap`.
10. Learner layout bottom nav (`data-bottom-nav`): labels come from `pages.tab_*` / `refunds.nav_payments` keys, no `truncate` class; AR and EN key parity (`app:check-translations`).

## Visual checks (Browser tests, manual or Playwright, 390 and 1440, EN and AR)
- Admin, finance, instructor dashboards and ledger KPI tiles: no value splits inside a number or currency, labels show at least two readable lines, no horizontal page scroll at 390.
- Refunds, ledger, statements, notification templates: no mid-word or mid-number wrapping; tables scroll inside their container.
- Course page EN 390: tabs stay on one row and scroll sideways.
