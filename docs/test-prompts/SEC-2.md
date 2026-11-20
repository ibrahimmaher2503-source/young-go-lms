# Tests prompt: SEC-2 — AI Adult/Minor Band Alignment & Atomic Budget Gate (`T-P7-02`)

Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. Write regression tests only in `tests/Feature/AI/AssistantInputGuardsCacheQuotasTest.php` and/or `tests/Feature/AI/AssistantEligibilityTest.php`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Run `npm run build` before any test run. On Windows run Pest with `php -d memory_limit=2G vendor/bin/pest <path>`.

## Vulnerabilities Being Tested
1. **Adult/Minor Split (`AssistantEligibility` vs `StyleTier`):** `AssistantEligibility::isAdult()` previously ignored database `age_bands`, causing eligibility and safeguarding alerting (`AskLessonAssistant`) to disagree with `StyleTier::forLearner()` when an admin modified the `adults` age band, and `StyleTier::forLearner()` lacked an O-015 `config('ai.adult_age')` floor check if a band was lowered below 18.
2. **Budget Overrun Race (`BudgetGate`):** `BudgetGate::assertOpen()` previously only summed committed `ai_usage.cost_minor` before provider execution with no reservation or lock, allowing concurrent requests to pass `assertOpen()` simultaneously and overrun monthly global or course budgets.

## Files & Classes Under Test
- `App\Domain\AI\AssistantEligibility`
- `App\Domain\AI\Assistant\StyleTier`
- `App\Domain\AI\Quota\BudgetGate`
- `App\Domain\AI\Quota\BudgetReservation`
- `App\Domain\AI\Actions\AskLessonAssistant`
- `App\Models\AiBudgetLedger`
- `App\Models\AiBudgetReservation`
- `config/ai.php` (`budget.*`)

## Behaviours, Attack Scenarios & Edge Cases to Assert
Tag all tests with `->group('T-P7-02', 'A1', 'A4', 'O-015', 'ED-010', 'SEC-2')`:

### Part A — Unified Adult/Minor Classification
1. **Admin raises `adults` band in `age_bands` (e.g., `teens` 10..20, `adults` 21..null):**
   - Seed `AgeBandSeeder` with `config('ai.adult_age', 18)` and update `teens.max = 20`, `adults.min = 21`.
   - For a 19-year-old learner (`birth_year = now()->year - 19`, `birth_month = 1`) with no guardian link:
     - `AssistantEligibility::isAdult($learner)` returns `false` (matches `StyleTier::forLearner($learner) === StyleTier::Teens`).
     - `AssistantEligibility::check($learner, $lesson)` refuses with `UnavailableReason::GuardianUnverified` / `ConsentRequired` (never treated as adult).
     - Self-harm moderation triggers an `AiSafeguardingFlag` and `ai.safeguarding_flagged` audit entry (`!isAdult`).
2. **Admin lowers `adults` band below `config('ai.adult_age')` (e.g., `adults.min = 16`, `config('ai.adult_age') = 18`):**
   - For a 17-year-old learner: `AssistantEligibility::isAdult($learner)` MUST stay `false` (O-015 legal floor wins), and `StyleTier::forLearner($learner)` MUST NOT return `StyleTier::Adults` (falls back to `StyleTier::Teens`).
3. **`config('ai.adult_age') === null` with `AgeBandSeeder` seeded:**
   - Even for a 25-year-old learner (`StyleTier::Adults`), `AssistantEligibility::isAdult($learner)` returns `false` and safeguarding raises a flag.
4. **Empty `age_bands` table with `config('ai.adult_age') = 18`:**
   - 18+ learner with valid `birth_year` & `birth_month` is adult; missing `birth_month` or invalid `birth_month` (e.g. 13) returns `false`.

### Part B — Concurrency-Safe Monthly Budget Reservation
1. **In-flight reservation blocks concurrent requests from overshooting global ceiling:**
   - Set `AiSettings::monthly_budget_minor = 100` (e.g. `cost` already `60` or `0` with `config(['ai.budget.reservation_minor' => 60])` or `estimatedCostMinor = 60`).
   - Hold an active reservation via `$hold = app(BudgetGate::class)->reserve($lesson, 60)` (or trigger an in-flight provider callback inside a fake `AiProviderInterface` that asserts inside `complete()` that an `AiBudgetReservation` row exists and that a second `POST /api/v1/lessons/{lesson}/assistant` / `AskLessonAssistant` or `BudgetGate::reserve()` throws `AssistantRefused::budgetExhausted` / HTTP 503 with zero second provider call!).
2. **Course-level monthly ceiling (`course_monthly_budget_minor`):**
   - With `monthly_budget_minor = null` and `course_monthly_budget_minor = 80`, holding a 50-minor reservation on course A + 40 spent blocks a concurrent call on course A (503 `budget_exhausted`), while course B remains unblocked.
   - If `global` reservation succeeds and `course` ceiling is already exceeded inside the same `reserve()` transaction, the transaction rolls back so NO orphaned `'global'` `AiBudgetReservation` row remains.
3. **Clean release on success, provider exception, and quota-exhausted race:**
   - When `AskLessonAssistant` finishes (either normally or when provider throws), `AiBudgetReservation::query()->count()` returns to `0`.
   - When `BudgetGate::reserve()` succeeds and `QuotaLedger::reserve()` subsequently throws `UnavailableReason::QuotaExhausted` (e.g. counter filled right before `QuotaLedger`), `finally` releases the `BudgetReservation` so `AiBudgetReservation::query()->count()` is `0`.
4. **Expired reservation reclamation:**
   - Insert an `AiBudgetReservation` row with `expires_at = now()->subSecond()`. Calling `BudgetGate::reserve()` deletes the expired row and succeeds cleanly; `BudgetGate::assertOpen()` ignores expired rows.
5. **No reservations created when ceilings are `null`:**
   - When both `monthly_budget_minor` and `course_monthly_budget_minor` are `null`, `reserve()` creates zero `ai_budget_ledgers` or `ai_budget_reservations` rows.

## SQLite Limits
- `SQLite :memory:` runs on a single connection where `SELECT ... FOR UPDATE` is a no-op and true multi-process lock contention cannot be exercised; simulate concurrent in-flight callers either by calling `BudgetGate::reserve()` before `AskLessonAssistant` / HTTP `postJson` or by dispatching a second `AskLessonAssistant` / `BudgetGate::reserve()` from inside a fake `AiProviderInterface::complete()` closure while the first call is in flight.

## Verification Commands
```bash
npm run build
php -d memory_limit=2G vendor/bin/pest tests/Feature/AI
php -d memory_limit=2G vendor/bin/pest tests/Feature/AgeBandResolverTest.php
php -d memory_limit=2G vendor/bin/pest tests/Arch tests/Feature/Invariants
```
