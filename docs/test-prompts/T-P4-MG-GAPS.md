# Tests prompt: T-P4-MG Milestone Demo End-to-End Test (`A1` & `G10` Coverage)

You are closing the missing milestone test gap for `T-P4-MG` (Milestone gate P4). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## Why this gap exists
- `php artisan app:req-coverage --task=T-P4-MG` currently fails with `Missing coverage: T-P4-MG:A1` (`agents/PROGRESS.md`: `G10 FAIL`).
- Note: Wait until `T-P4-02` (free self-enrollment HTTP route/CTA) and `T-P4-09` (seated cohort drip start date on non-live items) are also resolved if you want the full P4 milestone gate to close, though this milestone demo test itself exercises manual grant -> learn -> resume -> download -> progress -> parent view -> unauthorized 404.

## Missing evidence to create

### Create `tests/Feature/Milestone/P4DemoTest.php`
Follow the pattern of `tests/Feature/Milestone/P1DemoTest.php` and tag with `->group('T-P4-MG', 'A1')`:

1. **Setup:**
   - Seed `RolePermissionSeeder` and configure `Storage::fake(config('media.disk', 'local'))`.
   - Create an admin user, an academy instructor, a verified linked parent + child learner (`student` role, verified `GuardianChildLink`), and an unrelated non-entitled learner (`otherStudent`).
   - Create a published course with one published section containing:
     - Lesson 1: video lesson (`signed_storage` protected video asset, duration e.g. 120s) + private downloadable `LessonResource` PDF + private `LessonCurriculumDocument` PDF.
     - Lesson 2: rich-text or document lesson for completion/resume verification.
2. **End-to-end journey assertions:**
   - **Step 1 (Manual grant):** Admin grants entitlement to `$child` via `App\Domain\Entitlements\Actions\GrantEntitlement` (`EntitlementSource::ManualGrant`) with a reason string (`admin grant reason`), creating an active entitlement for the child and recording the audit event.
   - **Step 2 (Player & protected media/PDFs):** Acting as `$child`, `GET route('learn.course', ['course' => $course->id])` and `GET route('learn.show', ['course' => $course->id, 'item' => $item1->id])` return 200. Obtain signed download URL (`App\Domain\Media\SignedFileUrl::for($child, $resource)` / document) and `GET route('files.download', ...)` returns 200 with `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`.
   - **Step 3 (Progress & resume):** `POST route('learn.progress', ['course' => $course->id, 'item' => $item1->id])` records watched seconds and position (e.g. `position_seconds = 60`, then completion >= 90%), updates course completion percentage, and sets resume position.
   - **Step 4 (Student dashboard):** `GET route('learner.dashboard')` returns 200 showing the enrolled course and updated progress %.
   - **Step 5 (Parent dashboard + context switch):** Acting as `$parent`, `GET route('parent.dashboard')` returns 200 showing `$child`'s course progress and current lesson; switching into `$child` acting context reflects `$child` progress while preventing unauthorized access to unrelated children.
   - **Step 6 (Non-entitled boundary):** Acting as `$otherStudent` (no entitlement), `GET route('learn.course', ['course' => $course->id])` and `GET route('learn.show', ['course' => $course->id, 'item' => $item1->id])` return `404`, and `GET` on `$child`'s signed `files.download` URL returns `403`.

## Verification commands
```bash
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Feature/Milestone/P4DemoTest.php
php artisan app:req-coverage --task=T-P4-MG
```
