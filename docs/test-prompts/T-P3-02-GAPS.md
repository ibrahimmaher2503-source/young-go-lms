# Tests prompt: T-P3-02 Course-Level Quiz Pass Mark in Course Wizard

You are adding acceptance and UI regression tests for the course-level quiz pass mark field (`courses.quiz_pass_mark_percent`) inside the course creation/edit wizard (`T-P3-02` / `O-020`). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## Files and classes
- `App\Filament\Resources\Courses\Schemas\CourseWizardSteps` (`basic()` Step 1 `TextInput::make('quiz_pass_mark_percent')`)
- `App\Domain\Catalog\Actions\SaveCourseWizard` (`validate()` and `persist()`)
- `App\Filament\Resources\Courses\Pages\Concerns\PersistsCourseWizard` (`courseFormData()`)
- `App\Filament\Resources\Courses\Pages\CreateCourse`, `EditCourse`
- `App\Domain\Assessment\QuizPassMark`

## Behaviours and edge cases to test (`tests/Feature/Catalog/CourseWizardTest.php` and `tests/Feature/Filament/CourseWizardPagesTest.php`)
Tag tests with `->group('T-P3-02', 'A1', 'O-020')`:

1. **Action persistence & validation (`SaveCourseWizard`)**
   - Saving Step 1 (draft or complete) with `quiz_pass_mark_percent` = `75` persists `courses.quiz_pass_mark_percent = 75` and records one `course.quiz_pass_mark_saved` audit entry with `before = null`, `after = 75`.
   - Clearing `quiz_pass_mark_percent` with `''` or `null` sets `courses.quiz_pass_mark_percent = null` and audits `before = 75`, `after = null`.
   - Re-saving Step 1 with the same value or omitting the `quiz_pass_mark_percent` key leaves the stored value untouched and writes no duplicate `course.quiz_pass_mark_saved` audit row.
   - Invalid inputs (`0`, `101`, `-1`, `65.5`, `'abc'`) fail with `ValidationException` on `quiz_pass_mark_percent` (and use the localized `completion.quiz_pass_mark` attribute label).
   - Direct wizard edit on an already-published course still records both `course.updated_live` and `course.quiz_pass_mark_saved` appropriately (and published independent courses remain blocked via `RevisionAccess`).
   - Down-stream inheritance: a quiz with `pass_mark_percent = null` added to that course resolves `QuizPassMark::forQuiz($quiz)` to the wizard-saved course pass mark.

2. **Filament Livewire Create/Edit wizard UI (`CourseWizardPagesTest.php`)**
   - Mounting `CreateCourse` as an authorized admin and instructor (`course.create`) renders `quiz_pass_mark_percent` in Step 1 (`Basic`) with localized AR/EN labels (`completion.quiz_pass_mark` and `completion.quiz_pass_mark_help` showing `QuizPassMark::default()`).
   - Submitting via `CreateCourse` / `EditCourse` (`saveDraft` and step completion) persists `quiz_pass_mark_percent`, and mounting `EditCourse` pre-fills the stored value cleanly.
   - Invalid value (`0` or `101`) maps the validation error cleanly back to `data.quiz_pass_mark_percent` in Livewire (`PersistsCourseWizard::persistStep`).
   - RTL/localization check: `app:check-translations` and `app:check-rtl` remain green; browser capture of Step 1 in AR/EN at `390×844` and `1440×900` (`CourseWizardBrowserTest`) has zero horizontal overflow and no axe issues.

## Setup hints & SQLite limits
- `uses(RefreshDatabase::class);` + `$this->seed(RolePermissionSeeder::class);`.
- Always call `$this->withoutVite()` in Livewire tests when Vite manifest isn't needed, or run `npm run build` beforehand.
- SQLite in-memory database runs per `phpunit.xml`; never modify `database/database.sqlite`.
- Keep all test additions free of production code edits (`app/`, `resources/`, `lang/`, `routes/`, `config/`).

## Verification commands
```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/Catalog/CourseWizardTest.php tests/Feature/Filament/CourseWizardPagesTest.php tests/Feature/Assessment/TPassMarkQuizTest.php
php artisan app:check-translations
php artisan app:check-rtl
```
