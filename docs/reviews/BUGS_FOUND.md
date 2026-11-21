# Bugs Found During Test Implementation

## Overview of Resolutions (Updated 2026-11-18)

All previously identified product bugs (`BUG-001` through `BUG-009`, `BUG-P7-06-01`), design/token gaps (`GAP-P6-F8`), domain gaps (`GAP-P6-F3`, `GAP-P2-06`, `GAP-P9-04`), static analysis errors (`LARASTAN-001`), architectural invariants (`I01`), and host binary dependencies (`HOST-QPDF`) have been **completely fixed and verified with green tests and exit code 0**.

---

## 1. Bugs Resolved & Verified

| ID | Severity | Module | Location / Root Cause | Resolution & Verification | Status |
|---|---|---|---|---|---|
| **BUG-001** | High | Cohorts | `ManageCohorts::update()` overwrote `starts_on`/`ends_on` with `null` | Merges locked dates before validation. Verified in `CohortEdgeCasesTest` (10 passed). | **Fixed & Verified** |
| **BUG-002** | Medium | Cohorts | `PromoteFromWaitlist` seated learners in closed cohorts | Throws `cohorts.closed`. Verified in `CohortEdgeCasesTest`. | **Fixed & Verified** |
| **BUG-003** | Medium | Entitlements | `CourseAccessSummary` counted future grants as active | Skips `Entitlement::SCHEDULED`. Verified in `EntitlementEdgeCasesTest` (10 passed). | **Fixed & Verified** |
| **BUG-004** | Low | Curriculum authoring | `ManageAssessmentItems.php:40` dropped `"0"` title and stored whitespace | Strict trimming retaining `"0"`: `array_filter(array_map(trim, $title), fn ($v) => $v !== '')`. Verified in `CurriculumEdgeCasesTest:239` (13 passed). | **Fixed & Verified** |
| **BUG-005** | Low | Catalog categories | `SaveCategory.php:44` orphaned old disk images on replacement | Purges old file from public disk on image change or clear after commit. Verified in `CatalogEdgeCasesTest:149` (19 passed). | **Fixed & Verified** |
| **BUG-006** | Medium | Catalog pricing | `SaveCourseWizard.php:175` left stale prices on switch to free | Deletes all `CoursePrice` rows when `access_mode !== individual`. Verified in `CatalogEdgeCasesTest:301`. | **Fixed & Verified** |
| **BUG-007** | Critical | Commerce orders | `Order.php:52-55` cast `OrderStatus` enum to string throwing fatal error on `Order::updating` | Fixed in commit `4a99365` (`$raw = $order->getOriginal('status'); $original = $raw instanceof OrderStatus ? $raw : OrderStatus::tryFrom((string) $raw)`). Un-`todo`'d in `a992726` and verified on 2026-11-19 with 0 `todo` across `OrderEngineTest` (4 passed), `CouponsTest` (2 passed), `PaymobWebhookFulfillmentTest` (4 passed), `ManualPaymentProofsTest` (2 passed), and `CommerceEdgeCasesTest` (12 passed) — 24/24 passed (28/28 passed, 246 assertions including `PaymentGatewayCheckoutTest` & `DisabledSaudiMarketTest`). | **Fixed & Verified** |
| **BUG-008** | High (UX/A11y) | Learning / AI panel | `resources/views/learn/show.blade.php:126-135` omitted `<x-learning.assistant-opener />` on `embed`/YouTube lessons | Added `<x-learning.assistant-opener />` under `$canMarkComplete` fallback branch for below-`2xl` viewports. Verified via `AssistantPanelTest` and markup assertions. | **Fixed & Verified** |
| **BUG-009** | Medium (UI/Browser) | Assessment modals | `tests/Browser/AssessmentControlsBrowserTest.php:55` timed out waiting for `[role="dialog"]` | Updated selector to `.fi-modal-window:visible` conforming to Filament 3.2 DOM. Verified 32 passed (224 assertions). | **Fixed & Verified** |
| **BUG-P7-06-01** | Accessibility (Axe Critical) | AI Availability | `resources/views/filament/ai/availability.blade.php:29,53` used `aria-pressed` on `role="switch"` | Changed to `aria-checked="{{ ... ? 'true' : 'false' }}"`. Verified in `AssistantAdminBrowserTest` (16 passed, 0 axe violations). | **Fixed & Verified** |

