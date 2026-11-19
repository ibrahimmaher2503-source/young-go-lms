# Tests prompt: SEC-1 — Free Course HTTP Enrollment Endpoint & UI CTA (`T-P4-02`)

Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. Write regression tests only in `tests/Feature/Entitlements/EnrollmentAndGrantsTest.php` (or a dedicated test file under `tests/Feature/Entitlements/`).

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Run `npm run build` before any test run. On Windows run Pest with `php -d memory_limit=2G vendor/bin/pest <path>`.

## Vulnerability / Gap Being Tested
`EnrollInFreeCourse` previously had no HTTP controller or route, and `resources/views/catalog/_cta.blade.php` rendered disabled `data-cta="soon"` (`course_page.enrollment_soon`) for free courses because `PublicCourseController` only flagged `purchasable` when a paid `CoursePrice` existed. Signed-in learners and parents could not enroll in free published courses from HTTP/UI.

## Files & Classes Under Test
- `App\Http\Controllers\CourseEnrollController` (`POST /{locale}/courses/{course}/enroll`, route name `catalog.enroll`, middleware `auth`, `EnsureActiveAccount`, `auth.session`, `EnforceTwoFactor`, `throttle:10,1`)
- `App\Http\Controllers\PublicCourseController` (`GET /{locale}/courses/{course}`, route name `catalog.show`)
- `App\Domain\Entitlements\Actions\EnrollInFreeCourse`
- `App\Domain\Identity\ActingLearner`
- `resources/views/catalog/show.blade.php`
- `resources/views/catalog/_cta.blade.php`

## Behaviours, Attack Scenarios & Edge Cases to Assert
Tag all tests with `->group('T-P4-02', 'A1', 'A3', 'PRD-18', 'SEC-1')` as appropriate:

1. **Public CTA rendering (`GET /{locale}/courses/{course}` in AR and EN):**
   - Guest on free published course sees `data-cta="sign-in"`, not `data-cta="enroll-free"` or `data-cta="soon"`.
   - Signed-in non-enrolled learner on free published course sees active `<form data-enroll-free-form>` pointing to `route('catalog.enroll', ['course' => $course->id])` and `data-cta="enroll-free"` (no `data-cta="soon"`).
   - Signed-in learner on free published course whose cohort is full sees `data-cta="waitlist"` (or `data-cta="waitlisted"`), NOT `data-cta="enroll-free"`.
   - Signed-in learner on paid course without a market price still sees `data-cta="soon"`.
   - Signed-in parent (not acting as child) on free course sees learner selector `<select id="enroll-learner-id" name="child_id">` containing themselves (`enroll_myself`) and verified linked active children (with already-enrolled accounts marked disabled / `already_enrolled`). When the parent is already enrolled, the secondary `<form data-enroll-child-form>` with `<select id="enroll-child-id" name="child_id">` appears if at least one linked active child is not yet enrolled.
   - Signed-in parent acting as child (`ActingLearner`) sees `data-cta="enroll-free"` without the multi-learner dropdown (`showLearnerPicker` hidden) and when enrolled sees `data-cta="continue"`.

2. **Self-enrollment & Idempotent Double-Submit (`POST /courses/{course}/enroll`):**
   - Active student posts to `route('catalog.enroll', $course)` with no `child_id`.
   - Assert one `entitlements` row exists for `(learner_id: student, course_id: course, source_type: 'free')` via `EntitlementService::currentFor($student, $course)` and `activity_log` records `entitlement.granted`.
   - When the course has an open published lesson, response redirects to `route('learn.course', $course)`. When the course has no open items (empty curriculum or locked), response redirects to `route('catalog.show', ...)` with session flash `enrollment_notice` (`__('course_page.enrolled')`), avoiding a 404.
   - Posting a second time (double-click / replay) succeeds without creating a duplicate entitlement row (`Entitlement::query()->count() === 1`).

3. **Parent enrolling a child (`child_id` and `ActingLearner`):**
   - Verified parent posts with `child_id => $child->id`. Assert entitlement belongs to `$child->id`, NOT `$parent->id`, and response redirects to `route('catalog.show', ...)` with session flash `enrollment_notice` containing `$child`'s name (`enrolled_child`).
   - Verified parent in `ActingLearner::SESSION_KEY` child context posts with no `child_id`. Assert entitlement belongs to `$child->id`, NOT `$parent->id`.

4. **Authorization, Security & Validation Refusals (Attack Reproduction):**
   - **Unauthenticated guest:** `POST /courses/{course}/enroll` redirects to login (`302`), zero entitlements created.
   - **IDOR / Stranger or Unverified Child Enumeration:** Parent posts `child_id => $strangerChild->id`, `child_id => $pendingLinkedChild->id`, or `child_id => 99999999` (non-existent). ALL THREE must return HTTP `404` identically (no user enumeration) and create zero entitlements.
   - **Non-free / Non-published Course:**
     - Paid, subscription-only, or `both` access mode course: returns validation error on `course`, zero entitlements created.
     - Draft, private, suspended, upcoming, or archived course: returns HTTP `404` even for admins/instructors, zero entitlements created.
   - **Inactive / Suspended User:** Suspended actor (or parent trying to enroll a suspended child) receives `403` / `404` refusal and creates zero entitlements.
   - **Support View-As Read-Only Guard:** Staff member in `support.view-as` session attempting `POST /courses/{course}/enroll` is blocked with HTTP `403` by `EnforceViewAsReadOnly` and creates zero entitlements.

## Setup Hints & SQLite Limits
- Seed `RolePermissionSeeder::class`.
- Note I01 invariant (`tests/Feature/Invariants/I01_EntitlementOnlyViaServiceTest.php`): test files may query `Entitlement` if needed for assertions, or verify via `EntitlementService::canAccessCourse()` / `currentFor()` — remember not to touch `app/` production code.
- Include `withoutVite()` or run `npm run build` first so views render cleanly.

## Verification Commands
```bash
npm run build
php -d memory_limit=2G vendor/bin/pest tests/Feature/Entitlements
php -d memory_limit=2G vendor/bin/pest tests/Feature/Pages/PublicCourseDetailTest.php
php -d memory_limit=2G vendor/bin/pest tests/Feature/Commerce/CheckoutUiTest.php
php artisan app:check-translations
php artisan app:check-rtl
```
