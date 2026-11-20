# Tests prompt: T-P9-05 Notification Matrix Completeness & Event Wiring

You are closing the remaining test gaps for `T-P9-05` (notification matrix completeness, `quiz.result` source wiring across auto-graded submit / essay review / manual release, `event()` bindings on notices, per-recipient title localization, timezone guard, and `docs/NOTIFICATION_MATRIX.md` sync). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first.

## Files and classes
- `App\Domain\Assessment\Notifications\QuizResultNotice` (`event() => 'quiz.result'`, `dedupeKey() => 'quiz-review:{id}'`, localized quiz title, `review.notice.quiz_result`)
- `App\Domain\Assessment\Notifications\EssayReviewedNotice` (`event() => 'quiz.result'`, localized quiz title)
- `App\Domain\Assessment\Actions\SubmitQuizAttempt` (dispatches `QuizResultNotice` for non-review, non-hidden auto-graded submissions only on first submit)
- `App\Domain\Assessment\Actions\GradeEssayAnswer` (withholds `EssayReviewedNotice` when `result_visibility === 'hidden'` and `released_at === null`)
- `App\Domain\Assessment\Actions\ReleaseQuizResult` (dispatches `EssayReviewedNotice` when essays were reviewed or `QuizResultNotice` otherwise, after commit, non-fatal on failure)
- `App\Domain\Assignments\Notifications\SubmissionDecisionNotice` (`event() => matrixEvent()`, per-recipient localized assignment title)
- `App\Domain\Certificates\Notifications\CertificateIssuedNotice` (`event() => 'certificate.issued'`)
- `App\Domain\Notifications\Notifications\AccessNotice` (`timezone_identifiers_list()` validation fallback)
- `App\Domain\Notifications\Listeners\NotifyInstructorsOfCourseReview` & `NotifyOfCourseRevision` (per-recipient localized course title)
- `lang/{ar,en}/review.php` (`notice.quiz_result`), `lang/{ar,en}/support.php` (`notice_kinds.quiz_result`), `docs/NOTIFICATION_MATRIX.md`

## Behaviours and edge cases to test

### 1. Quiz result notification wiring (`tests/Feature/Notifications/AssessmentLifecycleNoticesTest.php`)
- **Auto-graded quiz (`needs_review = false`):**
  - `result_visibility = 'full'` or `'score'`: `SubmitQuizAttempt` dispatches `QuizResultNotice` once with `event() === 'quiz.result'` and links to `quiz.attempt`; calling `SubmitQuizAttempt` again does not duplicate (`dedupeKey`: `quiz-review:{attempt_id}`).
  - `result_visibility = 'hidden'`: `SubmitQuizAttempt` sends **no** notification; calling `ReleaseQuizResult` then sends `QuizResultNotice` once (re-releasing is idempotent), forwarding sanitized notice to verified guardians when learner is a child without email.
- **Essay quiz (`needs_review = true`):**
  - `result_visibility = 'hidden'` and `released_at = null`: `GradeEssayAnswer` completes grading **without** notifying; calling `ReleaseQuizResult` afterwards sends `EssayReviewedNotice` once (`event() === 'quiz.result'`).
  - `result_visibility = 'full'`: `GradeEssayAnswer` sends `EssayReviewedNotice` with localized quiz title matching each recipient's locale.

### 2. Notice metadata, localization & resilience (`tests/Feature/Notifications/`)
- Assert `(new SubmissionDecisionNotice(...))->event()` returns `assignment.graded` / `assignment.accepted` / `assignment.rejected` / `assignment.revision` and renders the assignment title in the recipient's locale (`ar` vs `en`).
- Assert `(new CertificateIssuedNotice(...))->event() === 'certificate.issued'` and `(new QuizResultNotice(...))->event() === 'quiz.result'`.
- Assert `AccessNotice` does not throw on an invalid `$recipient->timezone` string (e.g. `'Invalid/Zone'`), falling back cleanly to `config('app.timezone')`.
- Assert `NotifyInstructorsOfCourseReview` and `NotifyOfCourseRevision` send bilingual course titles translated per instructor/reviewer locale (e.g., Arabic instructor gets Arabic course title even when an English admin approved).
- Assert `docs/NOTIFICATION_MATRIX.md` includes every event returned by `NotificationMatrix::events()` (including `course.approved` and `course.rejected`).

### 3. Support Learner 360 label check (`tests/Feature/Pages/G7FixesLearner360LabelsTest.php`)
- Verify `QuizResultNotice` maps to `quiz_result` -> `support.notice_kinds.quiz_result` (`Quiz result` / `نتيجة الاختبار`), never `'other'`.

## Verification commands
```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/Notifications tests/Feature/Assessment tests/Feature/Assignments tests/Feature/Pages/G7FixesLearner360LabelsTest.php tests/Arch
php artisan app:check-translations
php artisan app:check-rtl
php vendor/bin/pint --test app/Domain/Assessment app/Domain/Assignments app/Domain/Certificates app/Domain/Notifications
```
