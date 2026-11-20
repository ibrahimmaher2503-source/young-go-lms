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
