# SEC-15: Ensure AA Contrast in View-As Modal Description, Helper Text, and Submit Button

## Context
- **Finding:** P2 Demo (`DEMO_REHEARSAL_2026-11-20.md`) — Related tasks `T-P9-10`, `T-P9-11`.
- **Fixed file:** `resources/css/filament-theme.css`.

## Reproduction / Bug Scenario
Opening `LearnerThreeSixtyPage` in `AR 1440` and triggering `startViewAs` modal caused `axe` `color-contrast` violations on:
1. `.fi-modal-description` (overridden by Filament's 0-4-0 nested `text-gray-500` rule using `--yg-neutral-500`).
2. Form field helper text inside `.fi-modal-window` (using `text-gray-500` / `--yg-neutral-500`).
3. The primary submit button (overridden by Filament's 0-5-1 `.fi-btn:not(.fi-outlined).fi-color:not(label):not([aria-pressed])` rule setting `--bg` / `--text` from `--primary-400` / `--primary-900`).

## Regression Test Requirements (write in `tests/Browser/`)
1. Sign in as admin, open `/admin/learner-360?learner=<omar_id>&lang=ar` at `1440x900` in light mode, and click the `startViewAs` action button to open the modal.
2. Run `axe` scoped to `.fi-modal-window` (or the page) and assert zero `color-contrast` violations on `.fi-modal-description`, field helper text, and `.fi-btn.fi-color-primary`.
