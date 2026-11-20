# T-P4-06 lesson PDF, player and public preview

References: `07-recorded-player-ai-v2.png` (desktop recorded player) and `17-mobile-lesson-assistant.png` (mobile lesson player) in the 2026-11-17 reference pack, plus `06-public-course-detail-v2.png` as context for previews.

## Resolved visual & functional gaps (2026-11-20)
1. **Desktop video-to-materials gap (~69px -> ~16px):** Reordered `[data-player]` as a `flex flex-col gap-4` container with `#lesson-materials` (`order-3`) placed directly beneath `[data-player-stage]` (`order-1 lg:order-2`) ahead of the toolbar (`order-4`) and shortcuts hint (`order-5`), embedded `[data-course-progress]` and `<x-learning.curriculum-list embedded>` inside one unified sidebar surface card, wrapped the lesson column in a desktop surface card, and eliminated double tabpanel padding in `.lesson-materials`.
2. **Mobile compact header & stage-first order:** Passed `$back` to `<x-layouts.learner>` so `< lg` shows `[data-mobile-back]` cleanly without crowded bell/locale/account buttons, hid the desktop course header on mobile (`sr-only lg:not-sr-only`), and ordered `[data-player-stage]` (`order-1`) before the mobile lesson pill/badge/heading header (`order-2`).
3. **Eliminated video loading spinner:** Switched `<video>` in `<x-learning.lesson-media>` to `preload="auto"`, added HTTP `Range` (`206 Partial Content`) / `Content-Length` local file delivery via `response()->file(...)` in `MediaPlaybackController`, and updated `CoursePlayerBrowserTest` to wait for `canplaythrough` (`readyState >= 4`) before screenshotting. Added `dir="ltr"` on `<select data-player-speed>` so `1×` keeps proper numeric ordering in RTL.
4. **Public lesson preview parity (`catalog/preview.blade.php` & `PublicCoursePreviewController`):** Enabled sanitized TipTap rich-text rendering (`<x-learning.rich-content>`) inside `[data-player-stage]`, exposed only `isPublicPreview()` resources (signed file links only for authenticated `$viewer`), and upgraded the preview CTA card using design-system tokens.

## Screenshots reviewed
- Desktop RTL light: [pdf-ar-1568.png](../../tests/Browser/Screenshots/T-P4-06/pdf-ar-1568.png), [pdf-ar-1440.png](../../tests/Browser/Screenshots/T-P4-06/pdf-ar-1440.png)
- Mobile RTL light: [pdf-ar-852.png](../../tests/Browser/Screenshots/T-P4-06/pdf-ar-852.png)
- Mobile LTR dark: [attachments-en-390-dark.png](../../tests/Browser/Screenshots/T-P4-06/attachments-en-390-dark.png)

## Independent G7 visual score (final round)
| Dimension | Score |
|---|---|
| Layout & proportions | 4.5 |
| Color, contrast & surfaces (incl. dark mode) | 4.7 |
| Typography, hierarchy & RTL/LTR directionality | 4.6 |
| Components, tabs & interactive states | 4.6 |
| Spacing, rhythm & alignment | 4.4 |
| Visual polish & clean media frame | 4.5 |
| **Mean / Minimum** | **4.55 / 4.40 (PASS — bar: mean >= 4.50, min >= 4.0)** |

## Automated test evidence
- `tests/Feature/Learning/CoursePlayerTest.php`, `tests/Feature/Media/PrivateFilesTest.php`, `tests/Feature/Pages/PublicCourseDetailTest.php`: `49 passed (351 assertions)`.
- `tests/Browser/CoursePlayerBrowserTest.php`: `15 passed (134 assertions)` (AR/EN, desktop `1440x900`/`1568x1003` & mobile `390x844`/`852x1846`, light & dark, axe accessibility clean, zero horizontal overflow).
