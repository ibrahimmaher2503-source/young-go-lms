# Tests prompt: T-P6-03 Remaining Test Gaps (Instructor Settings Modal Livewire + Browser/Screenshots)

You are closing the remaining test gaps for `T-P6-03` (Assignments and submissions). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## What already passes
- `tests/Feature/Assignments/AssignmentSubmissionsTest.php` verifies `A1`, `A2`, `A3` at the domain/HTTP level (MIME/magic-byte check, `qpdf` inspection, size & count limits, non-entitled/other-learner 404 boundaries, late policies, resubmission revisions, private download headers).
- `tests/Feature/Learning/AssignmentCompletionTest.php` verifies assignment completion coupling.

## Missing evidence to add

### 1. Livewire `ManageCurriculum::configureAssignmentAction()` tests (`tests/Feature/Assignments/AssignmentSubmissionsTest.php`)
Tag with `->group('T-P6-03', 'A1', 'A3', 'REQ-AS-007', 'REQ-AS-008')`:
- Mount `App\Filament\Resources\Courses\Pages\ManageCurriculum` as an authorized course instructor (`curriculum.update`) and invoke `callAction('configureAssignment', arguments: ['assignment' => $assignment->id], data: ...)`:
  - Bilingual `instructions.ar` / `instructions.en`, `allowed_types`, `max_size_mb` (verifying MB→KB `* 1024` conversion stored in `assignments.max_size_kb`), `due_at` interpreted in `assignmentZone()` (`Africa/Cairo`), `max_revisions`, `status` sync through `SetCurriculumStatus`.
  - Conditional validation: `late_penalty_percent` is required and clamped `1..100` when `late_policy === Assignment::LATE_PENALTY`, and rejected/ignored cleanly otherwise.
  - Attachments via `mountedAssignment()` inside the open modal: uploading `attachment_file` adds an `AssignmentAttachment` (and deletes staging file) while checking `remove_attachments` deletes ticked attachments.
  - Authorization: unauthorized instructor or non-staff user receives `AuthorizationException` / 403.

### 2. Browser + screenshots (`tests/Browser/AssignmentSubmissionBrowserTest.php`)
Tag with `->group('T-P6-03', 'A1', 'A2', 'A3', 'browser')`:
- Capture screenshots in `tests/Browser/screenshots/T-P6-03/` across `['ar', 'en'] × ['light', 'dark'] × [[390, 844], [1440, 900]]` for:
  1. Learner assignment submission card (`[data-assignment-card]`) on `/learn/{course}/{item}` in `draft`, `submitted`, and returned/resubmit states (including teacher attachments download links and uploader control).
  2. Instructor `configureAssignment` settings modal inside `/admin/courses/{course}/curriculum`.
- Assert `dir` matches locale (`ar => rtl`, `en => ltr`), `scrollWidth <= innerWidth`, `assertNoJavaScriptErrors()`, and `assertNoAccessibilityIssues()`.

## Verification commands
```bash
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Feature/Assignments/AssignmentSubmissionsTest.php
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Browser/AssignmentSubmissionBrowserTest.php
php artisan app:check-translations
php artisan app:check-rtl
php artisan app:req-coverage --task=T-P6-03
```
