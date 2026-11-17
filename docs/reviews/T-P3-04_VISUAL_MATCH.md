# T-P3-04 visual match record

Reference: `docs/design-system/reference-pack-2026-11-17/03-curriculum-builder.png`. Captures: `tests/Browser/screenshots/T-P3-04/` (AR/EN, 390 and 1440, axe clean, no horizontal scroll).

Matched: staff shell; page title with the course name beneath it; "Add lesson" primary action and "Student preview" secondary action in the header; one "Course content" card with numbered section cards (title, lesson count and total minutes); numbered lesson rows with a drag handle, duration, type and a status badge (published/draft); logical RTL/LTR layout; archived lessons listed apart.

Differences that remain, with the reason:
- The reference's right-hand "Lesson settings" panel (title, type, duration, curriculum PDF, assistant readiness) is a modal here (Edit). The PDF and readiness fields belong to T-P3-08/T-P3-09; an empty slot (`filament.courses.curriculum-readiness`) is wired for the readiness indicator.
- The reference's per-row clipboard and video icons and the "…" overflow menu are replaced by text actions (move up/down, edit, archive) so every action is keyboard- and screen-reader-reachable.
- Section drag handles are not offered: sections move with their up/down buttons, lessons move by drag or by buttons.
- Pixel-level typography and spacing are not claimed equal. No independent visual score has been run.

## T-P3-06 addendum (lesson editor)

Captures: `tests/Browser/screenshots/T-P3-06/lesson-editor-*` (AR/EN, 390 and 1440, axe clean). The lesson editor is the same modal as in T-P3-04, now with description, media source (type-appropriate choices), file upload with Filament's progress bar, link and text fields, and the protection tier and media status badges on each lesson row. Differences from the reference stay as listed above: the reference shows a permanent side panel instead of a modal, and the curriculum PDF and assistant-readiness rows belong to T-P3-08 and T-P3-09. Automatic duration detection is not offered (no media service is connected); the author enters minutes.
