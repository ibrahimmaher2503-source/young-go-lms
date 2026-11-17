# T-P3-02 visual match record

Reference: `docs/design-system/reference-pack-2026-11-17/13-course-wizard-v2.png`. Implementation captures: `tests/Browser/screenshots/T-P3-02/` (Basic and Information, AR/EN, 390 and 1440, axe clean, no horizontal scroll).

Matched after review: staff shell and sidebar from the shared workspace; stepper with the active step highlighted; one "Basic details" card holding the AR/EN tabs with title, address (slug), short and long description per language; required markers on title, category, level, content language and format; two-column field grid; primary "Save draft" button in the page header; "Setup progress" side card driven by the saved completion flags; Next/Back/Cancel actions.

Differences that remain, with the reason:
- The reference stepper shows seven steps and a "Pre-publish checklist" card: steps 3 to 7 (pricing, media, SEO, custom fields, finish) belong to T-P3-03 and are not built; showing them would be dead controls.
- "Preview" button: preview ships with the Finish step (T-P3-03).
- Sub-category is one tree select (parent › child) instead of two selects; the category tree is the existing T-P2-01 model.
- Age suitability is minimum and maximum age; the age-band selector waits for the approved cutoffs (O-015).
- Ownership select in the reference is a single combined control; here ownership and owner instructor are separate and visible to admin only (PRD 9.2).
- Pixel-level typography and spacing are not claimed equal; the shared tokens and Filament theme are used. No independent visual score has been run.