---

## 2. Gaps & Architectural Issues Resolved & Verified

| ID | Severity | Module | Description & Fix | Verification Evidence | Status |
|---|---|---|---|---|---|
| **GAP-P6-F3** | High | Entitlements / Sequence | Required quiz with `progression = 'after_submission'` did not gate downstream lessons | Added `Quiz::hasActiveQuestions()` check with `morphWithCount` eager loading so quizzes require submission/passing while empty quiz shells don't block learners. | Verified in `AccessLayersTest` (18 passed), `ProgressEdgeCasesTest` (16 passed), `QuizRuntimeTest` (5 passed). | **Fixed & Verified** |
| **GAP-P6-F8** | Medium | Design Tokens | Raw CSS colors (`bg-white`, `border-gray-*`) in `manage-quiz.blade.php`, `completion.blade.php`, `cohorts.blade.php` | Replaced with semantic tokens (`yg-surface`, `yg-border-subtle`, `text-[var(--yg-text-muted)]`) and registered `@source '../views/filament/**/*.blade.php';` in `resources/css/filament-theme.css`. | `npm run build` passed (34/34 AA contrast pairs). | **Fixed & Verified** |
| **GAP-P2-06** | Medium | SEO / Catalog | Missing `$profiles` in `PublicCourseController.php`, improper `hreflang` on unlocalized routes, missing sitemap locale filtering | Passed `$profiles` to `CourseSeo::for()`, guarded `hreflang` to translated locales only, and added `localesWith()` in `SitemapEntries.php`. | Verified in `SeoInfrastructureTest` (9 passed). | **Fixed & Verified** |
| **GAP-P9-04** | High | Safeguarding / Notifications | Minor check failed-open when `ai.adult_age` was null; unhandled audit log failure | Updated `GuardianAccess::isMinor()` to default to 18 (fail-closed); added rollback and error check on `AuditLogger::record() === null` in `SetPhoneReminderOptIn.php`. | Verified in `PhoneChannelTest` and `NotificationRoutingTest`. | **Fixed & Verified** |
| **I01 Violation** | Critical (Arch Invariant) | Invariants / Entitlements | Direct access to `'entitlements'` table/model outside `app/Domain/Entitlements/` | Created `CapEntitlementsEndForSource`, `ScrubEntitlementReasons`, and `LearnerEntitlementExport` boundary actions inside `app/Domain/Entitlements/`. Cleaned external references in `SubjectData`, `AnonymizeStagingData`, `GrantOrderEntitlements`, and `CancelSubscription`. | `tests/Feature/Invariants/I01EntitlementOnlyViaServiceTest.php` passed (2 passed, exit 0). | **Fixed & Verified** |
| **LARASTAN-001** | Medium (Gate G2) | Static Analysis | 6 pre-existing PHPStan errors across `AskLessonAssistant`, `AssistantController`, `DuplicateCourse`, `RichContent`, `ManageCurriculum` | Fixed all type mismatches, nullsafe operators, and missing return types. Configured `parallel: maximumNumberOfProcesses: 2` in `phpstan.neon`. | `phpstan analyse` passed with 0 errors across all 953 files (`[OK] No errors`). | **Fixed & Verified** |
| **HOST-QPDF** | Environment Blocker | Host PDF Inspection | Missing Windows runtime DLLs (`0xC0000135`) in `qpdf.exe` causing 23 test failures | Installed standalone `qpdf 12.2.0` with all runtime DLLs (`qpdf30.dll`, MSVC runtimes) at `C:\Users\N\AppData\Local\YoungGo\qpdf\12.4.2\bin\qpdf.exe`. Fixed test fixtures in `ContentLibraryTest`, `AssignmentSubmissionsTest`, and `ManualPaymentProofsTest`. | All 23 tests resolved green (`PrivateFilesTest` 27 passed, `PdfIngestionTest` 3 passed, `ContentLibraryTest` 5 passed, `CurriculumPageTest` 14 passed). | **Fixed & Verified** |

