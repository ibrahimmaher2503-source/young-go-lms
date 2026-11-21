# Young Go LMS --- Developer Handoff

**Updated:** 2026-11-19 (pack v3.2)\
**Current stage:** P8/P9 milestone verification on `feat/lms-build`. Use the canonical workspace in D-029 and read [the task board](agents/PROGRESS.md) before starting a task.

Owner G7 acceptance and outstanding functional criteria are recorded in
[P8/P9 gate readiness](docs/reviews/GATE_READINESS_P8_P9.md). Use the
[demo walk-through](docs/DEMO_SCRIPT.md) against a seeded scratch database.
See the [performance report](docs/reviews/PERF_BUDGET_P8_2026-11-19.md)
for the lab method and measured pages.

D-030 schedules access-expiry and assignment reminders at 7, 3 and 1 days.
D-031 adds a buyer-selected cohort per eligible live bundle course. Rehearse
this path with the [demo script](docs/DEMO_SCRIPT.md). Before rolling back the
multi-hold migration, check whether any order line already has multiple holds.
The previous schema cannot store those selections. Use the
[gate readiness record](docs/reviews/GATE_READINESS_P8_P9.md#learning-and-checkout-continuation)
for automated and independent evidence.

The owner confirmed the [requested release facts](docs/07_DECISION_REGISTER.md)
are unavailable. P10 depends on T-P10-01, which needs authoritative read-only
legacy source, DB and media plus T-P3-MG. Keep live payments, ETA submission,
Saudi checkout and live AI activation gated. Use fake integrations locally.

Telescope is a dev dependency and its application provider is loaded when the
package is installed. The quality workflow checks that Laravel also boots with
`composer install --no-dev`. Repo choices for `main` and the original review
findings document are pending owner direction.

The [P0 clean-clone continuity record](docs/evidence/p0-closure/README.md#2026-11-18-clean-clone-recheck) records the tested revision, demo, gate results and open deployment, domain and recovery facts.

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

Use agents/PROGRESS.md for the active task board, docs/ENVIRONMENT_INVENTORY.md for verified environment facts, and README.md for setup. Development uses [private GitHub young-go-lms](https://github.com/ibrahimmaher2503-source/young-go-lms), branch `feat/lms-build`. Historical CI run 43960234987 (023eae73d9b1d69dc49ed487c6f23780f19e21f5) passed the foundation and localization checks with MySQL and Redis/Horizon. Local bootstrap uses SQLite. The token preview is opt-in in local or staging environments.

The legacy hostname and read-only access are unconfirmed. Staging and production deployment, migration, backup and rollback evidence are pending. Use the migration plan and obtain recorded approval before cutover. The current scope is review and repair of already implemented tasks. Use the Current section and closure audit in agents/PROGRESS.md; do not infer completeness from this historical summary.

P0 milestone browser acceptance and its invariant tests passed in CI run 43961974995 at fe6d741a82fda8e3609dc26fe182746b512c0e53.


## Current visual refinement — 2026-11-17

Owner requires full reference matching across every current/future page. D-026 reference and rejected implementation are preserved in docs/design-system/references. Read VISUAL_ACCEPTANCE.md and TABLE_WORKSPACE.md before UI work. Shared native login/dashboard, table shell, tokens, fonts, avatars, filters and forms are implemented locally. Root personally owns final visual review. Existing business tasks remain at their actual states; this work does not close ApproveRejectAction, unapproved age cutoffs or downstream LMS features. Fresh verification evidence is recorded in agents/PROGRESS.md; do not infer completion from screenshot existence.


## P1 handoff — 2026-11-17

Resume P1 closure from [the task board](agents/PROGRESS.md), [workspace visual review](docs/reviews/WORKSPACE_VISUAL_REVIEW_2026-11-17.md) and [milestone evidence](docs/evidence/p1-milestone/README.md#closure-follow-up-2026-11-17). The identity, role-management, family and 2FA journeys have fresh local test evidence. The review still needs the remaining D-026 comparisons and independent verdicts, the O-015 age defaults and the G5 I02 coverage item. The primary checkout is on `feat/lms-build`.


## Review closure pass — 2026-11-21

Orchestrator closure of the `review` queue on `feat/lms-build` at committed HEAD `86e1730` plus the working tree (nothing from this pass is committed yet). The owner accepted the deferred visual scope in [D-032](docs/07_DECISION_REGISTER.md); every other gate still applied.

- **Status:** 90 of 123 tasks are `done` (15 before the pass). Every closure row in [the task board](agents/PROGRESS.md) names the suites run, their counts and the gates checked.
- **Shared gates (working tree):** G2 Larastan, G4 Arch, G5 Invariants (11), G6 translations/RTL/composer audit/`migrate:fresh --seed` on a scratch database/`npm run build`, and G11 doctor pass. G1 fails only on `app/Filament/Resources/Courses/Pages/ListCourses.php`, an uncommitted parallel edit; the committed file passes. G8 `ai:evaluate --provider=fake` passes. G9 `--group=payments` passes (150).
- **Milestone demos:** `tests/Feature/Milestone/P1DemoTest.php` … `P6DemoTest.php` pass end to end. Phase requirement coverage (G10) passes for P0–P6.
- **Fixed in this pass:** subscription grace badge after `grace_ends_at`; post-commit disk resolution in `AnonymizeAccount`; `ReleaseQuizResult` queried a missing `reviewed_at` column (now `graded_at`); certificate footer overflow at A4 landscape; unfocusable revision scroll regions; uncompilable `course-thumb` component; staff-panel dark mode (new `filament/shared/theme-bridge.blade.php`). See `docs/reviews/BUGS_FOUND.md`.
- **Parallel uncommitted work:** about 84 modified files from another tool (public header and theme toggle, curriculum action groups, Arabic copy, demo seeders) are uncommitted. Several browser suites fail only on that tree; those tasks were closed on committed-HEAD browser runs, which the board rows state. The owner must decide whether that work is committed or discarded, and the affected browser tests must be updated with it.
- **Open:** T-P1-08 (sidebar fit at 1568 px), T-P7-06 (assistant controls overflow at 390 px), T-P2-08 (owner visual acceptance), T-P9-11 (J01–J12 qa-verifier run), milestone gates P1/P2/P7/P8/P9, T-P7-07 and P10 (blocked on owner inputs), P11/P12.
- **Owner decisions still pending:** commission rates O-008..O-011 (zero defaults), settlement approval O-012, retention O-025, refund revoke policy, Paymob credentials B-07, legacy access O-001/O-003/O-018, AI provider key and privacy gate for T-P7-07.
