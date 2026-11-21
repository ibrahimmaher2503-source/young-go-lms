# SEC-6: Prevent File Path Tampering on Private Upload Forms

## Context
- **Finding:** High (`SECURITY_REVIEW_2026-11-20.md`) — Related tasks `T-P3-08`, `T-P3-09`, `T-P3-13`, `T-P6-04`, `T-P11-01`.
- **Fixed files:**
  - `app/Filament/Resources/Courses/Pages/ManageCurriculum.php` (`attachment_file`, `media_file`, `curriculum_pdf`, `resource_file` + `saveAssignmentAttachments`, `saveDocuments`, `saveMedia`)
  - `app/Filament/Resources/Library/Pages/ListLibraryItems.php` (`file` + header `add` action)

## Reproduction / Attack Scenario
An instructor authorized to edit their own course or add to their own library submits a crafted Livewire/Filament action payload containing an existing private path on `config('media.disk')` (for example `media/victim.pdf`, `resources/victim.txt`, or `certificates/2.pdf`) instead of an uploaded file.
Before the fix, the five `FileUpload` components lacked `storeFiles(false)->preventFilePathTampering()`, and the five handlers resolved string paths directly against `Storage::disk(config('media.disk'))` and deleted them in `finally`, allowing cross-course/private file deletion or unauthorized copying.

## Regression Test Requirements (write in `tests/Feature/Filament/` or `tests/Feature/Catalog/`)
1. Seed `Storage::fake('local')` and `config(['media.disk' => 'local'])` with an existing victim private file (e.g. `media/victim.pdf` containing valid PDF bytes, plus a secret text file `resources/secret.txt`).
2. Act as an instructor authorized on their own course (`ManageCurriculum`) and attempt to submit the victim path string in:
   - `addLesson` / `editLesson` with `curriculum_pdf` = `'media/victim.pdf'`
   - `addLesson` / `editLesson` with `resource_file` = `'resources/secret.txt'`
   - `addLesson` / `editLesson` with `media_provider` = `'signed_storage'` and `media_file` = `'media/victim.pdf'`
   - `editAssignment` with `attachment_file` = `'media/victim.pdf'`
3. Act as an instructor on `ListLibraryItems` and attempt `callAction('add', ['file' => 'media/victim.pdf', ...])`.
4. Assert for all five inputs:
   - No `LessonCurriculumDocument`, `LessonResource`, `MediaAsset`, `AssignmentAttachment`, or `LibraryItem` is created from the victim path.
   - `Storage::disk('local')->assertExists('media/victim.pdf')` and `Storage::disk('local')->assertExists('resources/secret.txt')` both hold (existing private files survive rejected input).
5. Also assert that normal authorized `UploadedFile::fake()` uploads still succeed on `ManageCurriculum` and `ListLibraryItems`.
