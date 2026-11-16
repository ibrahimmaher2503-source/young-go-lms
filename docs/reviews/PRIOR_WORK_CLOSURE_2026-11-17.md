# Prior-work closure review — 2026-11-17

## Scope and conclusion

Owner direction: no new task starts; inspect and repair previously implemented P0/P1 work. This is a closure audit, not authorization to build downstream family, course, payment or AI features. Overall conclusion: **prior work cannot yet be declared fully closed**. Passing existing checks does not establish absence of unmet requirements.

## Current evidence

| Check | Observed result | Evidence / limit |
|---|---|---|
| Composer QA after repairs | Pint and Larastan pass; final rerun 187 tests, 738 assertions; 1 TODO | agents/logs/closure-qa-final.txt; I02 remains downstream scaffold |
| Full existing browser suite | Final rerun 28 tests, 158 assertions | agents/logs/closure-browser-final.txt; includes repaired screens and added drop interaction coverage |
| Affected staff browser rerun | 8 tests, 80 assertions | agents/logs/staff-browser-after-repairs.txt; both locales/themes/viewports, axe/JS/page-overflow assertions |
| Translation keys / RTL | Pass | Structural checks; they cannot find untranslated database labels |
| Production asset build | Pass, 34 semantic contrast pairs | Local build; does not prove every dynamic UI state |
| Locked dependency audit | Exit 0, no advisories | agents/logs/closure-composer-audit.json |
| P0 phase G10 | Pass | agents/logs/closure-p0-g10.txt; presence of tags/prose is not a semantic audit |
| Seeded local database | 16 settings, 10 roles, 64 permissions, 0 age bands | agents/logs/closure-seed-summary.txt; no unapproved age defaults |
| Local doctor after setup | Applicable checks pass | agents/logs/closure-doctor.txt; mail delivery untested, Redis skipped by local sync/file profile; heartbeat is point-in-time |
| Hosted CI | Run 43967044917 on a14fae2 succeeded | Earlier head; current local staff repairs not yet hosted-verified |

## Repairs already made

1. ExportCsvAction now calls Gate::authorize at execution before querying rows. A permission-revocation regression failed before repair and passes afterward. Combined staff/export checks: 11 tests, 55 assertions.
2. Staff user headings and statuses use Arabic/English translations and the registered StatusBadgeColumn. Bilingual rendered regression failed before repair and passes afterward.
3. Staff UI browser checks and generated screenshots expose actual dark mode, locale direction, JS, accessibility, and horizontal page overflow. Earlier dark-button contrast and keyboard access to scrollable tables were repaired.
4. Setup/CI includes filament:assets; published vendor assets are ignored. These changes are still local.
5. Created absent local SQLite only if missing, applied migrations/seed, and ran scheduler heartbeat; no fresh/reset or overwrite of existing data.
6. Corrected stale current scope, Handoff stage, CI inventory and implemented reuse-registry statuses.

## Remaining prior-work closure items

| Item | Requirement / owning task | Disposition |
|---|---|---|
| Shared ApproveRejectAction absent | T-P1-08 step 3 | Implementation gap; do not close task. Business mutation/audit must remain in Domain Actions, with reason validation and execution authorization |
| Raw role/permission keys displayed in Arabic | T-P1-08, AGENTS bilingual rule | Add display labels for table and both selectors, preserve canonical keys; retest AR/EN |
| Ten-role menu matrix | T-P1-08 A1 | Repaired: 10 roles × 3 panels, explicit visible/hidden resource links; PanelAccess suite 34 tests /134 assertions PASS (closure-panel-menu-check.txt) |
| Initial age-band seeds absent | T-P1-07 step 1 | Numeric values are OPEN O-015; neither PRD5.1 nor D-005 supplies them. Do not invent defaults or count seed step as complete |
| Milestone demo record | P0-MG / verification milestone gate | Reproducible demo and actual execution record consolidated in docs/evidence/p0-closure/README.md; independent review/exact-head hosted result pending |
| Roleless self-registration | P1-02/P1-03 integration, PRD J01 | Repaired: assign seeded student role inside registration transaction, ignore submitted staff roles/status; actual HTTP registration suite 14 tests /75 assertions PASS (registration-role-green.txt) |
| SMTP provider-validation completeness | P0-07 / ED-022 | Doctor constructs selected mail transport; explicit provider credentials check covers SES/Postmark/Resend, not SMTP. Validate actual missing-config cases before treating as a defect; no mail delivery claim |
| Status editing interpretation | T-P1-08 step 4 | Display/filter exists. Historical progress called editing pending; require actual spec support before adding a mutation solely to satisfy that note |
| Audit query indexes | P1-04 / review R3 | Event/date filtering and sorting lack supporting composite indexes; SHOULD fix before audit history grows |
| File-drop interaction evidence | P1-09 | Repaired: actual DragEvent tests for single/multiple/disabled inputs AR/EN, 2 tests /6 assertions PASS (closure-file-drop-check.txt) |
| Shared HTML language/direction helper | P1-01 step 5 | Current previews set attributes directly; reusable helper/directive requested by the task is absent. Do not claim this implementation step complete; product layouts remain P1-10 |
| Exact-head hosted checks and independent closure verdicts | Review/verification protocols | Remain pending; current green CI is an earlier head |

## Scope boundaries and review corrections

- P1-05 was not implemented; it is held under owner direction, not silently completed. The subagent's write attempt was rejected by automatic approval due to usage limit; no files written.
- The file-upload primitive already implements delegated drag/drop and filename updates in resources/js/ui-primitives.js. A review's initial missing-drag/drop finding was retracted after source verification; it is not a confirmed defect.
- P1-08 is a panel shell task. Referencing PRD19.1 does not make all later domain KPI/dashboard functionality part of this shell's closure.
- I02's TODO is explicitly planned scaffolding in T-P1-03. It is not proof of real guardian/instructor object isolation; later owning features must fill and pass those cases.
- Official brand, legacy hostname, staging/production services, approved legal versions, provider credentials, minor AI/privacy policy and age cutoffs remain external gates. None is inferred from local tests.

## Next action

Resolve and independently verify the listed old-scope gaps before starting another task. Keep P1-03/P1-04/P1-07 under review and P1-08 in progress; do not promote them from tag-coverage results alone. This document is a current evidence record; agents/PROGRESS.md remains the sole task-status authority.
