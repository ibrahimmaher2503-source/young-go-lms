# Bugs Found During Test Implementation

## T-P7-06: Admin controls, readiness, usage reports, insights and resilience

### BUG-P7-06-01: Invalid ARIA Attributes on `role="switch"` in Assistant Availability View

- **Location**: `resources/views/filament/ai/availability.blade.php` (lines 29 and 53)
- **Severity**: Accessibility (Axe Critical: `aria-allowed-attr` & `aria-required-attr`)
- **Found by**: `tests/Browser/AssistantAdminBrowserTest.php` (`verifies accessibility for assistant availability page`)
- **Description**:
  The course and lesson toggle buttons use `role="switch"` combined with `aria-pressed="{{ ... ? 'true' : 'false' }}"`:
  ```html
  <button type="button" class="yg-ai-switch" role="switch" aria-pressed="{{ $course->ai_enabled ? 'true' : 'false' }}" ...>
  <button type="button" class="yg-ai-switch" role="switch" aria-pressed="{{ $row['enabled'] ? 'true' : 'false' }}" ...>
  ```
  Under W3C WAI-ARIA 1.2 specifications:
  1. The `switch` role requires the `aria-checked` attribute (`true` or `false`) to convey its binary state.
  2. `aria-pressed` is not a permitted attribute on `role="switch"` (it is reserved for toggle buttons without a role).
  Axe flags this as:
  - `[critical] Elements must only use supported ARIA attributes (aria-allowed-attr)`
  - `[critical] Required ARIA attributes must be provided (aria-required-attr): Required ARIA attribute not present: aria-checked`

- **Remediation**:
  In `resources/views/filament/ai/availability.blade.php`, change `aria-pressed` to `aria-checked`:
  ```blade
  {{-- Line 29 --}}
  <button type="button" class="yg-ai-switch" role="switch" aria-checked="{{ $course->ai_enabled ? 'true' : 'false' }}" wire:click="toggleCourse" wire:loading.attr="disabled" data-course-switch="{{ $course->ai_enabled ? 'on' : 'off' }}">

  {{-- Line 53 --}}
  <button type="button" class="yg-ai-switch" role="switch" aria-checked="{{ $row['enabled'] ? 'true' : 'false' }}" aria-label="{{ $row['title'] }}" wire:click="toggleLesson({{ $row['item_id'] }})" wire:loading.attr="disabled" data-lesson-switch="{{ $row['enabled'] ? 'on' : 'off' }}">
  ```
- **Test Status**:
  Test `tests/Browser/AssistantAdminBrowserTest.php > it('verifies accessibility for assistant availability page')` is marked `->skip('BUG: resources/views/filament/ai/availability.blade.php uses aria-pressed instead of aria-checked on role="switch", violating aria-allowed-attr and aria-required-attr')` pursuant to test task instructions.

---

## Deep test pass findings (2026-11-18)

Scope: tests only. No production file, migration, route, config or view was changed
by this pass. Every entry has a test that documents the expected behavior.

Status update: BUG-001, BUG-002 and BUG-003 were fixed in production by concurrent
work while this pass was running. Their tests now pass and the `->todo()` markers
were removed. BUG-004, BUG-005, BUG-006 and BUG-007 are still pinned by `->todo()` tests.

