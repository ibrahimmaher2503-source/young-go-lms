# T-P3-09 visual match (G7)

Screen: PDF text approval (`/admin|instructor/courses/{id}/curriculum/documents/{document}`) and the readiness badge in the curriculum manager rows.

Reference: `docs/design-system/reference-pack-2026-11-17/03-curriculum-builder.png` and the component board `18-shared-component-library-v2.png`. The reference pack has no approval screen, so this page is built from the same tokens and components (card surface, border, radius, primary button, rounded badge) and is not a pixel match of a mock.

Captured (tests/Browser/screenshots/T-P3-09, AR/EN at 390 and 1440, axe clean, direction checked):
- Two columns at 1440 (PDF left, extracted text right; mirrored in AR), one column at 390.
- Status badge with the readiness colour dot, version, quality, approve button first in reading order.
- Per page: quality chip, OCR flag when the page was read by OCR, editable text, save button.

Known differences: the PDF pane shows the browser's own PDF viewer, which is empty in the test because the fixture file is not a real PDF. Page images are not rendered server side (no renderer installed), so the side-by-side uses the PDF itself.