## T-P8-08 subscription gaps (SubscriptionGapsTest)

| ID | Severity | Module | Location / Root Cause | Status |
|---|---|---|---|---|
| **BUG-P8-08-01** | Low (UX) | Subscriptions / learner page | `app/Domain/Subscriptions/Subscription.php` `displayState()` returns `grace` for any subscription whose latest period has ended while `status` is still `active`, so `/my/subscription` keeps the grace badge and "grace until" date after `grace_ends_at` has passed, until `subscriptions:process` (hourly) marks it expired. Access is already denied by the entitlement end dates. | **Fixed & Verified**: `displayState()` returns `expired` once the latest period's `grace_ends_at` has passed (orchestrator fix). Test `A5 once the grace days have ended ...` in `tests/Feature/Subscriptions/SubscriptionGapsTest.php` is unskipped and asserts the expired badge; the subscriptions suite passes. |

## T-P9-08 storage cleanup gaps (PrivacyStorageReconcileTest)

| ID | Severity | Module | Location / Root Cause | Status |
|---|---|---|---|---|
| **BUG-P9-08-01** | Low | Privacy / AnonymizeAccount | `app/Domain/Privacy/Actions/AnonymizeAccount.php` `__invoke()` resolves `Storage::disk(Config::string('media.disk'))` (and `Storage::disk('public')`) after the DB commit but outside the try/catch in `deleteFiles()`. A media disk name that has no configured driver throws `InvalidArgumentException` to the caller even though the account is already anonymized, so the scrub report is lost. Only a misconfigured disk triggers it, so impact is low. | **Fixed & Verified**: `deleteFiles()` now resolves the disk inside try/catch and reports it. Verified by un-skipped test `does not fail the caller when the media disk cannot be resolved after commit` in `PrivacyStorageReconcileTest.php`. |

## T-P4-MG milestone demo (P4DemoTest)

| ID | Severity | Module | Location / Root Cause | Status |
|---|---|---|---|---|
| **BUG-P4-MG-01** | High (blocks learner and parent dashboards) | Learning / shared course card | `resources/views/components/learner/course-thumb.blade.php` has an uncommitted working-tree edit (adds a multi-line `@php ... @endphp` block inside the `@else` branch) that fails to compile: `syntax error, unexpected token "endif"` (ViewException). Every page that renders `<x-learner.course-thumb>` returns 500: `GET /my` (`learner.dashboard`) and `GET /parent` (`parent.dashboard`). HEAD version of the file is not affected (it has no `@php` block). Found while running `tests/Feature/Milestone/P4DemoTest.php`. | **Open**: the step is kept, not skipped. Test steps 6 and 7 fail with 500 until the component compiles. The file is being edited by another agent in this checkout, so it was not modified here. Steps 1-5 and 8-9 pass in a run with steps 6-7 temporarily removed (52 assertions). |

## T-P3-07 course revisions at phone width (CourseRevisionBrowserTest)

