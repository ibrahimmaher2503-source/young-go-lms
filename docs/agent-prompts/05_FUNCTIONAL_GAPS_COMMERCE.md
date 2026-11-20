# 05: Close the remaining functional gaps in commerce and finance

> Read `docs/agent-prompts/00_COMMON.md` first. You own these files: `app/Domain/Commerce*`, `Subscriptions` and `InstructorFinance`, plus their Filament resources. Do not touch the Learning or Assessment files; prompt 06 owns them.

## Known gaps (check each one in the current code first; some may already be fixed)
1. **Refunded and voided gateway callbacks** are still flagged for review, and `ExecuteRefund` webhook callbacks are not matched to refund rows. Match them on the provider reference. The match must be idempotent, with no double transition.
2. **Cohort reservations for plans:** subscription plans do not reserve a cohort seat the way single courses and bundles do (D-031). Check the specs and decisions first. If the behaviour is not clearly defined, mark it `NEEDS-OWNER`.
3. **Queued and PDF exports:** large reports and CSV exports should run on a queue and notify the user when the file is ready. Statement PDF needs a real Chromium-generated check, not only a Blade check.
4. **T-P8-13 audit (CHANGES REQUIRED):** check the remaining items, including the SA webhook check. Do not activate the Saudi market: `Market::isActive` stays as it is, because T-P12-01 owns activation.
5. Find every `->todo()` in `tests/Feature/Commerce|Subscriptions|InstructorFinance|Settings` whose reason was a bug that has since been fixed. List them in the report so the test model can activate them.

## Out of scope
The live Paymob gateway (O-006), ETA (O-023) and the seller's legal details (O-007). Their adapters stay off.

## Output
- One commit per gap, the test prompt `docs/test-prompts/T-P8-GAPS-2.md`, and notes on the T-P8-xx tasks in the board.
- Update the "Known functional gaps" section in `docs/reviews/GATE_READINESS_P8_P9.md` so it matches reality.
- A summary in Arabic.
