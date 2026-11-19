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

## Continuity record

- Demo walk-through written: `docs/DEMO_SCRIPT.md` (19 steps plus a PO checklist). Steps are tagged SHOW, PARTIAL or GAP; GAP items are not claimed as working.
- Demo accounts and setup stay in `docs/DEMO.md`. It lists no finance user, so the finance-only steps need one created by the PO before the demo.
- Not verified by running the app: the script was written from code and PROGRESS notes. Expected values marked "verify on first run" are unconfirmed.
- The gate stays `todo` until the G7 screenshot review and the owner decisions in the list above are done.

## PO decision 2026-11-19: P8 accepted with known defects

The owner accepted P8 as it stands, with the defects listed in `docs/reviews/G7_P8_REVIEW4_2026-11-19.md` (and fix batch E notes) recorded as known and not blocking.
- G7 for P8 is closed as "accepted with known defects", not as a 4.5 pass. No further fix cycles are required for P8.
- Remaining defects stay as backlog items. They are not claimed as fixed.
- T-P8-MG stays `todo` until the other gate criteria (owner decisions, performance budget) are done.

## PO decision 2026-11-19: P9 design-only defects accepted

The owner accepted the remaining design-only defects of P9 (re-review `G7_P9_REREVIEW_2026-11-19.md`: mean 4.18, none at 4.5). Functional defects X1-X5 and D1 were fixed (commit `beebd04`). Open and accepted as backlog: D2, C1, H1, X6, X7, W1b, P1, R2 (partial). G7 for P9 is closed as "accepted with known defects". T-P9-MG and T-P8-MG still need the owner-fact items (see HANDOFF_NEXT_PROMPT.md) before they can be marked done.

## 2026-11-19 continuity check at `85ca87c`

Independent PRD audit by `p8p9_gate_audit` (Sol): **CHANGES REQUIRED**
for both milestones under ORCHESTRATOR §8 item 3 and the phase specs.

| Criterion | P8 | P9 |
|---|---|---|
| Task closure and independent evidence | T-P8-01..13 remain in review. | T-P9-01..11 remain in review. |
| G7 | Owner accepted with recorded design defects. | Owner accepted with recorded design defects. |
| Demo | The scratch demo browser on `4bbdcc7` verified fake card failure, retry and paid confirmation on order 6. The script covers bundle and plan entry, refund access, commission and settlements. The full walkthrough is pending. | Script available. Independent demo on reset data, including the approved PDF/key-term fixture, remains open. |
| Checklist | §10 remains open. Live items may be marked pending under the P8 acceptance criteria. B-07 (real Paymob), O-007 (Egyptian seller/tax) and O-023 (ETA applicability) remain live-release blockers. | §11 remains open. Platform overview, student progress and content reports are missing. Required notification wiring remains open in the matrix. |
| Performance | Course detail and checkout meet the LCP/CLS budgets after the Feature suite finished. Course detail exceeded LCP under concurrent test load. See the [lab performance report](PERF_BUDGET_P8_2026-11-19.md). | Full performance evidence for applicable public and learner pages remains open. |
| Continuity | Recorded in project state and handoff. | Recorded in project state and handoff. |

The current code has the disabled-Saudi webhook guard, individual-course cohort
holds, public bundle and plan entries, the learner subscription page, and
bundle/subscription access revocation for eligible refunds. Refunded and voided
gateway callbacks still flag for review. The approved O-025 policy keeps existing
defaults and protects financial records. The learning-record lifecycle of
account active plus two years remains unimplemented.

The owner confirmed the requested [release facts](../07_DECISION_REGISTER.md)
are unavailable. P10 remains blocked at T-P10-01. The authoritative legacy
dataset and verified source identity are needed before work can advance along
the P10 dependency chain.

### Regression and local coverage

- Vite production build: exit 0, including contrast for 34 token pairs.
  Translation, RTL and brand token commands exited 0.
- Per-folder Windows feature run plus root Feature files: 2214 passed,
  12 existing TODOs, 4 skipped and 0 failures in the final folder results.
  Settings was rerun after finalizing its new Orders assertions. Arch and
  Invariants passed separately (17 tests, 107 assertions). Commands used
  `php -d memory_limit=2G vendor/bin/pest <path>`. A separate Manual Payment
  Proof run with qpdf 12.4.2 available passed 52 tests with one skip.
- Telescope: the production-style `composer install --no-dev` bootstrap
  reproduced a missing base provider class before the fix. Package discovery
  and production-mode bootstrap then passed with the guard in `4bbdcc7`.
  The Windows scratch install bypassed POSIX extension checks for Horizon.
  The 11 focused Telescope privacy tests passed with dev dependencies.
- The local fake card walkthrough exercised a seeded checkout order. Receipt
  acceptance here covers browser fail, retry and paid confirmation. The payment
  features, cohort fixtures and payout views use synthetic test data.
- Final focused checks: mixed bundle coupon and cohort A8/A9, 48 passed
  (273 assertions, two existing TODOs); finance payout views and StatementPdf,
  61 passed (335); full Settings with new Orders list and read-only details,
  51 passed (366, two existing TODOs). Statement PDF checks cover Blade output
  and data. A Chromium-generated PDF remains a separate check.
- Restored Watermark browser regressions: 16 passed (124 assertions), including
  playback, masking and actual fullscreen in both locales and themes. Full
  T-P4-07 A3 still needs periodic motion and explicit T2 render evidence.
- Sol independent test writers and code review were used in this Codex session.
  The requested Haiku model is unavailable here.
