# Tests prompt: SEC-3 — Seated Cohort Start Date for Relative Drip on Non-Live Items (`T-P4-09`)

Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. Write regression tests only in `tests/Feature/Entitlements/AccessLayersTest.php` (or a dedicated file under `tests/Feature/Entitlements/`).

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Run `npm run build` before any test run. On Windows run Pest with `php -d memory_limit=2G vendor/bin/pest <path>`.

## Vulnerability / Gap Being Tested
`EntitlementService::canAccessItem()` previously set `$cohort = $entity instanceof LiveSession && $entity->cohort_id !== null ? $entity->cohort : null;`, passing `$cohortStart = null` into `dripDecision()` for all `Lesson`, `Quiz`, and `Assignment` curriculum items. Consequently, any hybrid/cohort course using `unlock_rule = 'days'` computed drip unlock dates from `$entitlement->starts_at` instead of the learner's seated cohort `starts_on`, prematurely unlocking (or mis-timing) non-live items relative to the cohort start date (`YG-GAP-LRN-002`).

## Files & Classes Under Test
- `App\Domain\Entitlements\EntitlementService` (`canAccessItem()`, `preloadItemAccess()`, `flush()`, `canAccessLessonFiles()`)
- `App\Domain\Curriculum\Queries\EntitledCurriculumQuery`
- `App\Domain\AI\AssistantEligibility`
- `App\Domain\Cohorts\Cohort`, `App\Domain\Cohorts\CohortMember`

## Behaviours, Attack Scenarios & Edge Cases to Assert
Tag all tests with `->group('T-P4-09', 'A1', 'A2', 'YG-GAP-LRN-002', 'ED-005', 'SEC-3')`:

1. **Seated learner uses cohort `starts_on + unlock_days` for `Lesson`, `Quiz`, and `Assignment`:**
   - Create a course with an `Entitlement` granted 30 days ago (`starts_at = now()->subDays(30)`), and a `Cohort` whose `starts_on` is `now()->addDays(5)->startOfDay()` (future cohort start) or `now()->subDay()->startOfDay()`.
   - Add a published `Lesson`, `Quiz`, and `Assignment` each with `unlock_rule = 'days'` and `unlock_days = 3`.
   - Seat the learner in the cohort (`CohortMember::SEATED`).
   - Assert `EntitlementService::canAccessItem()` returns `drip_locked` with `unlockAt->equalTo($cohort->starts_on->copy()->addDays(3))` for ALL THREE item types (lesson, quiz, assignment) — NOT `$entitlement->starts_at->addDays(3)`.
   - Travel to `$cohort->starts_on->copy()->addDays(3)` and assert all three items open (`allowed === true`).

2. **Waitlisted, removed, or unseated learner falls back to `entitlement->starts_at + unlock_days`:**
   - On the same course (`entitlement->starts_at = now()->subDays(10)`), a learner who has NO `CohortMember` row, or only a `waitlisted` row, or only a `removed` row in a future cohort (`starts_on = now()->addDays(20)`), must compute unlock from `$entitlement->starts_at + 3 days` (already open!).

3. **Seated cohort with `starts_on = null` falls back to `entitlement->starts_at`:**
   - Seat a learner in a cohort with `starts_on = null`; assert `dripDecision()` uses `$entitlement->starts_at + unlock_days`.

4. **Other-course cohort isolation:**
   - Seat the learner in Cohort X of Course A (`starts_on = now()->addDays(30)`). When evaluating a `days` drip item on Course B (where learner is NOT seated in any cohort of Course B), assert Course B uses Course B's `entitlement->starts_at`, ignoring Course A's cohort.

5. **Multiple seated cohorts in the same course use the earliest non-null `starts_on`:**
   - Seat the learner in two cohorts of the same course: Cohort 1 with `starts_on = now()->addDays(10)` and Cohort 2 with `starts_on = now()->addDays(2)` (or one null and one non-null). Assert the earliest non-null `starts_on` (`+2 days`) governs the drip unlock date.

6. **Downstream guards (`canAccessLessonFiles`, `AssistantEligibility`, HTTP `/learn/{course}/{item}`):**
   - While a `Lesson` (`unlock_rule = 'days'`) is `drip_locked` relative to the learner's seated cohort `starts_on`:
     - `GET /learn/{course}/{item}` renders locked state (`data-locked-reason`) and omits lesson content/media.
     - `EntitlementService::canAccessLessonFiles($learner, $lesson)` returns `false`.
     - `AssistantEligibility::check($learner, $lesson)->reason` is `UnavailableReason::NoEntitlement`.

7. **Batch preloading (`preloadItemAccess`) without N+1 queries:**
   - Create a course with 5+ `Lesson`/`Quiz`/`Assignment` items (`unlock_rule = 'days'`).
   - Call `$service->preloadItemAccess($learner, $items)` then enable `DB::enableQueryLog()`, flush query log, and call `$service->canAccessItem($learner, $item, $items)` across all items.
   - Assert ZERO additional `cohorts` / `cohort_members` queries execute during the loop, and that `$service->flush()` clears the preloaded cache cleanly when cohort membership changes afterwards.

## Setup Hints
- Seed `RolePermissionSeeder::class`.
- Remember `Carbon::setTestNow()` or `$this->travelTo()` for deterministic date arithmetic (`starts_on` is cast to `date`, i.e., midnight in app timezone).

## Verification Commands
```bash
npm run build
php -d memory_limit=2G vendor/bin/pest tests/Feature/Entitlements
php -d memory_limit=2G vendor/bin/pest tests/Feature/Cohorts
php -d memory_limit=2G vendor/bin/pest tests/Feature/Learning
php -d memory_limit=2G vendor/bin/pest tests/Arch tests/Feature/Invariants
```
