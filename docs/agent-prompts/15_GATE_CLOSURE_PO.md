# 15: Gate closure review (PO/orchestrator only)

> Read `docs/agent-prompts/00_COMMON.md` first. This prompt is for the strongest model acting as PO. Run it after prompts 01–09.

## Inputs
`docs/reviews/REGRESSION_RUN_*.md`, `EVIDENCE_AUDIT_P0_P7.md`, `SECURITY_REVIEW_*` and `SECURITY_FIXES_*`, `DEMO_REHEARSAL_*`, `PERF_BUDGET_FULL_*`, `A11Y_RTL_AUDIT_*`, and `GATE_READINESS_P8_P9.md`.

## Method
1. Verify every claim in these reports yourself: run the tests they cite and read the commits. Do not accept a claim that has no evidence.
2. Move every task classified READY or READY-WITH-ACCEPTED-DEFECTS to `done`, with a note that points to its evidence.
3. For each gate from T-P0-MG to T-P9-MG, apply ORCHESTRATOR.md §8.3 item by item: task closure, demo, checklist, performance and continuity. Close a gate only if it meets all of them in full. For any gate that stays open, record why.
4. Update `PROJECT_STATE.md`, `HANDOFF.md` and `docs/HANDOFF_NEXT_PROMPT.md`.
5. Prepare a list for the owner of the decisions still pending (fast-forwarding `main`, what to do with `docs/16_...`, and the O-xxx items), with the effect each one has on the timeline.

## Output
- `docs/reviews/GATE_CLOSURE_2026-11-xx.md`, plus the board updates.
- A summary in Arabic for the owner.
