# SEC-10: AI Budget Admission Must Check Proposed Reservation Against Remaining Ceiling

## Context
- **Finding:** Medium (`SECURITY_REVIEW_2026-11-20.md`) — Related tasks `T-P7-02`, `T-P11-01`.
- **Fixed file:** `app/Domain/AI/Quota/BudgetGate.php` (`reserve()`, `reservationMinor()`).

## Reproduction / Attack Scenario
Previously, `BudgetGate::reserve()` checked only `$used < $ceiling` before computing `$amount = $this->reservationMinor(...)`.
1. If `monthly_budget_minor = 100` and `spent = 80` (remaining `20`), a request whose reservation estimate was `50` (or `100` with 1 retry) was still admitted, exceeding the hard budget ceiling.
2. In addition, `reservationMinor()` did not account for `config('ai.assistant.retries', 1)`, underestimating worst-case billable work per request.

## Regression Test Requirements (write in `tests/Feature/AI/`)
1. Configure `monthly_budget_minor = 100`, `config(['ai.budget.default_reservation_minor' => 50, 'ai.assistant.retries' => 0])`, and seed `AiUsage` with `cost_minor = 80` for the current month (remaining `20 < 50`).
2. Call `BudgetGate::reserve($lesson)` (or `AskLessonAssistant`) and assert `AssistantRefused::budgetExhausted(...)` is thrown (`budget_exhausted`, HTTP `503`) and no `AiBudgetReservation` row is persisted.
3. Also verify retry sizing: with `monthly_budget_minor = 90`, `default_reservation_minor = 50`, and `config(['ai.assistant.retries' => 1])` (2 max provider attempts = `100 > 90`), `BudgetGate::reserve($lesson)` rejects with `budget_exhausted`.
4. Verify the same hard-ceiling enforcement applies to `course_monthly_budget_minor` (`course:<id>` scope).
