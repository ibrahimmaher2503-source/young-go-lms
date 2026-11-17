# T-P3-04 visual match record

Reference: `docs/design-system/reference-pack-2026-11-17/03-curriculum-builder.png`. Captures: `tests/Browser/screenshots/T-P3-04/` (AR/EN, 390 and 1440, axe clean, no horizontal scroll).

Matched: staff shell; page title with the course name beneath it; "Add lesson" primary action and "Student preview" secondary action in the header; one "Course content" card with numbered section cards (title, lesson count and total minutes); numbered lesson rows with a drag handle, duration, type and a status badge (published/draft); logical RTL/LTR layout; archived lessons listed apart.

Differences that remain, with the reason:
- The reference's right-hand "Lesson settings" panel (title, type, duration, curriculum PDF, assistant readiness) is a modal here (Edit). The PDF and readiness fields belong to T-P3-08/T-P3-09; an empty slot (`filament.courses.curriculum-readiness`) is wired for the readiness indicator.
- The reference's per-row clipboard and video icons and the "…" overflow menu are replaced by text actions (move up/down, edit, archive) so every action is keyboard- and screen-reader-reachable.
- Section drag handles are not offered: sections move with their up/down buttons, lessons move by drag or by buttons.
- Pixel-level typography and spacing are not claimed equal. No independent visual score has been run.
