# 04: Fix confirmed security and code findings

> Read `docs/agent-prompts/00_COMMON.md` first. Inputs: `docs/reviews/SECURITY_REVIEW_*.md`, the `GAP-CODE` list in `docs/reviews/EVIDENCE_AUDIT_P0_P7.md`, and the bug list in `docs/reviews/DEMO_REHEARSAL_*.md`.

## Goal
Fix the findings the PO approved, in this order: Critical → High → Medium. Leave Low findings alone unless the fix is one line.

## For each finding
1. First reproduce it: read the code path and confirm the problem exists in the current code.
2. Make the smallest fix that follows the architecture. Do no refactoring on the side.
3. Run the tests for the affected folder, plus Arch and Invariants.
4. Make one commit per finding: `fix(security|area): ... [T-xx-yy]`.
5. Write a test prompt that reproduces the attack or the bug in `docs/test-prompts/SEC-<n>.md`. An independent model will write the regression test from it.

## Rules
- If a finding needs a product decision (for example, a refund policy), mark it `NEEDS-OWNER` and do not fix it.
- Do not change what a passing test means. If a test depended on the vulnerable behaviour, explain that in the report.

## Output
`docs/reviews/SECURITY_FIXES_2026-11-xx.md` with these columns: finding | fix | commit | tests run | test prompt. Add a summary in Arabic.
