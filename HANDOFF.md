# Young Go LMS --- Developer Handoff

**Prepared:** 2026-11-16 (pack v3.1)\
**Current stage:** P0/P1 implementation exists; prior-work closure audit is active. Do not start new tasks until its recorded gaps are resolved.

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

Staging and production server/database, legacy source schema, media
storage, exact live DNS, gateway, AI provider and deployment status.

## Approved since v3.0

-   Paymob is the Egypt gateway (D-025).

## Resume development

Use agents/PROGRESS.md for the active task board, docs/ENVIRONMENT_INVENTORY.md for verified environment facts, and README.md for setup. Development uses [private GitHub young-go-lms](https://github.com/ibrahimmaher2503-source/young-go-lms), branch feat/T-P0-03-bootstrap. CI run 43960234987 (023eae73d9b1d69dc49ed487c6f23780f19e21f5) passed the foundation and localization checks. Local bootstrap uses SQLite; hosted CI verifies MySQL, Redis and Horizon. The token preview is opt-in in local or staging environments.

The legacy hostname and read-only access are unconfirmed. Staging and production deployment, migration, backup and rollback evidence are pending. Use the migration plan and obtain recorded approval before cutover. The current scope is review and repair of already implemented tasks. Use the Current section and closure audit in agents/PROGRESS.md; do not infer completeness from this historical summary.

P0 milestone browser acceptance and its invariant tests passed in CI run 43961974995 at fe6d741a82fda8e3609dc26fe182746b512c0e53.


## Current visual refinement — 2026-11-17

Owner requires full reference matching across every current/future page. D-026 reference and rejected implementation are preserved in docs/design-system/references. Read VISUAL_ACCEPTANCE.md and TABLE_WORKSPACE.md before UI work. Shared native login/dashboard, table shell, tokens, fonts, avatars, filters and forms are implemented locally. Root personally owns final visual review. Existing business tasks remain at their actual states; this work does not close ApproveRejectAction, unapproved age cutoffs or downstream LMS features. Fresh verification evidence is recorded in agents/PROGRESS.md; do not infer completion from screenshot existence.


## P1 handoff — 2026-11-17

Resume at agents/PROGRESS.md. Identity, RBAC, audit, age model, staff panels, UI primitives, family accounts, 2FA and the AR/EN account screens exist locally. Read docs/evidence/p1-milestone/README.md for gate results and the open items list. Next buildable tasks without external inputs: T-P2-01 (categories) and the independent verification waves for T-P1-03..11. Do not enable live payments, Saudi checkout, AI for minors or a domain switch.