| ID | Severity | Module | Test | Expected | Actual | Status |
|---|---|---|---|---|---|---|
| BUG-001 | High | Cohorts | `tests/Feature/Cohorts/CohortEdgeCasesTest.php` | Editing a cohort without date fields keeps `starts_on` / `ends_on` | `ManageCohorts::update()` wrote `null` over both dates, silently losing the schedule | Fixed: `update()` now merges the locked dates before validation; test passes |
| BUG-002 | Medium | Cohorts | `tests/Feature/Cohorts/CohortEdgeCasesTest.php` | A closed cohort refuses `PromoteFromWaitlist` | Promotion succeeded; a learner was seated in a closed cohort | Fixed: `PromoteFromWaitlist` now throws `cohorts.closed`; test passes |
| BUG-003 | Medium | Entitlements / reporting | `tests/Feature/Entitlements/EntitlementEdgeCasesTest.php` | A grant that starts next week is not reported as current access | `CourseAccessSummary` counted it as `active` because it only looked at status and `ends_at` | Fixed: `CourseAccessSummary` skips `Entitlement::SCHEDULED`; test passes |
| BUG-004 | Low | Curriculum authoring | `tests/Feature/Curriculum/CurriculumEdgeCasesTest.php:239` | Assessment titles are trimmed like lesson titles and `"0"` is kept | `array_filter()` stores a whitespace-only title and drops the valid title `"0"`, unlike `CurriculumAccess::validTitle()` | **Open**, marked `->todo()` |
| BUG-005 | Low | Catalog categories / storage | `tests/Feature/Catalog/CatalogEdgeCasesTest.php:149` | Replacing a category image deletes the previous public file | `SaveCategory` stores the new path and persists it, but never deletes the old `categories/*` file; every replacement leaves an orphan | **Open**, marked `->todo()` |
| BUG-006 | Medium | Catalog pricing / commerce | `tests/Feature/Catalog/CatalogEdgeCasesTest.php:301` | Switching a course from individual paid access to free removes its persisted `CoursePrice` rows | `SaveCourseWizard::savePricing()` only touches prices while the mode is individual, so a free course keeps stale EGP/SAR rows that can reappear if it becomes paid again | **Open**, marked `->todo()` |
| BUG-007 | Critical | Commerce orders | `tests/Feature/Commerce/CommerceEdgeCasesTest.php:425` (blocked variants at lines 261, 343, 372) | Any order update (status transition, coupon apply/remove, manual approval) persists normally | `Order::booted()` casts `getOriginal('status')` to string, but Laravel 13 returns the cast `OrderStatus` enum; every `Order::updating` event throws `Object of class App\Domain\Commerce\OrderStatus could not be converted to string` at `app/Domain/Commerce/Order.php:52` | **Open**, checkout/payment blocker, marked `->todo()` |

### BUG-007 detail

Reproduction: create a pending order, then call `TransitionOrder` (or apply/remove
a coupon or approve a manual payment). The first `$order->save()` triggers
`Order::updating` and dies before any status or totals logic runs. Existing order
creation is unaffected because it uses an insert, which is why the failure was not
visible until commerce actions were exercised.

Suggested remediation: read the raw original value (`getRawOriginal('status')`) or
accept the already-cast enum before calling `tryFrom()`. No production change was
made during this test pass.

### Notes on rejected candidates

- `LearnerNotificationRouter` with a `null` email is not a bug. `CreateChildAccount`
  creates children with `email => null`, and the router correctly writes a database
  notification plus a copy to each eligible guardian. Covered as a normal path in
  `tests/Feature/Notifications/NotificationRoutingTest.php`.
- `SourceUrl::https()` drops the port when a URL is rebuilt by the iframe/external
  providers. It is documented behavior of the returned tuple (host/path/query only)
  and no current provider needs a non-standard port. Recorded as a gap, not a bug.
- `AttachLibraryItem::asResource()` accepts an unvalidated `meta['title']`, but the
  only caller (`ManageCurriculum`) never passes a title, so it is not reachable
  from the UI today. Recorded as a gap.

---

## Test Closure Pass Findings (2026-11-18)

Additional product/environment issues confirmed while completing and verifying the 31 test-prompt tasks (zero production files were modified during this pass; full details and audit breakdown are in `docs/reviews/TEST_CLOSURE_ISSUES_REPORT.md`):

