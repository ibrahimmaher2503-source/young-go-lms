# Young Go LMS --- Developer Handoff

**Prepared:** 2026-11-16 (pack v3.3)\
**Current stage:** Reviewed specification/methodology pack; no application is expected in this deliverable.

## Read order

1.  `README_AR.md`
2.  `docs/01_MASTER_PRD_YOUNG_GO_LMS.md`
3.  `docs/02_DMC_DEVELOPMENT_MASTER_CONTEXT.md`
4.  `docs/03_PHASES_MILESTONES_ROADMAP.md`
5.  `docs/04_DELIVERY_ACCEPTANCE_CHECKLIST.md`
6.  `docs/05_ARCHITECTURE_DATA_API.md`
7.  `docs/06_AI_LESSON_ASSISTANT_SPEC.md`
8.  `docs/07_DECISION_REGISTER.md`
9.  `docs/08_LEGACY_MIGRATION_PLAN.md`
10. `docs/09_CODEX_START_HERE.md`
11. `docs/10_GAP_CLOSURE.md`
12. `docs/11_DESIGN_SYSTEM.md`
13. `docs/12_CODE_ARCHITECTURE_CONVENTIONS.md`
14. `docs/13_ENGINEERING_DECISIONS.md`, `docs/14_LEARNHOUSE_REFERENCE_STUDY.md`, `docs/15_UX_FLOWS_AND_PATTERNS.md`, `docs/TRACEABILITY_MATRIX.md`
15. `AGENTS.md`, `agents/README.md`, `agents/ORCHESTRATOR.md`

## Verified facts

-   Owner wants existing Young Go brand/logo/domain retained.
-   Owner requires all existing courses and related course content to be
    migrated.
-   Owner-approved functional/commercial/AI decisions are recorded in
    PRD and Decision Register.

## Unknown until inspected

Repository, branch, server, database, legacy source schema, media
storage, exact live DNS, gateway account configuration, AI provider and deployment status.

## Approved since v3.0

-   Paymob is the Egypt gateway (D-025).

## Future implementation entry point

Start the agent loop at T-P0-01 (environment inventory), then dependency-ready P0 tasks (stack ADR, skeleton + CI gates, brand tokens, module map, shared primitives).
Keep legacy production unchanged.

## Continuity fields to update after every milestone

Phase/milestone, repo/branch/commit, environment, deployed evidence,
implemented behavior, focused validation, migration state,
backup/rollback, open blockers and next milestone.
