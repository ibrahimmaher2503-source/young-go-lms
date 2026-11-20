# 02: Evidence audit of the review tasks P0–P7

> Read `docs/agent-prompts/00_COMMON.md` first. This task is **read-only**: do not change any production code.

## Goal
About 60 tasks in P0–P7 have status `review`, with notes like "approval withheld" or "independent reviewers pending". For each task, decide from the evidence whether it is ready to close. If it is not, record exactly what is missing.

## Method
For each task in `agents/PROGRESS.md` from T-P0-* to T-P7-* whose status is `review` or `blocked`:
1. Read the task spec in `agents/tasks/`: the acceptance items A1..An and the linked requirement IDs.
2. Match each acceptance item to evidence in the current code:
   - the test that covers it (path and test name). Run that test now to confirm it passes.
   - or a review or browser report in `docs/reviews/` or `docs/evidence/`.
3. Check whether a later decision changed the item: D-028, D-030, D-031, or the owner's G7 acceptance on 2026-11-19.
4. Classify the task:
   - `READY`: every acceptance item has evidence that passes, and nothing is open.
   - `READY-WITH-ACCEPTED-DEFECTS`: the only things left are design defects the owner accepted (D2, C1, H1, X6, X7, W1b, P1, R2), or G7 VISUAL DEFERRED under D-028.
   - `GAP-TEST`: the code exists, but no test covers item An. Write a test prompt in `docs/test-prompts/<ID>-GAPS.md`.
   - `GAP-CODE`: the behaviour itself is missing or wrong. Describe it precisely, with file and line.
   - `BLOCKED-OWNER`: waiting on an O-xxx input.

## Output
- `docs/reviews/EVIDENCE_AUDIT_P0_P7.md`: a table with these columns: task | classification | A→evidence | what is missing | suggested next step.
- One GAPS test prompt for each `GAP-TEST` task.
- A separate list of the `GAP-CODE` items, ordered by risk. Prompts 05 and 06 take their input from this list.
- Do not change any task status. Only add this short note to each task: "evidence audit 2026-11-xx: <classification>, see EVIDENCE_AUDIT_P0_P7.md".
- A summary in Arabic in your reply: how many tasks are READY, how many have gaps, and the five most dangerous gaps.
