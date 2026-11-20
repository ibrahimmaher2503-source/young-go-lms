# Tests prompt: T-P3-07 Remaining Test Gaps (`YG-GAP-CHG-001` Tag, Notifications, Action Guards, Livewire & Browser)

You are closing the remaining test gaps for `T-P3-07` (Course revisions after publication). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## What already passes
- `tests/Feature/Catalog/CourseRevisionsTest.php` exercises domain actions (`OpenCourseRevision`, `SaveCourseRevision`, `SubmitCourseRevision`, `DiscardCourseRevision`, `ApproveCourseRevision`, `RejectCourseRevision`, `ReviewCourseRevision`, live concurrency merge, `SaveCourseWizard` guard, `course.updated_live` audit).

## Missing evidence to add

### 1. Fix `G10` missing requirement tag (`YG-GAP-CHG-001`)
- `php artisan app:req-coverage --task=T-P3-07` currently fails with `Missing coverage: T-P3-07:YG-GAP-CHG-001`.
- Ensure tests in `tests/Feature/Catalog/CourseRevisionsTest.php` carry `->group('T-P3-07', 'YG-GAP-CHG-001', 'YG-GAP-CHG-002', 'YG-GAP-CHG-003')` alongside `'A1'`/`'A2'`/`'A3'`.

### 2. Missing domain guard & notification tests (`tests/Feature/Catalog/CourseRevisionsTest.php`)
- **Curriculum action guards on a published independent course (`instructor_independent` manager):**
  - Assert `ValidationException` with key `'course'` (`__('course_revisions.use_revision')`) when calling:
    - `ManageSections::add()`, `rename()`, `archive()`
    - `ManageLessons::add()`, `archive()`, and `ManageLessons::update()` when `title` changes
    - `ReorderCurriculum`
    - `SetCurriculumStatus`
    - `ManageAssessmentItems`
  - Assert `ManageLessons::update()` without changing title/description still succeeds, and academy-owned published courses do NOT trigger `RevisionAccess::requiresRevision()`.
- **Notification dispatcher assertions (`Notification::fake()`):**
  - `SubmitCourseRevision` notifies active admins with `course.publish` + `panel.admin.access` who pass `publish` on the course (`course.revision_submitted`), excluding the author and excluding admins lacking `course.approve` for independent courses.
  - `ApproveCourseRevision` / `RejectCourseRevision` notify the author, course instructors, and owner instructor (`course.revision_approved` / `course.revision_rejected`), with the rejection reason included in `course.revision_rejected` body.
  - Deduplication prevents duplicate sends per revision/user, and a failing mail/notification channel does not roll back the committed review decision.

### 3. Missing Filament Livewire tests (`tests/Feature/Filament/CourseRevisionPagesTest.php` or inside `CourseRevisionsTest.php`)
Tag with `->group('T-P3-07', 'A1', 'A2', 'A3', 'YG-GAP-CHG-001')`:
- **`ManageCourseRevision` (`App\Filament\Resources\Courses\Pages\ManageCourseRevision`):**
  - Instructor opens revision (`openRevision`), edits payload in slide-over (`editRevision`), submits (`submitRevision`), or discards (`discardRevision`), verifying compare summary rows and last-10 history rows.
  - Unassigned instructor and view-only instructor receive 403/404.
- **`ListCourseRevisions` (`App\Filament\Resources\CourseRevisions\Pages\ListCourseRevisions`):**
  - Admin with `course.publish` sees pending revision, runs `compare`, `approve`, and `reject` (reject requires reason); author and non-pending revisions have `approve`/`reject` hidden.
- **`EditCourse` redirect & curriculum banner:**
  - `EditCourse` redirects an independent instructor opening a published independent course to `ManageCourseRevision`.
  - `ManageCurriculum` displays `[data-revision-banner]` when a revision is open or required.

### 4. Browser + screenshots (`tests/Browser/CourseRevisionBrowserTest.php`)
Tag with `->group('T-P3-07', 'A1', 'A2', 'A3', 'YG-GAP-CHG-001', 'browser')`:
- Capture AR/EN × 390×844 and 1440×900 (light/dark) screenshots in `tests/Browser/screenshots/T-P3-07/` of:
  1. `ManageCourseRevision` with an open revision, compare summary, and history rows.
  2. `editRevision` slide-over modal.
  3. `/admin/course-revisions` review queue with the compare modal open.
- Assert `dir` matches locale, `scrollWidth <= innerWidth`, `assertNoJavaScriptErrors()`, and `assertNoAccessibilityIssues()`.

## Verification commands
```bash
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Feature/Catalog/CourseRevisionsTest.php
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Browser/CourseRevisionBrowserTest.php
php artisan app:check-translations
php artisan app:check-rtl
php artisan app:req-coverage --task=T-P3-07
```
