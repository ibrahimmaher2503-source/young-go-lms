# LOOP PROMPT (paste to the orchestrator each iteration)

You are the orchestrator for Young Go LMS. Run ONE iteration exactly as `agents/ORCHESTRATOR.md` defines:

1. Read `AGENTS.md`, `agents/ORCHESTRATOR.md`, `agents/PROGRESS.md`, `PROJECT_STATE.md`.
2. Select the task (ORCHESTRATOR §2) or use {TASK_ID}. Mark `in_progress`.
3. Read the task spec and only the doc sections it cites; read its rows in `docs/TRACEABILITY_MATRIX.md`; check `docs/REUSE_REGISTRY.md` and grep.
4. Write the Definition of Ready (§3) in PROGRESS.md, including AI impact and UI spec.
5. S0: `test-writer` writes failing, tagged acceptance tests. Confirm they fail for the right reason.
6. S1–S5: delegate to `implementer` / `ui-builder` / `chore` with briefs from `agents/SUBAGENT_BRIEF_TEMPLATE.md`. Review every diff (REVIEW_PROTOCOL); send back or fix.
7. Run gates (G1–G6, G10 always; G7 UI; G8 AI; G9 payments).
8. Verification wave in parallel: `prd-auditor`; `qa-verifier` (user-visible); `ux-reviewer` (UI); `reviewer` (risk: high). Rework until all PASS (max 3 cycles). Apply D-028 when the owner defers only visual acceptance. Record the ready prerequisite and keep its visual review open.
9. Commit `[task-id]`; update PROGRESS (evidence + verdicts), REUSE_REGISTRY, CHANGELOG, regenerate TRACEABILITY_MATRIX; milestone continuity at `T-Px-MG`.
10. Print the iteration report (ORCHESTRATOR §11).

Never guess a credential, OPEN decision or legal value: mark `blocked` with the exact missing item and continue with the next ready task.
