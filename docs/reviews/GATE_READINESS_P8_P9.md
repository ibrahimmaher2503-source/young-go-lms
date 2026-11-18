# Gate readiness: T-P8-MG and T-P9-MG

Date: 2026-11-18. Branch `feat/lms-build`. Prepared by the orchestrator session; this is a readiness record, not a gate pass.

## Evidence in hand

| Area | Result | Command |
|---|---|---|
| P8 acceptance tests (T-P8-01..12) | 471 passed, 8 todo, 4 skipped, 0 failed | `pest tests/Feature/Commerce tests/Feature/Subscriptions tests/Feature/InstructorFinance tests/Arch tests/Feature/Invariants` |
| P9 acceptance tests (T-P9-01..11) | all new files pass; 195 passed across Notifications, Reports, Privacy, Settings, Support, Arch, Invariants | per-file `pest` runs |
| Arch + Invariants | 17 passed | `pest tests/Arch tests/Feature/Invariants` |
| Translations / RTL | pass | `app:check-translations`, `app:check-rtl` |
| Static analysis | no errors (use `--debug`, parallel workers run out of memory) | `phpstan --memory-limit=2G --debug` |

## Why the gates stay `todo`

Gate criteria (ORCHESTRATOR.md section 8.3): every task of the phase done, demo script, checklist items, performance budget for public/student UI, continuity record.

1. **G7 visual match is open for every UI task in P8 and P9** (checkout, receipts, refunds, statements, settlements, reports, dashboards, privacy page, preferences page, Learner 360, settings hub, sidebar). No independent screenshot review has been run.
2. **T-P8-13** independent audit says CHANGES REQUIRED: webhook SA check and decision O-013. `Market::isActive()` hard-codes Saudi Arabia inactive; two tests are skipped on this. Owner decision (T-P12-01 owns activation).
3. **OPEN decisions the code uses as documented defaults** and that need owner sign-off: O-008..O-011 (commission rates, all zero), O-012 (settlement approval, no four-eyes rule), O-025 (retention periods), refund revoke policy `config/refunds.php`, opt-out defaults for notifications, B-07 (Paymob credentials; reconciliation and refund endpoints unverified against real docs).
4. **Known functional gaps** recorded in the PROGRESS notes: cohort seat reservation at checkout, no first-purchase entry for plans, no learner subscription page, refunds do not revoke subscription or bundle access, queued/PDF exports, platform overview and student progress reports, `ExecuteRefund` webhook callbacks not matched to refund rows, Filament-layer coverage in several test files (see their `todo` entries).
5. **Performance budget** (LCP, CLS, INP) not measured for new public pages (catalog filters, checkout).

## To pass the gates

- Run the independent screenshot review (G7) for the UI tasks above and record scores.
- Owner decisions for item 3; confirm or change the defaults.
- Measure the performance budget on the catalog and checkout pages.
- Write the demo script (`docs/DEMO.md` has the dataset; the walk-through is missing).
- Update PROJECT_STATE.md and HANDOFF.md with the continuity record.
