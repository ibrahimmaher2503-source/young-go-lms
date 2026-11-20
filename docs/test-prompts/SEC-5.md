# Tests prompt: SEC-5 — Course Directory PRD §9.1 CSV Export & Sortable Columns (`T-P3-01`)

Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. Write regression tests only in `tests/Feature/Catalog/AuditCatalogRegressionTest.php` (or a dedicated test file under `tests/Feature/Catalog/`).

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Run `npm run build` before any test run. On Windows run Pest with `php -d memory_limit=2G vendor/bin/pest <path>`.

## Vulnerability / Gap Being Tested
1. **Incomplete Course CSV Export (`ListCourses`):** `ListCourses::getHeaderActions()` exported only 3 columns (`title`, `status`, `access_mode`), omitting `category`, `instructors`, `sections_count`, `lessons_count`, `enrollments_count`, and `market_price` required by PRD §9.1, and `ExportCsvAction` only accepted string attribute paths (preventing Closure formatting/unassigned fallbacks).
2. **Missing Table Sorting (`CourseResource`):** `title`, `category.name`, and `instructors.name` columns lacked `->sortable()`.

## Files & Classes Under Test
- `App\Filament\Resources\Courses\Pages\ListCourses`
- `App\Filament\Resources\Courses\CourseResource`
- `App\Filament\Shared\Actions\ExportCsvAction`

## Behaviours, Attack Scenarios & Edge Cases to Assert
Tag all tests with `->group('T-P3-01', 'A1', 'A3', 'PRD-09.1', 'SEC-5')`:

1. **PRD §9.1 CSV export columns & values for Admin (with `learner.progress.view`):**
   - Seed courses across categories (including one unassigned `category_id = null`), instructors (0, 1, and multiple instructors), sections, lessons, entitlements (active learners), free course vs priced EGP course (`59900` minor) vs unconfigured paid course.
   - As admin (`learner.progress.view` granted), trigger `Livewire::test(ListCourses::class)->callAction('exportCsv')->assertFileDownloaded('courses.csv')`.
   - Parse downloaded CSV content and verify header row includes ALL 9 columns: `courses.name`, `courses.category`, `courses.instructors`, `courses.sections`, `courses.lessons`, `courses.enrollments`, `courses.price`, `courses.status`, `courses.access`.
   - Verify row values match formatted EGP price (or `free` / `unconfigured`), counts (`sections_count`, `lessons_count`, `enrollments_count`), unassigned fallback text, and localized status.

2. **Permission gating & role scoping in CSV export (Security):**
   - **No `enrollments_count` leak:** Act as a staff user granted `course.view` WITHOUT `learner.progress.view` (e.g., an instructor or custom staff role). Trigger `exportCsv` and assert the `courses.enrollments` column header AND enrollment counts are ABSENT from the exported CSV.
   - **Instructor course scoping:** Act as an instructor assigned to Course A only (not Course B). Trigger `exportCsv` and assert Course A appears while Course B is absent.
   - **Unauthorized role:** Act as a student or staff lacking `course.view`; assert `exportCsv` action is hidden/unauthorized (`assertActionHidden('exportCsv')` or `AuthorizationException`).

3. **CSV formula injection escaping (`ExportCsvAction::safeCell` on Closure columns):**
   - Create a category named `=HYPERLINK("http://evil.test","cat")`, an instructor named `+cmd|' /C calc'!A0`, and a course titled `@SUM(1+1)` (and `-1+1`).
   - Trigger `exportCsv` and assert every dangerous cell in the CSV output is prefixed with `'` (`'=HYPERLINK...`, `'+cmd...`, `'@SUM...`, `'-1+1`).

4. **Table sorting (`asc` and `desc`) on `title`, `category.name`, and `instructors.name`:**
   - Using `Livewire::test(ListCourses::class)->sortTable('title', 'asc')` / `'desc'`, test in BOTH `en` and `ar` locales (`app()->setLocale('ar')` vs `'en'`) and assert `assertCanSeeTableRecords([...], inOrder: true)`.
   - Sort by `category.name` (`asc` and `desc`), including a course with `category_id = null`, asserting ordered output without SQL errors.
   - Sort by `instructors.name` (`asc` and `desc`) with 0 instructors, 1 instructor, and multiple instructors on a single course — assert ordering by first alphabetical instructor name and assert NO duplicate course rows appear in the table!

## Setup Hints
- Seed `RolePermissionSeeder::class`.
- Remember `ExportCsvAction` returns a `StreamedResponse`; in Livewire tests, `Livewire::test(ListCourses::class)->callAction('exportCsv')` allows reading the streamed content from `$response->effects['download']['content']` (base64 decoded) or via `assertFileDownloaded()`.

## Verification Commands
```bash
npm run build
php -d memory_limit=2G vendor/bin/pest tests/Feature/Catalog
php -d memory_limit=2G vendor/bin/pest tests/Feature/Filament/FilteredCsvExportTest.php
php -d memory_limit=2G vendor/bin/pest tests/Arch tests/Feature/Invariants
```
