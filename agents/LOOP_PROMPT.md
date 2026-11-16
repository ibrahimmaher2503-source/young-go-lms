# LOOP PROMPT (paste to the orchestrator each iteration)

You are the orchestrator for Young Go LMS. Run ONE iteration exactly as `agents/ORCHESTRATOR.md` defines:

1. Read `AGENTS.md`, `agents/ORCHESTRATOR.md`, `agents/PROGRESS.md`, `PROJECT_STATE.md`.
2. Select the task (ORCHESTRATOR §2) or use {TASK_ID}. Confirm its dependencies and authorized scope; mark `in_progress`.
3. Read the task spec and only the doc sections it cites; read its rows in `docs/TRACEABILITY_MATRIX.md`; check `docs/REUSE_REGISTRY.md` and grep.
4. Write the Definition of Ready (§3) in PROGRESS.md, including AI impact and UI spec.
5. For executable behavior, S0 writes failing, tagged acceptance tests; confirm the failure reason. Docs/inventory use structural and semantic checks.
6. Relevant S1–S5: use available host tools to delegate when authorized, otherwise implement sequentially. Use `implementer` / `ui-builder` / `chore` with briefs from `agents/SUBAGENT_BRIEF_TEMPLATE.md`. Review every diff (REVIEW_PROTOCOL); send back or fix.
7. Apply VERIFICATION_GATES bootstrap/path-impact rules for G1–G11. Record actual results, justified N/A and required NOT RUN checks. Verify disposable database isolation before resets.
8. Verification wave in parallel: `prd-auditor`; `qa-verifier` (user-visible); `ux-reviewer` (UI); `reviewer` (risk: high). Use separate reviewers, never self-review PASS; unavailable required review stays pending. Rework up to 3 cycles, rerunning all checks/reviews invalidated by changes.
9. Commit `[task-id]` only when authorized; update PROGRESS (evidence + verdicts), REUSE_REGISTRY, CHANGELOG, regenerate TRACEABILITY_MATRIX; milestone continuity at `T-Px-MG`.
10. Print the iteration report (ORCHESTRATOR §11).

Never guess a credential, OPEN decision or legal value: record the exact blocked slice; continue independent authorized work without marking incomplete acceptance done.
