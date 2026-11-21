# Launch evidence — PRD §26 gates (T-P11-01)

Date: 2026-11-21. Branch: `feat/lms-build`, working tree (not a clean commit). This file answers acceptance items A1 and A2 of T-P11-01 in `agents/tasks/P11.md`.

## How to read this

Status words are strict.

| Status | Meaning |
|---|---|
| MET | A passing test or a recorded review proves it. Nothing outside the repo is needed. |
| MET LOCALLY | Proven on the local stack with fake providers or accepted defaults. Needs staging or production confirmation. |
| BLOCKED | Cannot be proven yet. The owner input or blocker is named. |

Every test below was re-run on 2026-11-21 on this working tree with `php -d opcache.enable_cli=0 -d memory_limit=2G vendor/bin/pest <file> --filter="<name>"`. All of them passed. Browser (Playwright) suites were not re-run for this file. They are cited from `agents/PROGRESS.md` only.

Short names: P1..P9 demo = `tests/Feature/Milestone/P<n>DemoTest.php`, test `runs the P<n> demo script end to end`.

## Summary

| Status | Gates | Count |
|---|---|---|
| MET | 1, 2, 3, 4, 6, 7, 9, 10 | 8 |
| MET LOCALLY | 8, 11, 12, 13 | 4 |
| BLOCKED | 5, 14, 15 | 3 |

## 1. Gate map

| # | Gate (PRD §26) | Evidence | Status |
|---|---|---|---|
| 1 | Visitor discovers a published course and registers or logs in | P2 demo (87/113 assertions across P1 and P2 demos). `tests/Feature/AuthenticationTest.php`: `records only birth year and month and current consent versions`, `rate limits login using the configured limit`. | MET |
| 2 | Parent sees only linked children and permitted records | `tests/Feature/Invariants/I02_ObjectScopeTest.php`: `scopes guardian access to verified linked children only`, `enforces object ownership and relationship scopes in policies` (3 cases). P4 demo covers the parent dashboard. | MET |
| 3 | Admin or instructor creates, previews, approves and publishes a bilingual course | P3 demo (wizard, pricing, curriculum, PDF ingestion, review, publish, revision, duplicate, delete blocked). Fixes in `docs/reviews/SECURITY_FIXES_2026-11-20.md` (T-P3-12). | MET |
| 4 | Ordered sections, recorded lessons, PDFs, quizzes, assignments, live sessions | P3 demo (sections, PDFs), P5 demo (live: capacity, waitlist, join window, attendance, replay), P6 demo (quiz, essay, late assignment, certificate). | MET |
| 5 | Every migrated legacy course reconciles to source inventory | No legacy data has been imported. T-P10-01 is `blocked` in `agents/PROGRESS.md`. Importers and `legacy:verify` exist with a synthetic fixture only (`docs/migration/INVENTORY.md`, `MAPPING_DRAFT.md`). | BLOCKED: O-003 (legacy source, DB, storage), O-001 and B-01 (hostname and source identity), B-06 |
| 6 | Entitled student opens a course, resumes, downloads a PDF, sees accurate progress | P4 demo (free enroll over HTTP, grant, player, progress, dashboards, private files, cohort drip lock). `tests/Feature/Learning/CoursePlayerTest.php`: `plays the lesson for an entitled learner with captions, toolbar, shortcuts and navigation`. `tests/Feature/Media/PrivateFilesTest.php`: `opens protected files to entitled learners through the entitlement checker`. | MET |
| 7 | Non-entitled user cannot reach protected media, PDF, live or AI | `CoursePlayerTest`: `gives a learner without access a 404, not a 403, on lesson URLs`. `ProtectedPlaybackTest`: `binds protected playback to its viewer and refuses anonymous or copied access`. `PrivateFilesTest`: `gives out a signed link only to people who may open the file, and fails closed for learners`. `tests/Feature/Live/JoinLinkTest.php`: `refuses learners without access, with a 404 that hides the session`, `never puts the meeting link in the page...`. `tests/Feature/AI/AssistantEligibilityTest.php`: `fails closed with the real entitlement service when the learner has no entitlement`. | MET |
| 8 | AI only on recorded lesson PDFs, concise and grounded, off during live | `AssistantEligibilityTest`: `is unavailable with the right reason when one condition fails` (14 cases, includes `NotRecordedLesson`). `tests/Feature/AI/AssistantPanelTest.php`: `omits assistant panel on live items, text lessons, and ineligible lessons...`. `AssistantEnginePipelineTest`: `answers question with verified quote...`. `AssistantEvaluationTest`: `evaluates assistant with fake provider...` (red-team 31/31, age-tier 6/6 per PROGRESS). P7 demo. All use the fake provider. | MET LOCALLY: O-014 (real provider, region, budget), T-P7-07 real-provider eval, B-08 |
| 9 | "Still not understood" creates an instructor request with admin copy, replies stay in-platform | `tests/Feature/AI/AssistantFeedbackTest.php`: `creates exactly one help request on not_understood feedback...`, `routes request to admin queue when course has no manage-scope reply instructor and notifies managers`. `HelpRequestLifecycleTest`: `handles staff reply: transitions state, stops SLA...`. `DecisionsMinorHelpRequestTest`: `notifies the safeguarding group once for a minor help request...`. P7 demo. | MET |
| 10 | Individual, bundle and subscription access use one entitlement service | `tests/Feature/Invariants/I01_EntitlementOnlyViaServiceTest.php`: `lets only the Entitlements module touch the entitlements table`. `EntitlementServiceTest`: `creates one row for the same source however many times it is granted`. P8 demo (bundle, subscription). | MET |
| 11 | Egypt checkout follows the approved legal and gateway setup; Saudi checkout is disabled | Saudi off: `tests/Feature/Invariants/I15SaudiMarketTest.php`: `refuses checkout in a disabled market`; `tests/Feature/Commerce/DisabledSaudiMarketTest.php`: `keeps Saudi order and payment flows disabled...`. Egypt: Paymob adapter and manual proofs pass with the fake gateway (P8 demo). Live adapter is not activated. | MET LOCALLY (Saudi part is MET): B-07, O-006 (Paymob credentials, methods, Instapay flow), O-007 and O-023 (seller legal details, e-receipt) |
| 12 | Instructor earnings follow approved simple rules and reconcile to central collections | `tests/Feature/InstructorFinance/CommissionLedgerTest.php`: `writes the same earnings rows when RecordOrderEarnings runs twice`. `StatementsAndSettlementsTest`: `A1 freezes the ledger sums of a closed month into a statement equal to LedgerTotals`. `tests/Feature/Commerce/PaymentReconciliationTest.php`: `A1 a different amount or currency is an amount_mismatch`. Rates are the owner-accepted zero defaults (D-040). | MET LOCALLY: O-008..O-012 real rates and settlement rules (D-040 defaults only); live reconciliation needs B-07 |
| 13 | SKIT and student UI pass responsive AR/EN review | Owner accepted the current visual design for D-028 tasks (D-032, 2026-11-21). Browser suites with axe and overflow checks are recorded in `agents/PROGRESS.md` (for example `CoursePlayerBrowserTest` 15, `PublicCatalogBrowserTest` 14, `FamilyFlowsTest` 98, `AssistantPanelBrowserTest` 24). PROGRESS also records working-tree failures from other tools' uncommitted public-shell, theme and curriculum edits. Not re-run here. | MET LOCALLY: needs a clean-commit browser rerun and the visual polish D-032 deferred |
| 14 | Critical authorization, payment idempotency, file access, migration, backup/restore and rollback checks pass | Authorization: `tests/Feature/Filament/PanelAccessTest.php` `permits only the assigned panel routes for each role` (30 cases), I02 tests above. Payment idempotency and signature: `PaymobWebhookVerificationAcceptanceTest` `fulfils once when the same signed success arrives three times`, `answers 401 for wrong, missing, empty and altered-payload signatures...`; `PaymobWebhookFulfillmentTest` `fulfills paid orders once with idempotent webhook replay...`. File access: `PrivateFilesTest`, `ProtectedPlaybackTest`. The migration, backup/restore and rollback parts have no evidence. | BLOCKED: migration (O-003, T-P10), backup/restore drill and rollback (T-P11-03, B-05, O-002, O-004, O-005 staging and production hosting) |
| 15 | Training, support ownership, operational docs and handoff complete | T-P11-05, T-P11-06 and T-P11-07 are `todo`. No admin, instructor, finance or support guides exist in `docs/`. | BLOCKED: T-P11-05..07 not started; support ownership and hosting need owner input (O-001, O-002, B-05) |

