# Tests prompt: T-P9-08 Orphaned Upload Storage Reconciliation & Post-Commit Fault Tolerance

You are closing the remaining test gaps for `T-P9-08` (resilient post-commit storage cleanup in `AnonymizeAccount` and dry-run-by-default upload reconciliation via `ReconcileOrphanedUploads` / `privacy:reconcile-storage`). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first.

## Files and classes
- `config/privacy.php` (`orphan_grace_minutes` default `60`)
- `App\Domain\Privacy\Actions\AnonymizeAccount` (resilient `deleteFiles()` after DB commit so one storage adapter failure reports without throwing or aborting remaining deletions)
- `App\Domain\Privacy\Actions\ReconcileOrphanedUploads`
- `App\Console\Commands\ReconcilePrivacyStorageCommand` (`privacy:reconcile-storage {--apply} {--grace=}`)
- `routes/console.php` (daily `04:00` schedule for `privacy:reconcile-storage --apply`)

## Behaviours and edge cases to test

### 1. Post-commit storage adapter failure in `AnonymizeAccount` (`tests/Feature/Privacy/`)
- Configure a subject user with multiple files across `config('media.disk')` (`submissions/...`, `instructor-applications/...`, `avatars/...`) and `public` (`avatars/...`).
- Mock/inject a failing `Filesystem` adapter for `config('media.disk')` where `delete()` throws (or returns `false`) for one file while succeeding for others:
  - Assert `AnonymizeAccount` still completes and returns the scrub report, DB rows stay anonymized (`status = 'anonymized'`), and remaining deletions still execute.
  - Assert `ReconcileOrphanedUploads` then identifies the leftover file as an orphan once past `orphan_grace_minutes` (or with `graceMinutes: 0`).

### 2. Dry-run by default & grace window in `ReconcileOrphanedUploads` & `privacy:reconcile-storage`
- Place in `Storage::fake(config('media.disk'))` and `Storage::fake('public')`:
  - Referenced files present in DB (`submission_files.path`, `instructor_applications.id_document_path`, `users.avatar_path`) -> never deleted even under `--apply`.
  - `.gitignore`, `.DS_Store`, and `config('privacy.placeholder_file')` -> never flagged or deleted.
  - Fresh unreferenced upload (`mtime` within `orphan_grace_minutes = 60`) -> skipped under default grace, detected/deleted only when `--grace=0` or after `travel(61)->minutes()`.
  - Stale unreferenced orphans across `submissions/`, `instructor-applications/`, private `avatars/`, and public `avatars/`:
    - Calling `php artisan privacy:reconcile-storage` (no `--apply`) reports counts (`dry-run`) and leaves every file on disk (`assertExists`).
    - Calling `php artisan privacy:reconcile-storage --apply` (with aged timestamps or `--grace=0`) deletes ONLY the orphaned files and leaves all DB-referenced and fresh files intact (`assertExists` / `assertMissing`).
    - Calling with negative `--grace=-1` returns `Command::INVALID` (`exitCode(2)`).
- Assert `Schedule::events()` registers `privacy:reconcile-storage` at `'0 4 * * *'` (`dailyAt('04:00')`).
- Assert `ProtectedData` tables are untouched and `tests/Arch` remains green.

## Verification commands
```bash
php artisan privacy:reconcile-storage --help
php -d memory_limit=2G vendor/bin/pest tests/Feature/Privacy tests/Arch tests/Feature/Invariants
php vendor/bin/pint --test config/privacy.php app/Domain/Privacy app/Console/Commands/ReconcilePrivacyStorageCommand.php routes/console.php
```
