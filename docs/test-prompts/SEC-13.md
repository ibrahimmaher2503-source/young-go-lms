# SEC-13: Help Requests SLA Column Must Have Non-Empty Accessible Link Text

## Context
- **Finding:** P2 Demo (`DEMO_REHEARSAL_2026-11-20.md`) — Related tasks `T-P7-04`, `T-P9-11`.
- **Fixed file:** `app/Filament/Resources/HelpRequests/HelpRequestResource.php` (`table()`).

## Reproduction / Bug Scenario
On `/instructor/help-requests` (with `recordUrl` enabled on every row), `TextColumn::make('overdue')` returned `null` for non-overdue requests without a `placeholder(...)`, producing an empty `<a href="...">` inside `.fi-ta-cell-overdue > .fi-ta-col` that failed `axe` rule `link-name`.

## Regression Test Requirements (write in `tests/Feature/AI/` or `tests/Browser/`)
1. Seed a non-overdue `HelpRequest` visible to an instructor/admin.
2. Render `/instructor/help-requests` (or `ListHelpRequests` Livewire/browser page).
3. Assert the `.fi-ta-cell-overdue` cell contains non-empty link text (`-`) when not overdue, and `Overdue` badge text when overdue, with zero `axe` `link-name` violations.