## 2. Issue status

### 2.1 Critical and high findings

I checked that the fix is still present in the code at the cited place on 2026-11-21. "Test" means a passing test that exercises the fix. A "regression test" means a test written to fail without the fix.

| ID / source | Severity | Fix check in code | Test evidence | Status |
|---|---|---|---|---|
| SECURITY_REVIEW: private upload forms trust existing storage paths | High | Present. `storeFiles(false)->preventFilePathTampering()` on 4 inputs in `app/Filament/Resources/Courses/Pages/ManageCurriculum.php` (lines 327, 519, 535, 539) and 1 in `app/Filament/Resources/Library/Pages/ListLibraryItems.php:36`. Commit `b74d0cf`. | Existing suites passed at fix time (70 tests). No regression test for the attack exists (`docs/test-prompts/SEC-6.md` not yet written). No independent re-review. | Fixed in code. **Not verified by a regression test.** Treat as open for A1. |
| SECURITY_REVIEW: Paymob verified transaction not bound to its checkout attempt | High (before Paymob activation) | Present. `ProcessPaymentWebhook` flags `missing_provider_order`, `provider_order_mismatch`, `reference_mismatch` for non-fake gateways and looks up by `(gateway, provider_order_id)`. `PaymobGateway::createCheckout()` requires `intention_order_id`. Commit `0f46b30`. | Existing Paymob suites pass (re-run: 3 cases above). No test covers the three flag reasons (`SEC-7.md` not yet written). Real Paymob fields unconfirmed. | Fixed in code. **Not verified by a regression test or a sandbox transaction.** Treat as open for A1. |
| GAP-CODE T-P4-02: free enrollment had no endpoint | Critical | Present. `routes/pages.php:90` `POST /courses/{course}/enroll` (`catalog.enroll`). | `CheckoutUiTest` enroll cases (2 passed), P4 demo. | Fixed and verified |
| GAP-CODE T-P7-02: adult-age mismatch and no AI budget reservation | High | Present. `BudgetGate::reserve()` with locks. `GuardianAccess` uses `ai.adult_age`. | `AssistantInputGuardsCacheQuotasTest` budget case, `AssistantEligibilityTest` (14 cases). | Fixed and verified |
| GAP-CODE T-P4-09: day-based drip ignored cohort start | High | Present. `EntitlementService.php:87` `seatedCohortStart()`. | P4 demo (cohort drip lock). | Fixed and verified |
| GAP-CODE T-P3-12: course delete ignored order history, no lock | High | Present. `DeleteCourse.php:57-59` locks the row, then `blockers()`. | `AuditCatalogRegressionTest` (6 passed), P3 demo (delete blocked). | Fixed and verified |
| BUG-001 cohort dates overwritten | High | Fix recorded; not re-opened. | `CohortEdgeCasesTest` (14 passed in this run). | Fixed and verified |
| BUG-007 `Order` enum cast fatal | Critical | Present. `app/Domain/Commerce/Order.php:54`. | `PaymobWebhookFulfillmentTest`, P8 demo. | Fixed and verified |
| BUG-008 assistant opener missing on embed lessons | High (UX) | Present. `learn/show.blade.php:178,187`. | `AssistantPanelTest` (passed). | Fixed and verified |
| GAP-P6-F3 required quiz did not gate lessons | High | Fix recorded in BUGS_FOUND. | P6 demo, `QuizRuntimeTest` per BUGS_FOUND. | Fixed and verified |
| GAP-P9-04 minor check failed open | High | Present. `GuardianAccess.php:41` reads `ai.adult_age` and fails closed. | `AssistantEligibilityTest` adult-age case; `DecisionsMinorHelpRequestTest`. | Fixed and verified |
| I01 direct entitlement table access | Critical | Present. `CapEntitlementsEndForSource` and other boundary actions under `app/Domain/Entitlements/`. | `I01_EntitlementOnlyViaServiceTest` passed. | Fixed and verified |
| BUG-P4-MG-01 `course-thumb` blade did not compile | High | `course-thumb.blade.php` now compiles. | `BUGS_FOUND.md` still says **Open**, but P4 demo (dashboard steps) passes in this run. The doc row is stale. | Fixed and verified. Update the BUGS_FOUND row. |
| BUG-P9-05-01 quiz release notice query failed | High | Present. `ReleaseQuizResult.php:64` uses `graded_at`. | `QuizResultNoticeTest` per PROGRESS (not re-run here). | Fixed and verified (from PROGRESS) |

