# Tests prompt: T-P3-03 Remaining Test Gaps (`D-007` Coverage, Finish/Approval Livewire Actions & Remaining Browser Captures)

You are closing the remaining test gaps for `T-P3-03` (Course wizard: Pricing, Media, SEO, Custom fields, Finish and approval). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## What already passes
- `tests/Feature/Catalog/CourseWizardPricingSettingsTest.php`, `CourseWizardMediaSeoTest.php`, `CourseWizardCustomFieldsTest.php`, `CourseWizardReviewFlowTest.php`, `tests/Feature/Filament/CourseWizardPagesTest.php`, `tests/Feature/Media/PaidT3PublishTest.php`.
- `tests/Browser/CourseWizardBrowserTest.php` currently captures only `pricing-*` in `tests/Browser/screenshots/T-P3-03/` (note: existing `media-*` PNGs in `tests/Browser/screenshots/T-P3-03/` have no active Pest browser test generating them).

## Missing evidence to add

### 1. Fix `G10` missing requirement tag (`D-007`)
- `php artisan app:req-coverage --task=T-P3-03` currently fails with `Missing coverage: T-P3-03:D-007`.
- Add `'D-007'` to the relevant independent-course approval workflow tests in `tests/Feature/Catalog/CourseWizardReviewFlowTest.php` (e.g., independent instructor submit-for-review, admin approval/rejection boundary).

### 2. Missing Filament Livewire tests (`tests/Feature/Filament/CourseWizardPagesTest.php` or `tests/Feature/Catalog/CourseWizardReviewFlowTest.php`)
Tag with `->group('T-P3-03', 'A1', 'D-007', 'PRD-09-2', 'PRD-09-4')`:
- **`ListCourses` review table actions (`App\Filament\Resources\Courses\Pages\ListCourses`):**
  - `approve` and `reject` row actions from `ApproveRejectAction` are visible ONLY on `pending_review` courses and ONLY to users who pass `Gate::allows('publish', $record)` (e.g., admin with `course.approve` for independent courses; hidden for instructors and for `admin` without `course.approve` on independent courses).
  - `callTableAction('reject', $pendingCourse, data: ['reason' => 'short'])` fails validation (reason min length), leaves status `pending_review`, and writes no review audit.
  - Valid `callTableAction('reject', $pendingCourse, data: ['reason' => 'Missing safety prerequisites in unit 1'])` moves course back to `draft`, stores `review_reason`, records audit, and notifies instructors.
  - `callTableAction('approve', $pendingCourse)` enforces publish checks (`CourseReadiness::blockers($course, 'publish')`) and publishes when complete.
- **Wizard Finish step (`EditCourse` / `PersistsCourseWizard`):**
  - `submitForReview` action is visible only for editable draft courses; displays blocker validation notification on incomplete draft and sets `pending_review` when submit checks pass, redirecting to `ListCourses`.
  - `publishNow` action is visible only when `Gate::allows('publish', $course)`; publishes when ready and reports missing publish blockers otherwise.

### 3. Missing `A4` Browser tests, screenshots & visual record (`tests/Browser/CourseWizardBrowserTest.php`)
Tag with `->group('browser', 'T-P3-03', 'A4', 'D-007', 'PRD-09-2')`:
- Extend `tests/Browser/CourseWizardBrowserTest.php` to capture AR/EN × 390×844 and 1440×900 × light/dark screenshots in `tests/Browser/screenshots/T-P3-03/` for:
  1. **Media** step (with stored `cover_image` preview and `promo_video` inputs) — `media-{locale}-{theme}-{width}`
  2. **SEO** step (`seo_title`, `seo_description`, `seo_keywords`, `canonical_url`) — `seo-{locale}-{theme}-{width}`
  3. **Custom fields** step (at least one of text, number, select, toggle, date, translatable text) — `custom-{locale}-{theme}-{width}`
  4. **Finish** step (readiness checklist showing both ready and missing items, `submitForReview` / `publishNow` buttons) — `finish-{locale}-{theme}-{width}`
  5. **Course list with pending review course** (showing `approve` / `reject` actions) — `pending-table-{locale}-{theme}-{width}`
- Assert `dir`, `scrollWidth <= innerWidth`, dark class toggle, `assertNoJavaScriptErrors()`, and `assertNoAccessibilityIssues()`.
- Record file, viewport, language, theme, and reference comparison against `docs/design-system/reference-pack-2026-11-17/13-course-wizard-v2.png` in `docs/reviews/T-P3-03_VISUAL_MATCH.md`.

## Verification commands
```bash
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Feature/Catalog/CourseWizardReviewFlowTest.php tests/Feature/Filament/CourseWizardPagesTest.php
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Browser/CourseWizardBrowserTest.php --group=T-P3-03
php artisan app:check-translations
php artisan app:check-rtl
php artisan app:req-coverage --task=T-P3-03
```
