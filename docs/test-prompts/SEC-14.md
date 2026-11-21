# SEC-14: Make Reports Scrollable Table Region Keyboard-Focusable

## Context
- **Finding:** P2 Demo (`DEMO_REHEARSAL_2026-11-20.md`) — Related tasks `T-P9-02`, `T-P9-11`.
- **Fixed file:** `resources/views/filament/reports/index.blade.php`.

## Reproduction / Bug Scenario
Opening `/admin/reports` at 390px mobile width renders `<div class="yg-ai-scroll">` with `overflow-x: auto` around the report table, which failed `axe` rule `scrollable-region-focusable` because the scrollable container had no `tabindex="0"` or accessible region label.

## Regression Test Requirements (write in `tests/Feature/` or `tests/Browser/`)
1. Open `/admin/reports` as an authorized admin at `390x844` (or inspect rendered view when rows are present).
2. Assert `<div class="yg-ai-scroll"` has `tabindex="0"`, `role="region"`, and a non-empty `aria-label`.
3. Run `axe` at `390x844` and assert zero `scrollable-region-focusable` violations.
