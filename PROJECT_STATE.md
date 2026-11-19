# Young Go LMS --- PROJECT STATE

**Updated:** 2026-11-19 (pack v3.2)\
**Stage:** P8/P9 milestone verification on `feat/lms-build` in the single workspace required by D-029. See [the task board](agents/PROGRESS.md) for statuses.

The owner accepted P8 and P9 G7 with recorded design defects. Functional milestone
criteria and independent evidence are tracked in the [gate readiness record](docs/reviews/GATE_READINESS_P8_P9.md).
The post-fix catalog passes the measured lab LCP and CLS budget. See the
[performance report](docs/reviews/PERF_BUDGET_P8_2026-11-19.md) for page results and limits.

The latest P8/P9 work adds eligible bundle cohort selection, full-refund seat
release, scheduled learning reminders, three reports and account-closure
retention. The local demo includes private media fixtures and a fake checkout.
See the [continuation evidence](docs/reviews/GATE_READINESS_P8_P9.md#learning-and-checkout-continuation).

P10 candidate inventory is prepared. The owner confirmed the authoritative
legacy source, database and media are unavailable, along with the other release
facts listed in the [decision register](docs/07_DECISION_REGISTER.md#d-decisions-approved-2026-11-18-po).
The migration remains blocked at T-P10-01.

The [P0 continuity record](docs/evidence/p0-closure/README.md#2026-11-18-clean-clone-recheck) identifies the locally verified revision, demo results and open release inputs.

## Confirmed owner direction

-   Custom LMS from scratch.
-   Preserve current Young Go logo/brand and owner-stated
    `youngschool.com` domain; verify DNS before cutover.
    **Unconfirmed (T-P0-01, 2026-11-16): public observation contradicts —
    youngschool.com is a Learning Care Group (US childcare) site. Owner must
    confirm the real legacy hostname.**
-   Migrate all existing courses and related course content/assets.
-   All ages; configurable age suitability.
-   Parent/guardian account linked to child accounts.
-   Hybrid academy-owned and independent instructor course model.
-   Individual course purchases, bundles, monthly and annual
    subscriptions.
-   Egypt active; Saudi technically prepared but disabled.
-   Arabic and English from first release; extensible locales.
-   Central payment collection by Young Go; instructor settlements
    afterward.
-   Simple commission rules: percentage on individual sales, bundle
    course allocation + percentage, fixed amount per
    student/course/subscription period.
-   AI only for recorded lessons, based on current lesson curriculum
    PDF, concise answers, unavailable during live.
-   Unresolved AI request goes to instructor with admin copy; replies
    inside platform.
-   SKIT for admin/instructor panels; project separate from YFS.

## v3.1 additions

-   Paymob approved as Egypt gateway (D-025).
-   All v3.0 review gaps closed by approved decision, recommended default or tracked OPEN item (`docs/10_GAP_CLOSURE.md`).
-   Agent loop ready: orchestrator + lighter-model subagents + review + gates; 113 tasks in `agents/tasks/`; status board `agents/PROGRESS.md` (all `todo`).

## v3.2 additions

-   LearnHouse reference study, 32 engineering decisions, UX flows/patterns, traceability matrix (0 uncovered requirements), 123 tasks with AI touchpoint lines, verification subagents and G10/G11 gates.

## Documentation delivered

Master PRD, DMC, phase/milestone roadmap, delivery acceptance checklist,
architecture/data/API, simplified AI spec, decision register, migration
plan, Codex start instructions and Arabic README.

## Foundation continuity

- Development repository: [private GitHub young-go-lms](https://github.com/ibrahimmaher2503-source/young-go-lms), branch feat/T-P0-03-bootstrap.
- Linux CI run 43960234987 (023eae73d9b1d69dc49ed487c6f23780f19e21f5) verified MySQL migrations, Redis/Horizon job processing, scheduler and doctor, Pest coverage, RTL/translation checks and asset builds.
- Local Windows bootstrap uses SQLite, file cache and synchronous queues. Provisioning details are in docs/ENVIRONMENT_INVENTORY.md.
- Provisional brand tokens and bilingual preview are implemented. Official identity confirmation is O-027. Localization and shared domain primitives are implemented.
- T-P0-MG passed browser acceptance in CI run 43961974995 at fe6d741a82fda8e3609dc26fe182746b512c0e53. T-P1-02 authentication and profiles are in development.
- UNKNOWN: staging/production hosting, legacy hostname and read-only access, source migration data, external provider credentials, production backup/restore and cutover approval.


## Current visual refinement — 2026-11-17

Owner requires full reference matching across every current/future page. D-026 reference and rejected implementation are preserved in docs/design-system/references. Read VISUAL_ACCEPTANCE.md and TABLE_WORKSPACE.md before UI work. Shared native login/dashboard, table shell, tokens, fonts, avatars, filters and forms are implemented locally. Root personally owns final visual review. Existing business tasks remain at their actual states; this work does not close ApproveRejectAction, unapproved age cutoffs or downstream LMS features. Fresh verification evidence is recorded in agents/PROGRESS.md; do not infer completion from screenshot existence.


## P1 milestone gate record — 2026-11-17

P1 tasks T-P1-01..11 are implemented locally (commits through the T-P1-11 commit on feat/T-P0-03-bootstrap, not pushed). Local gates G1, G2, G4, G6, G7, G10 and G11 pass; 302 non-browser and 144 browser tests pass. The gate itself stays `review`: independent review waves, the staff-panel visual score (4.0 < 4.5), age-band numbers (O-015), official brand (O-027), approved terms/privacy versions, hosted CI at the new head and ED-030 budgets are open. Evidence and demo script: docs/evidence/p1-milestone/README.md. Registration now offers account type learner or parent (staff roles rejected). Local staff 2FA is enforced by default; set TWO_FACTOR_ENFORCED=false for local development only.
