# 06: Close the remaining functional gaps in learning, authoring and notifications

> Read `docs/agent-prompts/00_COMMON.md` first. You own these files: `app/Domain/Learning|Assessment|Assignments|Curriculum|Notifications|Media` and the course wizard. Do not touch Commerce; prompt 05 owns it.

## Gaps (check each one first)
1. **Admin UI for the course-level pass mark in the wizard** is still open. Add the field to the right wizard step, with validation, translations and RTL support.
2. **T-P4-07 A3:** periodic watermark motion and explicit T2 render evidence. Finish the behaviour if any of it is missing.
3. **T-P4-06 (blocked on G7, scored 4.08/5):** fix what `docs/reviews/T-P4-06_VISUAL_MATCH.md` lists. The protected PDF page and the previews must reach the bar: a mean of 4.5 with every dimension at 4.0 or higher. Use only design-system components and tokens.
4. **Notification matrix:** compare `docs/NOTIFICATION_MATRIX.md` with the code. Every "required" event needs a source, a template, AR/EN text, preferences and idempotency. Wire up whatever is missing.
5. **T-P9-04 audit (CHANGES REQUIRED):** minor issues in `SendPhoneReminder` and `SetPhoneReminderOptIn`.
6. **Storage reconciliation:** if the storage adapter fails during the post-commit cleanup in anonymization, orphaned uploads are left behind. Add a reconcile command that does a dry run by default, and schedule it if that makes sense.

## Output
- One commit per gap, a test prompt for each task (`docs/test-prompts/<ID>-GAPS.md`), and notes in the board.
- Update the matrix in `docs/NOTIFICATION_MATRIX.md` to show the wiring status.
- A summary in Arabic.