| ID | Severity | Module | Location / Test | Expected | Actual | Status |
|---|---|---|---|---|---|---|
| **BUG-008** | High (UX/A11y) | Learning / AI panel opener | `resources/views/learn/show.blade.php:101-128`; `resources/views/components/learning/assistant-panel.blade.php:3` | Every eligible recorded video lesson (`lesson->type === 'video'`, including YouTube/iframe `embed` providers) renders `<x-learning.assistant-opener />` (`data-ai-open`) below `2xl` (`1536px`) so mobile (`390px`) and laptop (`1440px`) learners can open the assistant drawer | `@if` toolbar guard on line 101 only includes `video\|audio\|vimeo`; YouTube (`embed`) lessons fall into `@elseif($canMarkComplete)` without rendering `<x-learning.assistant-opener />`, leaving `<aside data-ai-panel>` present in DOM but unreachable below `1536px` | **Open** (verified via `AssistantPanelTest` / HTML output) |
| **BUG-009** | Medium (UI/Browser) | Assessment / Completion staff modals | `tests/Browser/AssessmentControlsBrowserTest.php:55`; `/instructor/courses/{id}/curriculum` & `/admin/courses/{id}/completion` | Clicking `add-assessment`, `release-quiz`, or `certificate-templates` opens the Filament modal (`[role="dialog"]`) | All 24 staff-control cases (AR/EN × light/dark × 390/1440) fail waiting for `[role="dialog"]` to become visible | **Open** (24/24 failing; timed quiz 8/8 passing) |
| **GAP-P6-F3** | High (PRD §12) | Sequence / Quiz progression | `app/Domain/Entitlements/EntitlementService.php:250-254` | A required quiz with `progression = 'after_submission'` gates subsequent curriculum items until submitted | Sequence check only gates on `progression === 'require_pass'`; learners can skip `after_submission` quizzes (`REVIEW_QUEUE_P6` F3 still open) | **Open** (confirmed by independent audit) |
| **GAP-P2-06** | Medium (SEO) | Course JSON-LD & hreflang/sitemap | `app/Http/Controllers/PublicCourseController.php:30-32`; `app/Domain/Catalog/CourseSeo.php:66`; `app/Domain/Seo/Queries/SitemapEntries.php:60-74` | `PublicCourseController` passes fetched `$profiles` to `CourseSeo::for()`; `hreflang` only lists translated locales; category/instructor sitemap entries check translated locales | `$profiles` omitted in controller call (`instructor[].url` omitted); `$urls[$locale] ??= $url` forces untranslated locale `hreflang`; sitemap skips `localesWith()` | **Open** (confirmed by independent audit) |
| **GAP-P9-04** | High (Safeguarding/Ops) | WhatsApp/SMS minor guard & audit check | `app/Domain/Notifications/Actions/SendPhoneReminder.php:49`; `SetPhoneReminderOptIn.php:33,50-52`; `app/Domain/Engagement/ReviewerName.php:25-30` | Unlinked minors/unknown ages cannot opt into or receive phone reminders under default `O-015` config (`ai.adult_age = null`), and failed audit rolls back opt-in | `ReviewerName::isChild()` returns `false` when `ai.adult_age` is `null` or birth date missing; `SetPhoneReminderOptIn` does not check `AuditLogger::record() === null` | **Open** (confirmed by independent audit) |
| **LARASTAN-001** | Medium (Gate G2) | Static analysis (6 errors in untouched `app/**`) | `AskLessonAssistant.php:371-372`, `AssistantController.php:91`, `DuplicateCourse.php:190`, `RichContent.php:141`, `ManageCurriculum.php:274` | `phpstan analyse` passes with 0 errors | Fails globally with 6 errors (`nullsafe.neverNull`, `function.impossibleType`, `missingType.iterableValue`, `property.notFound`) | **Open** |
| **HOST-QPDF** | Env Blocker (Windows) | PDF inspection binary (`qpdf.exe`) | `AppData/Local/YoungGo/qpdf/12.4.2/bin/qpdf.exe` | `qpdf.exe` exits 0 inspecting PDFs | Fails on Windows with `-1073741515` (`0xC0000135` missing runtime DLLs), causing 23 test failures (`PrivateFilesTest` 20, `PdfIngestionTest` 1, `ContentLibraryTest` 1, `CurriculumPageTest` 1) + 3 explicit host skips | **Host ENV** |


