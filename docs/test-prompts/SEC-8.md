# SEC-8: Demo Seeder Must Include Published Live Sessions for J06

## Context
- **Finding:** P1 Demo (`DEMO_REHEARSAL_2026-11-20.md`) — Related tasks `T-P9-11`, `T-P5-02`.
- **Fixed file:** `database/seeders/DemoSeeder.php` (`curriculum()`).

## Reproduction / Bug Scenario
Running `php artisan demo:seed` created cohorts and seated learners (`omar` and `laila`) for `stage-workshop` and `animation-lab`, but created zero `live_sessions` rows, blocking Demo Journey J06 (live attendance / join flow).

## Regression Test Requirements (write in `tests/Feature/Demo/`)
1. Run `artisan('demo:seed')->assertExitCode(0)`.
2. Assert `LiveSession::query()->count()` is greater than 0 (2 sessions per live/hybrid cohort course: `stage-workshop` and `animation-lab`).
3. Assert `stage-workshop` has at least one published `CurriculumItem` (`itemable_type = 'live_session'`, `status = 'published'`) whose `LiveSession` is `LiveSession::OPEN` and belongs to the seated cohort.
4. Run `artisan('demo:reset')` (or `ResetDemoData`) and assert `live_sessions` seeded by `demo:seed` are cleaned up cleanly.
