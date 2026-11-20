# 14: Cutover runbook draft (T-P11-06)

> Read `docs/agent-prompts/00_COMMON.md` first. This is documentation only.

## Contents of `docs/ops/CUTOVER_RUNBOOK.md`
- Preconditions: the P10 and P11 gates, and the owner inputs (O-001, O-003, O-006, O-007 and others).
- A timeline from T-7 days to T+7 days: content freeze, final import, delta, DNS switch (O-001), smoke tests and monitoring.
- A detailed checklist with an owner and a time for each step, and a go/no-go criterion for each stage.
- A clear rollback plan for each stage, with its decision point and the maximum time allowed.
- Smoke tests to run after the switch, linked to J01–J12.
- A communication plan for learners, parents and instructors, with draft messages in Arabic and English.
- Mark every item that depends on missing information `PENDING-OWNER`.

## Output
- The runbook, plus a "draft" note on T-P11-06.
- A summary in Arabic.
