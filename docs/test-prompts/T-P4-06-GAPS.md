# Tests prompt: T-P4-06 Lesson PDF Player & Public Preview G7 Visual/Functional Closure

You are closing the remaining test gaps for `T-P4-06` (protected PDF player layout, video range delivery / frame-ready state, mobile compact header + stage-first order, and public course preview rich-text/resources rendering). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d memory_limit=2G`.

## Files and classes
- `resources/views/components/layouts/learner.blade.php` (`$back` prop rendering `[data-mobile-back]` below `lg` while hiding mobile bell/locale/account controls)
- `resources/views/components/learning/curriculum-list.blade.php` (`embedded` prop for borderless `divide-y divide-border` sidebar integration)
- `resources/views/components/learning/lesson-media.blade.php` (`preload="auto"` on `<video>`)
- `app/Http/Controllers/MediaPlaybackController.php` (`response()->file(...)` HTTP `206 Partial Content` + `Content-Length` / `Accept-Ranges` when local file exists)
- `resources/views/learn/show.blade.php` (`flex flex-col gap-4` player ordering: `order-1 lg:order-2` stage, `order-2 lg:order-1` header, `order-3` `#lesson-materials`, `order-4` toolbar, `order-5` shortcuts)
- `resources/css/app.css` (`.lesson-materials` semantic-token tab styling)
- `app/Http/Controllers/PublicCoursePreviewController.php` (`viewer`, filtered `isPublicPreview()` `$resources`, signed URLs only for authenticated `$viewer`)
- `resources/views/catalog/preview.blade.php` (rich-text lesson rendering inside `[data-player-stage]`, `$resources` list, upgraded course CTA card)

## Behaviours and edge cases to test

### 1. Protected media delivery & player feature tests (`tests/Feature/Learning/CoursePlayerTest.php`, `tests/Feature/Media/PrivateFilesTest.php`)
- **HTTP Range / Content-Length playback (`MediaPlaybackController`):**
  - Hit `route('media.play', ...)` with `Range: bytes=0-511` for a local video asset (`tests/Fixtures/lesson-player.mp4`) and assert status `206`, `Accept-Ranges: bytes`, `Content-Range`, `Content-Length`, `Cache-Control: private, no-store`, and `X-Content-Type-Options: nosniff`.
  - Keep verifying non-entitled learners, wrong `?u=`, and expired signatures fail closed (`403` / `404`).
- **Combined sidebar + compact mobile back header (`learn/show.blade.php`):**
  - Assert `[data-mobile-back]` renders on `learn.show` and `learn.preview`, pointing to `learner.courses` (or preview back URL).
  - Assert `<video ... preload="auto"` and `class="... order-3 ..."` (`#lesson-materials` immediately after `[data-player-stage]` in visual flex flow on desktop and after header on mobile).

### 2. Public lesson preview tests (`tests/Feature/Pages/PublicCourseDetailTest.php`)
- **Rich-text preview lesson:**
  - Create a published course with a `free_preview` `Lesson` of type `'text'` and non-empty TipTap JSON `content`, visit `route('catalog.preview', ...)`, and assert `[data-player-stage] [data-lesson-text] [data-rich-content]` renders the sanitized text without requiring authentication.
- **Public preview resources visibility & auth boundary:**
  - Attach two `LessonResource` rows to a `free_preview` lesson: one with `is_public_preview = true` (`link` and `file`) and one with `is_public_preview = false` (`link`).
  - As a **guest**, assert the `is_public_preview = true` link appears, while the protected resource (`is_public_preview = false`) and any unsigned/forbidden download file do **not** appear.
  - As a **signed-in user** (using `ActingLearner`), assert both the public preview link and the signed `SignedFileUrl::for($viewer, $resource)` download link for the `is_public_preview = true` file appear, while `is_public_preview = false` remains hidden.
  - Assert the preview CTA card links back to `route('catalog.show', ['course' => $key])`.

### 3. Browser geometry, video frame readiness & G7 capture matrix (`tests/Browser/CoursePlayerBrowserTest.php`)
- Assert across AR/EN desktop (`1440×900`, `1568×1003`) and mobile (`390×844`, `852×1846`), light & dark themes:
  - Wait until `media.readyState >= 2` (`loadeddata`) when validating video stills and verify zero loading overlay (`media.readyState >= 2` and `media.duration === 2`).
  - Measure desktop vertical gap between `[data-player-stage]` bottom and `#lesson-materials` card top is compact (`<= 24px`, ~16px matching reference ~22px).
  - Measure mobile vertical stacking: `[data-mobile-back]` is visible (`rect.height >= 44`), `[data-player-stage]` sits above `[data-lesson-title]` (`stageRect.bottom <= titleRect.top`), and `[data-lesson-title]` sits above `#lesson-materials` (`titleRect.bottom <= tabsRect.top`).
  - Assert zero horizontal overflow (`scrollWidth <= innerWidth`) and `assertNoAccessibilityIssues()` across all screenshots.

## Setup hints & SQLite limits
- Run `npm run build` before any browser test.
- Copy `tests/Fixtures/lesson-player.mp4` into `Storage::fake('local')` (or active `config('media.disk')`) when asserting `206 Partial Content` or `readyState >= 2`.
- Do not modify `database/database.sqlite` or uncommitted `catalog/show.blade.php`.

## Verification commands
```bash
npm run build
php -d memory_limit=2G vendor/bin/pest tests/Feature/Learning/CoursePlayerTest.php tests/Feature/Media/PrivateFilesTest.php tests/Feature/Pages/PublicCourseDetailTest.php
php -d opcache.enable_cli=0 -d memory_limit=2G vendor/bin/pest tests/Browser/CoursePlayerBrowserTest.php
php artisan app:check-translations
php artisan app:check-rtl
php artisan app:check-brand-tokens
```