| ID | Severity | Module | Location / Root Cause | Status |
|---|---|---|---|---|
| **BUG-P3-07-01** | Medium (A11y, axe `scrollable-region-focusable`, WCAG 2.1.1) | Course revisions workspace and editor | `resources/views/filament/courses/manage-revision.blade.php:22` (recent revisions table) and `resources/views/filament/courses/revision-compare.blade.php:6` (compare table) wrap wide tables in a bare `<div class="overflow-x-auto">`. At 390px the tables overflow, so the region scrolls, but it has no focusable content and no tabindex, so keyboard users cannot reach it to scroll. Reproduced by `tests/Browser/CourseRevisionBrowserTest.php` at 390x844 (AR and EN, light and dark) on the workspace and the editor slide-over. The same run at 1440x900 passes with zero axe issues. | **Fixed & Verified**: wrappers now have `tabindex="0"`, `role="region"` and an `aria-label` (coordinator fix). The 390x844 cases are un-skipped; `CourseRevisionBrowserTest` passes 24/24 (120 assertions) on a fresh run. Earlier fix note: make each scroll wrapper keyboard reachable (for example `tabindex="0"` with a `role="region"` and an accessible name), then un-skip and re-run. |

## T-P6-06 certificate PDF footer (CertificateVerificationBrowserTest, A2)

| ID | Severity | Module | Location / Root Cause | Status |
|---|---|---|---|---|
| **BUG-P6-06-A2-01** | Medium (layout, A4 landscape print) | Certificates / PDF layout | `resources/views/certificates/pdf.blade.php`: `.frame` has a fixed height (`100%` of a 210mm page) and `display: flex; flex-direction: column`, but the `.foot` row (QR 30mm, verify link, issuer line) is pushed below the frame's bottom border. The footer spills past the 2mm frame border in both AR and EN at 1123x794 CSS px (A4 landscape). The rendered sheet clips the QR bottom and the verify URL wrap against the border. Found by the new footer check in `tests/Browser/CertificateVerificationBrowserTest.php` (`keeps the certificate footer inside the frame border at A4 landscape`, AR and EN). The A2 screenshot `tests/Browser/screenshots/T-P6-06/pdf-ar-landscape.png` shows it. | **Fixed & Verified**: `.frame` in `resources/views/certificates/pdf.blade.php` now uses `padding: 8mm 16mm; line-height: 1.3; overflow: hidden`. The footer test is un-skipped and passes for ar and en (two runs; latest 12 passed). |

## T-P9-05 quiz result notices (QuizResultNoticeTest)

| ID | Severity | Module | Location / Root Cause | Status |
|---|---|---|---|---|
| **BUG-P9-05-01** | High (learner never told a hidden quiz result is ready) | Assessment / ReleaseQuizResult | `app/Domain/Assessment/Actions/ReleaseQuizResult.php` `notify()` runs `$attempt->answers()->whereNotNull('reviewed_at')->exists()`, but `quiz_answers` has no `reviewed_at` column (`database/migrations/2026_11_17_340000_create_quiz_attempts_tables.php`; essay review uses `graded_at`). The query throws, the `try` in `notify()` reports it, and neither `QuizResultNotice` nor `EssayReviewedNotice` is sent after a manual release. Affects hidden auto-graded quizzes and hidden essay quizzes. | **Fixed & Verified**: `ReleaseQuizResult::notify()` now checks `quiz_answers.graded_at` (set only by `GradeEssayAnswer`). The two release cases in `tests/Feature/Notifications/QuizResultNoticeTest.php` are un-skipped and pass. |

## T-P5-MG milestone demo (P5DemoTest)

| ID | Severity | Module | Location / Root Cause | Status |
|---|---|---|---|---|
| **BUG-P5-MG-01** | Medium (missing feature, false evidence) | Live sessions / calendar export | No `.ics` export exists in the codebase: no route, controller or action emits `BEGIN:VCALENDAR` or `text/calendar` (searched `app`, `routes`, `resources`, `tests/Feature/Live`). `docs/reviews/EVIDENCE_AUDIT_P0_P7.md` (T-P5-03 row) claims an `.ics` export is covered by `tests/Feature/Live/JoinLinkTest.php`, but that file contains no such test. The P5 demo journey therefore cannot walk an `.ics` step. | **Open**: implement a learner-facing `.ics` download for a live session (or drop the requirement from the P5 gate and correct the evidence audit row). `P5DemoTest` omits the step until a route exists. |