### 2.2 Other open items (not critical or high)

| ID | Severity | State |
|---|---|---|
| SECURITY_REVIEW: view-as keeps access after eligibility loss | Medium | Fixed in code (`ViewAsState.php:42`, `END_REVOKED`). No regression test (`SEC-9`). |
| SECURITY_REVIEW: AI budget omits reservation cost | Medium | Fixed in code (`BudgetGate.php:89-90`). Budget test passes. |
| SECURITY_REVIEW: Telescope retains sensitive request data | Medium | Fixed in code (`config/telescope.php` default off outside local, payment paths ignored). `TelescopePrivacyTest` passes but has no case for the new default. |
| BUG-P5-MG-01 no `.ics` export | Medium | Open. No P5 acceptance item needs it. |
| Fake gateway Back button gives 404 | P2 demo | NEEDS-OWNER (`SECURITY_FIXES_2026-11-21.md`). |
| Dependency audit | — | Composer and npm audits were clean on 2026-11-20. Not re-run. |

### 2.3 Does A1 hold?

**No, not yet as a verifiable claim.**

- No critical or high finding is known to be open in the code. Every fix I checked is still in place.
- Two High findings (upload paths, Paymob binding) have no regression test and no independent re-review. The review that raised them said to keep T-P11-01 open until both exist.
- The Paymob finding also needs a sandbox transaction before live activation (B-07, O-006).
- Items from the T-P11-01 steps that have no evidence yet: the full route-by-role sweep, MySQL concurrency, staging headers and the browser journeys J01 to J10 on a clean commit.

To close A1: write the SEC-6, SEC-7, SEC-9 and SEC-11 regression tests, run an independent re-review of the two Highs, fix the stale BUGS_FOUND row, then re-run the dependency audits.

## 3. What this file does not prove

- Hosting, DNS, mail, backups, monitoring and rollback (T-P11-03, T-P11-04, T-P11-06).
- Real Paymob, real AI provider, real mail delivery.
- Production performance. `docs/reviews/PERF_BUDGET_FULL_2026-11-21.md` is a local lab run on SQLite.
- Decisions D-032 and D-040 relax visual and commerce defaults. They do not remove the blockers above.
