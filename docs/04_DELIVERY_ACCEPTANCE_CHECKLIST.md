# YOUNG GO LMS --- DELIVERY, ACCEPTANCE & RELEASE CHECKLIST

**Document ID:** YG-DAC-001\
**Version:** 1.1 (v3.1 gap closure)\
**Date:** 16 November 2026\
**Purpose:** Practical checklist for milestone delivery, client
acceptance, release readiness and final handover.

Use this document with the Master PRD and Phases/Milestones Roadmap.
Mark a checkbox complete only when evidence exists. A screen or button
without a working backend outcome is not acceptance.

## 1. Project governance and source control

-   [x] PRD version and approved decision register are attached to the
    working repository.
-   [ ] DMC is read by the implementation team before coding.
-   [ ] Repository URL, default branch, feature branch convention and
    release tags are recorded.
-   [ ] CodeGraph/repository inspection completed where available.
-   [x] Development, staging and production environments are
    distinguished. See the [P0 continuity record](evidence/p0-closure/README.md#2026-11-18-clean-clone-recheck).
-   [x] No development changes are made directly on legacy production.
-   [ ] Current domain, DNS, TLS and brand assets are verified from the
    live environment.
-   [ ] Provider credentials are obtained through authorized secure
    channels.
-   [x] Unknowns and blockers are documented; no guessed infrastructure
    values.

P0 governance evidence: the PRD and decision register are in the repository.
The environment inventory and progress log record authorized public observations and unknowns.
CI run 43960234987 verifies development services. Deployment addresses, provider credentials,
owner brand confirmation, and release tag remain open.

## 2. Brand, domain and user experience

-   [ ] Existing Young Go logo is reused without redesign.
-   [ ] Existing brand colors and identity are preserved.
-   [ ] UI is modernized without changing brand positioning.
-   [ ] Owner-stated domain `youngschool.com` is verified against actual
    production DNS before cutover.
-   [ ] Existing site remains live until cutover approval.
-   [ ] Public site is responsive on desktop, tablet and mobile.
-   [ ] Student learning UI is distinct from SKIT operations UI.
-   [ ] Arabic RTL and English LTR work on all key journeys.
-   [ ] Mixed Arabic/English strings, tables, numbers, icons and
    breadcrumbs are visually reviewed.

## 3. Identity, permissions and family accounts

-   [x] Registration, login, logout, password reset and verification
    work.
-   [x] Deactivated/suspended users cannot access protected routes. Evidence: `tests/Feature/Identity/RbacCatalogTest.php` (`restricts Filament panels to active users with the matching permission`).
-   [x] Roles and granular permissions are manageable. See the [P1 evidence](evidence/p1-milestone/README.md#checklist-docs04-3-and-14a--items-a-test-proves).
-   [x] Backend policies enforce permissions on direct URL/API access. Evidence: `tests/Feature/Invariants/I02_ObjectScopeTest.php` (`enforces object ownership and relationship scopes in policies`); `tests/Feature/Identity/RbacCatalogTest.php` (`applies seeded role grants through Laravel authorization`); `tests/Feature/AI/HelpRequestQueueTest.php` (`forbids students, parents, content managers, and suspended staff from opening either panel page`).
-   [x] Instructor can access assigned courses only. Evidence: `tests/Feature/Invariants/I02_CourseScopeTest.php` (`scopes instructor listings, detail URLs and update authorization to assignments`).
-   [x] Independent instructor cannot publish without approval. Evidence: `tests/Feature/Catalog/CourseWizardReviewFlowTest.php` (`submits a complete independent course for review, locks it for the instructor and keeps publishing with the admin`).
-   [x] Finance role cannot alter curriculum/security unless explicitly
    granted.
-   [x] Parent account can link multiple verified child accounts. See the [P1 demo](evidence/p1-milestone/README.md#demo-script-executed).
-   [x] Parent cannot access unlinked children. See `ChildAccountsTest`, `GuardianLinkingTest` and `ChildRecoveryTest`.
-   [x] Each child has a separate learner profile and progress. Evidence: `tests/Feature/Learning/ParentDashboardTest.php` (`shows each linked child with their courses and progress and switches between them`).
-   [ ] Guardian consent and minor privacy policy are approved before
    minor launch. Pending: owner/legal approval (O-015); implemented behaviour: `tests/Feature/AI/DecisionsGuardianAiGateTest.php` (`refuses a minor the assistant with guardian_unverified until the guardian confirms the code`).
-   [ ] Parent visibility into AI conversations follows the approved
    policy. Pending: owner/legal approval (O-015); implemented behaviour: `tests/Feature/AI/LearnerHelpThreadTest.php` (`enforces parent context restrictions: hides question/answer and learner messages, and forbids reply`).

## 4. Public website, CMS and discovery

-   [x] Homepage content can be managed without code changes. Evidence: `tests/Feature/Pages/CmsPagesTest.php` (`reflects homepage block order and visibility without publishing hidden content`).
-   [x] Custom pages and policy pages are editable and publishable. Evidence: `tests/Feature/Pages/CmsPagesTest.php` (`publishes pages only as explicitly requested and restricts signed previews to editors`).
-   [x] Course catalog search/filter/sort/pagination work together. Evidence: `tests/Feature/Pages/PublicCatalogTest.php` (`combines catalog filters and excludes non-public courses and unpublished categories from filter options`); `tests/Feature/Pages/PublicCatalogTest.php` (`uses configured age bands and keeps search state on newest-first pagination`).
-   [ ] Categories/subcategories and age suitability are displayed
    correctly.
-   [x] Course detail page shows accurate curriculum, instructor, price
    and access model. Evidence: `tests/Feature/Pages/PublicCourseDetailTest.php` (`outputs escaped SEO meta tags, Course JSON-LD, bilingual content blocks, and correct CTA states`); `tests/Feature/Pages/PublicCourseDetailTest.php` (`shows the localized active-market price for an individually purchasable course in both access surfaces`).
-   [x] Preview lessons/resources do not expose protected content. Evidence: `tests/Feature/Pages/PublicCourseDetailTest.php` (`plays explicit preview lessons for guests and denies protected, non-lesson, draft, archived, and foreign items`).
-   [x] Blog, contact enquiries and newsletter work where included in
    the release. Evidence: `tests/Feature/Pages/BlogTest.php` (`enforces blog publication, future scheduling visibility, search/filter queries, and authorization`); `tests/Feature/Pages/ContactAndNewsletterTest.php` (`handles newsletter consent, honeypot, rate limits, identical duplicate responses, and login-free signed unsubscribe`).
-   [ ] SEO title, description, canonical and Open Graph fields are
    correct in both languages.
-   [x] Sitemap and robots rules do not expose private content. Evidence: `tests/Feature/Seo/SeoInfrastructureTest.php` (`generates sitemap.xml with live public entries only and omits drafts, private, scheduled future, and learner areas`); `tests/Feature/Seo/SeoInfrastructureTest.php` (`serves robots.txt by environment indexing flag, renders course canonical/hreflang tags, and resolves safe legacy redirects`).

## 5. Course Builder and curriculum

-   [ ] Multi-step wizard includes Basic, Information, Pricing, Media,
    SEO, Custom Fields and Finish.
-   [ ] Draft can be saved and resumed at any step.
-   [ ] Arabic/English course fields are independent from UI language.
-   [ ] Course category, subcategory, level, age suitability and content
    language are configurable.
-   [ ] Course ownership type (academy/independent) is stored and
    permission-checked.
-   [ ] Course statuses and featured flag work correctly.
-   [ ] FAQ, requirements and outcomes support multiple entries and
    translations.
-   [ ] Pricing/access mode supports free, individual purchase,
    subscription-only and both.
-   [ ] Course overview media and thumbnail preview work.
-   [ ] SEO metadata supports Arabic and English.
-   [x] Custom fields validate and render correctly. Evidence: `tests/Feature/Catalog/CourseWizardCustomFieldsTest.php` (`enforces text length, number range, select options, https urls, date format and toggles on the wizard values`); `tests/Feature/Catalog/CourseWizardCustomFieldsTest.php` (`renders only public custom fields with a value on the public course page, escaped and per locale`).
-   [ ] Sections can be created, renamed, reordered, archived and
    previewed.
-   [x] Lessons can be added, edited, reordered and archived. Evidence: `tests/Feature/Curriculum/CurriculumManagerTest.php` (`adds sections and lessons at the end, always as drafts, with no sort column on lessons`); `tests/Feature/Curriculum/CurriculumManagerTest.php` (`persists a reorder and the manager order equals the student order for published items`); `tests/Feature/Curriculum/CurriculumManagerTest.php` (`validates titles, types and durations`); `tests/Feature/Curriculum/CurriculumManagerTest.php` (`hides archived lessons and sections from students but keeps the records`).
-   [ ] Lesson types cover approved media/file/text/embed sources.
-   [x] Media source validation and private access are enforced. Evidence: `tests/Feature/Media/MediaProvidersTest.php` (`rejects bad sources for each provider`); `tests/Feature/Media/PrivateFilesTest.php` (`gives out a signed link only to people who may open the file, and fails closed for learners`).
-   [x] Each recorded lesson supports a dedicated curriculum PDF. Evidence: `tests/Feature/Media/PrivateFilesTest.php` (`stores a valid pdf as an inactive version 1 under a random private name`).
-   [x] PDF download permission is enforced. Evidence: `tests/Feature/Media/PrivateFilesTest.php` (`gives out a signed link only to people who may open the file, and fails closed for learners`); `tests/Feature/Media/PrivateFilesTest.php` (`refuses a forwarded link: another account cannot use it and an expired link is dead`).
-   [ ] Course list counters, filters, actions and CSV export work.
-   [x] Course access summary and manual grants are visible to
    authorized staff. Evidence: `tests/Feature/Entitlements/EnrollmentAndGrantsTest.php` (`shows grants to finance and admin only and summarises access by source`).

## 6. Student player and learning progress

-   [ ] Student sees only entitled courses.
-   [ ] Curriculum is grouped and ordered correctly.
-   [ ] Video/player loads for each supported source type.
-   [x] Previous/next and continue/resume work. Evidence: `tests/Feature/Learning/CoursePlayerTest.php` (`computes previous and next around the current item skipping locked ones`); `tests/Feature/Learning/ProgressTest.php` (`resumes at the last position and continues at the first unfinished lesson`).
-   [ ] Last position/progress is persisted where supported.
-   [x] Lesson completion is server-authoritative. Evidence: `tests/Feature/Learning/ProgressTest.php` (`does not let a client complete a video by sending the end position or by marking it done`).
-   [x] Course progress percentage is consistent with configured rules. Evidence: `tests/Feature/Learning/ProgressTest.php` (`keeps the course percentage right when lessons are added, archived or optional`); `tests/Feature/Learning/ProgressEdgeCasesTest.php` (`uses the configured completion percentage and speed`).
-   [x] PDF and resources are downloadable by entitled learners. Evidence: `tests/Feature/Media/PrivateFilesTest.php` (`opens protected files to entitled learners through the entitlement checker`).
-   [x] Preview content is limited to explicitly public items. Evidence: `tests/Feature/Learning/CoursePlayerTest.php` (`opens only the free preview lesson to a learner who is not enrolled`).
-   [x] Locked/expired lessons display a clear reason. Evidence: `tests/Feature/Learning/CoursePlayerTest.php` (`shows why a dated lesson is locked to an entitled learner and lists the reason in the curriculum`).
-   [x] Student cannot access another student's progress or submissions. Evidence: `tests/Feature/Learning/LearnerDashboardTest.php` (`shows the learner only their own courses, progress and continue point`); `tests/Feature/Learning/ProgressEdgeCasesTest.php` (`finds only this learner and course in the completion lookup`).

## 7. Live and hybrid learning

-   [x] Instructor/admin can create a live session with course,
    instructor, date/time and timezone. Evidence: `tests/Feature/Live/LiveSessionsTest.php` (`schedules, reschedules, opens and cancels from the curriculum manager`); `tests/Feature/Live/LiveSessionsTest.php` (`stores UTC and shows the learner their own timezone`).
-   [x] Manual Zoom/Meet/BigBlueButton/custom links can be stored where
    configured. Evidence: `tests/Feature/Live/LiveSessionsTest.php` (`keeps the meeting link encrypted and out of serialized data`); `tests/Feature/Live/LiveSessionsTest.php` (`rejects bad times, links, providers and foreign cohorts or instructors`).
-   [x] Join links are hidden from non-enrolled learners. Evidence: `tests/Feature/Live/JoinLinkTest.php` (`never puts the meeting link in the page, before or inside the window`); `tests/Feature/Live/JoinLinkTest.php` (`refuses learners without access, with a 404 that hides the session`).
-   [x] Join links are shown only in the allowed time window. Evidence: `tests/Feature/Live/JoinLinkTest.php` (`hands the link out only inside the join window`).
-   [ ] Reschedule/cancel status changes are audited and notified.
-   [ ] Reminders are deduplicated.
-   [x] Attendance can be marked and corrected by authorized staff. Evidence: `tests/Feature/Live/AttendanceTest.php` (`marks an audience learner once, manually and with an audit entry`); `tests/Feature/Live/AttendanceTest.php` (`corrects a mark with the earlier value in the audit trail`).
-   [x] Replay can be attached as a recorded item. Evidence: `tests/Feature/Live/LiveReplayTest.php` (`creates the replay as a bilingual draft video lesson in the same section and links it`).
-   [x] AI assistant is absent/disabled during live sessions. Evidence: `tests/Feature/Live/JoinLinkTest.php` (`keeps the assistant off live items`); `tests/Feature/Live/LiveSessionsTest.php` (`marks live items as not eligible for the assistant`).

## 8. Quizzes, assignments and certificates

-   [ ] Quiz title/instructions/section/duration/total marks/pass
    score/attempt limit save correctly.
-   [x] Single-choice, multiple-choice and true/false questions work. Evidence: `tests/Feature/Assessment/QuizRuntimeTest.php` (`grades every question type accurately, handles blank vs written essays, and enforces result visibility`).
-   [x] Short-answer/subjective modes follow configured grading rules. Evidence: `tests/Feature/Assessment/QuizRuntimeTest.php` (`grades every question type accurately, handles blank vs written essays, and enforces result visibility`).
-   [x] Next-lesson behavior after submission vs pass requirement works. Evidence: `tests/Feature/Assessment/QuizRuntimeTest.php` (`controls item completion and sequential gating for after_submission vs require_pass quizzes`).
-   [x] Server enforces timer, attempts and score calculation. Evidence: `tests/Feature/Assessment/QuizRuntimeTest.php` (`enforces timer grace window, GET expired non-mutation, late payload rejection, and ExpireQuizAttempts command`); `tests/Feature/Assessment/QuizRuntimeTest.php` (`enforces start, resume, access, draft, zero-question, and max-attempt guards on runtime routes`).
-   [x] Correct answers are not exposed before permitted release. Evidence: `tests/Feature/Assessment/QuizRuntimeTest.php` (`freezes attempt snapshot and never leaks answer keys or explanations during open attempt`).
-   [x] Student can submit text and permitted files for assignments. Evidence: `tests/Feature/Assignments/AssignmentSubmissionsTest.php` (`supports learner drafts, HTTP file uploads, spoofed extension rejection, late rules, and immutable revisions`).
-   [ ] Upload MIME/type/size checks work.
-   [x] Instructor can review, comment, grade, accept, reject or request
    revision. Evidence: `tests/Feature/Assignments/ReviewGradingQueueTest.php` (`validates review decisions, applies late penalties, avoids duplicate notices, and rolls back on audit failure`).
-   [x] Student receives feedback notification. Evidence: `tests/Feature/Learning/AssignmentCompletionTest.php` (`records repeated grades but only notifies for changed review decisions`).
-   [x] Completion rules include configured lesson/quiz/assignment/live
    requirements. Evidence: `tests/Feature/Learning/CompletionEngineTest.php` (`measures lessons, quizzes, accepted assignments, and live attendance criteria accurately`).
-   [ ] Certificate is issued only when eligible.
-   [ ] Certificate number/token is unique.
-   [x] Public verification page exposes only approved safe fields. Evidence: `tests/Feature/Learning/CertificateVerifyAndPdfTest.php` (`serves public verify endpoint safely across valid, revoked, unknown, privacy, headers, and throttle`).
-   [x] Revocation/void state is visible and audited. Evidence: `tests/Feature/Learning/CertificateVerifyAndPdfTest.php` (`issues once per completion, supports staff revoke and reissue with audit, and locks unauthorized actors`).

## 9. AI lesson assistant and help requests

-   [x] Assistant is available only on eligible recorded lessons. Evidence: `tests/Feature/AI/AssistantEligibilityTest.php` (`is unavailable with the right reason when one condition fails`).
-   [x] Assistant is not available during live sessions. Evidence: `tests/Feature/AI/AssistantEligibilityTest.php` (`is unavailable with the right reason when one condition fails`).
-   [x] Current lesson has an approved curriculum PDF before AI
    activation. Evidence: `tests/Feature/AI/AssistantEligibilityTest.php` (`is unavailable with the right reason when one condition fails`).
-   [x] AI retrieval is restricted to the current lesson PDF only. Evidence: `tests/Feature/AI/AssistantEnginePipelineTest.php` (`uses only selected approved excerpts in a chunked prompt`); `tests/Feature/AI/AssistantEnginePipelineTest.php` (`discards provider replies if the approved document was retired during the request`); `tests/Feature/AI/PdfIngestionTest.php` (`makes the approved edited text the only active source and swaps versions atomically`).
-   [ ] Student entitlement is rechecked on every AI request.
-   [x] AI answers are short, simple and age-appropriate. Evidence: `tests/Feature/AI/AssistantCoreGuardTest.php` (`enforces OutputGuard length trimming, retry caps, leakage rules, and thread/simpler turn limits`); `tests/Feature/AgeBandResolverTest.php` (`seeds editable catalog defaults and resolves each wording tier from the stored bands`).
-   [ ] AI does not deliver a full lesson or replace the instructor.
-   [x] If source is insufficient, AI says so and offers human help. Evidence: `tests/Feature/AI/AssistantEnginePipelineTest.php` (`ends as not_in_source when quote fails to match cited page after retry`); `tests/Browser/AssistantPanelBrowserTest.php` (`captures every assistant panel state across AR and EN on mobile and desktop`, not-in-source case asserts the human-help action).
-   [x] "I understood" feedback is recorded. Evidence: `tests/Feature/AI/AssistantFeedbackTest.php` (`records understood feedback without creating a help request, idempotently, and logs audit`).
-   [x] "I still don't understand" creates one linked help request. Evidence: `tests/Feature/AI/AssistantFeedbackTest.php` (`creates exactly one help request on not_understood feedback and returns 201 then 200 on repeated taps`).
-   [x] Help request contains student, course, lesson, question, AI
    response and video timestamp when available. Evidence: `tests/Feature/AI/AssistantFeedbackTest.php` (`creates exactly one help request on not_understood feedback and returns 201 then 200 on repeated taps`).
-   [ ] Responsible instructor receives notification.
-   [x] Admin sees the same request. Evidence: `tests/Feature/AI/HelpRequestQueueTest.php` (`allows admin and support users to see all requests across all courses`).
-   [x] Instructor can reply inside the platform request thread. Evidence: `tests/Feature/AI/HelpRequestLifecycleTest.php` (`handles staff reply: transitions state, stops SLA, sets first_replied_at once, and notifies learner with safe text`).
-   [x] Student receives notification and can reply. Evidence: `tests/Feature/AI/HelpRequestLifecycleTest.php` (`handles staff reply: transitions state, stops SLA, sets first_replied_at once, and notifies learner with safe text`). Evidence: `tests/Feature/AI/HelpRequestLifecycleTest.php` (`allows learner reply only when status is instructor_replied or waiting_for_student`).
-   [x] Request status and audit trail are available. Evidence: `tests/Feature/AI/HelpRequestLifecycleTest.php` (`enforces valid staff status transitions and stops SLA when resolving or closing`). Evidence: `tests/Feature/AI/AssistantFeedbackTest.php` (`creates exactly one help request on not_understood feedback and returns 201 then 200 on repeated taps`).
-   [ ] AI provider outage does not block video, PDF or learning.
-   [ ] Provider data policy, cost controls, retention and minor-consent
    requirements are approved before activation. Pending: T-P7-07 (real provider, privacy gate) — accepted as blocked for T-P7-MG; fake-provider evidence: `tests/Feature/AI/AssistantEligibilityTest.php` (`uses the deterministic fake provider and never touches the network`); `tests/Feature/AI/AssistantEligibilityTest.php` (`refuses loudly and reports down when no provider is configured`).

## 10. Commerce, subscriptions and instructor finance

-   [x] Individual course purchase is supported. Evidence: `tests/Feature/Commerce/OrderEngineTest.php` (`creates server-priced EGP orders idempotently, hides request_hash, and rolls back on audit failure`).
-   [x] Bundles grant all included eligible courses once. Evidence: `tests/Feature/Commerce/BundleAllocationTest.php` (`grants a bundle once: a second grant adds no row, no allocation change and no second audit`).
-   [x] Monthly subscription grants only plan-included courses. Evidence: `tests/Feature/Subscriptions/SubscriptionPlansTest.php` (`grants a paid plan only the included courses, with subscription source, the period ref and grace end`).
-   [ ] Annual subscription grants only plan-included courses.
-   [ ] Course access mode controls one-time/subscription eligibility.
-   [ ] Unified entitlement engine is used by player, files, live links
    and AI.
-   [x] Egypt is the only active checkout market at launch. Evidence: `tests/Feature/Commerce/DisabledSaudiMarketTest.php` (`keeps Saudi order and payment flows disabled even when market configuration is switched`). Evidence: `tests/Feature/Commerce/OrderEngineTest.php` (`creates server-priced EGP orders idempotently, hides request_hash, and rolls back on audit failure`).
-   [x] Saudi checkout is disabled. Evidence: `tests/Feature/Commerce/DisabledSaudiMarketTest.php` (`keeps Saudi order and payment flows disabled even when market configuration is switched`).
-   [ ] Legal seller/invoice settings are approved for Egypt.
-   [ ] Payment provider credentials and callback security are
    configured.
-   [x] Browser redirect alone does not mark payment as paid. Evidence: `tests/Feature/Commerce/PaymentGatewayCheckoutTest.php` (`keeps return status read-only and never marks an order paid from checkout return or session start`).
-   [x] Duplicate webhook cannot duplicate payment fulfillment or
    access. Evidence: `tests/Feature/Commerce/PaymobWebhookVerificationAcceptanceTest.php` (`fulfils once when the same signed success arrives three times`); `tests/Feature/Commerce/PaymobWebhookFulfillmentTest.php` (`fulfills paid orders once with idempotent webhook replay and single entitlement/ledger source`).
-   [x] Instapay/manual payment evidence is reviewed by authorized
    finance staff. Evidence: `tests/Feature/Commerce/CommerceEdgeCasesTest.php` (`requires a note when the transferred amount does not match and rejects the wrong actor`).
-   [x] Coupons respect date, usage and product restrictions. Evidence: `tests/Feature/Commerce/CouponAcceptanceTest.php` (`refuses a coupon whose start is in the future`); `tests/Feature/Commerce/CouponAcceptanceTest.php` (`refuses a payer who has used the coupon up to their own cap`); `tests/Feature/Commerce/CouponAcceptanceTest.php` (`refuses a course-restricted coupon on an order with no eligible course`).
-   [x] Refunds preserve original transaction history and apply approved
    access rules. Evidence: `tests/Feature/Commerce/RefundsReceiptsAcceptanceTest.php` (`partial then full refund leaves the original money rows byte-identical and walks the order to refunded`); `tests/Feature/Commerce/RefundAccessRevocationTest.php` (`A1 a full refund of a paid plan order revokes its subscription entitlements, closes the period and expires the subscription`).
-   [ ] All student money is collected centrally by Young Go.
-   [ ] No direct split payment to instructors is enabled.
-   [x] Individual course commission percentage is configurable. Evidence: `tests/Feature/InstructorFinance/DecisionsCommissionDefaultsTest.php` (`still lets a course rule override the 70 percent default`); `tests/Feature/InstructorFinance/CommissionLedgerTest.php` (`picks course plus instructor over course over instructor over global over the config default`).
-   [ ] Bundle value allocation is traceable and
    owner/accountant-approved.
-   [x] Subscription instructor earning is a fixed amount per
    student/course/period. Evidence: `tests/Feature/InstructorFinance/CommissionLedgerTest.php` (`gives one fixed entry per plan course per period and none on a replay, with the matching fixed rule`); `tests/Feature/InstructorFinance/DecisionsCommissionDefaultsTest.php` (`replaces the percentage with a fixed subscription rule and with the config fixed amount`).
-   [x] Subscription earnings are not based on watch time or engagement. Evidence: `tests/Feature/InstructorFinance/CommissionLedgerTest.php` (`keeps App\Domain\InstructorFinance free of progress, engagement, completion and AI imports`).
-   [x] Duplicate student-course-period ledger entries are prevented. Evidence: `tests/Feature/InstructorFinance/CommissionLedgerTest.php` (`writes the same earnings rows when RecordOrderEarnings runs twice`).
-   [x] Instructor statement reconciles to orders/subscriptions. Evidence: `tests/Feature/InstructorFinance/StatementsAndSettlementsTest.php` (`A1 freezes the ledger sums of a closed month into a statement equal to LedgerTotals`).
-   [x] Settlement approval and paid reference are audited. Evidence: `tests/Feature/InstructorFinance/DecisionsSettlementRulesTest.php` (`returns the already approved batch on a second approval without a second audit row`); `tests/Feature/InstructorFinance/StatementsAndSettlementsTest.php` (`A4 payout validates the reference, the date and the settlement state with lang keys`).
-   [ ] Exact commission rates, accounting base and settlement schedule
    are approved before live payouts.

## 11. Administration, reports and notifications

-   [x] SKIT shell is consistent across admin/instructor/finance/content
    panels. Evidence: owner acceptance D-032 in `docs/07_DECISION_REGISTER.md`; `tests/Browser/StaffPanelsTest.php` (`opens the active staff sidebar group after a direct visit`).
-   [x] Role-aware sidebar includes all approved modules. Evidence: `tests/Feature/Settings/SettingsAndSidebarParityTest.php` (`lists every registered class of each staff panel in exactly one catalog group, so More is empty`).
-   [ ] Course/user/payment/enrollment/help-request/finance lists
    support search/filter/sort/pagination.
-   [x] CSV exports honor filters and permissions. Evidence: `tests/Feature/Reports/ReportCoverageTest.php` (`refuses a report the user cannot read, through the export action`); `tests/Feature/Reports/ReportCoverageTest.php` (`lets the admin page run an export for each report the user can read and keeps the filter scope`).
-   [x] Dashboard KPIs are backed by actual records. Evidence: `tests/Feature/Reports/RoleDashboardKpisTest.php` (`equals the admin KPI tiles to the sales, refunds, enrollments, completions and AI report totals of the same window`); `tests/Feature/Reports/RoleDashboardKpisTest.php` (`reads each queue live and equal to its queue query`).
-   [x] Reports reconcile to transaction and ledger records. Evidence: `tests/Feature/Reports/ReportAcceptanceTest.php` (`reconciles finance report rows and totals per currency to paid orders and executed refunds`); `tests/Feature/Reports/RoleDashboardKpisTest.php` (`keeps finance KPIs equal to sales net and the ledger balances, with held money separate`).
-   [x] In-app/email notification templates are localized. Evidence: `tests/Feature/Notifications/NotificationTemplatesPreferencesTest.php` (`gives every matrix event with wording AR and EN text whose tokens are all allowed placeholders`).
-   [x] Notifications are deduplicated across retries. Evidence: `tests/Feature/Notifications/NotificationRoutingTest.php` (`returns false without notifying when the dispatch key already exists`).
-   [x] Audit log captures sensitive actions. Evidence: `tests/Feature/Audit/AuditLoggerTest.php` (`T-P1-04 A2 records the actor and acting_for context`); `tests/Feature/Reports/ReportCoverageTest.php` (`writes one report.exported audit row with the user as causer and subject and the filters and row count`).
-   [x] Secrets and sensitive learner data are excluded from routine
    logs. Evidence: `tests/Feature/Commerce/PaymentGatewayContractAcceptanceTest.php` (`keeps Paymob secrets out of logs, exceptions, attempt rows and audit rows on a 500`); `tests/Feature/Notifications/PhoneChannelAcceptanceTest.php` (`logs skips without any phone number or message body`).

## 12. Legacy migration and data reconciliation

-   [ ] Legacy source system and database are inventoried.
-   [ ] All existing courses are listed in a migration manifest.
-   [ ] Each course has a target record or documented exception.
-   [ ] Categories, levels, translations, prices and status reconcile.
-   [ ] Sections and lessons reconcile in order.
-   [ ] Media references and uploaded files are verified.
-   [ ] Curriculum PDFs/resources are migrated and downloadable.
-   [ ] Quizzes/questions/options and relevant history are mapped.
-   [ ] Assignments/submissions/feedback are mapped where in approved
    scope.
-   [ ] Live sessions/replays/certificates are mapped where in approved
    scope.
-   [ ] Student accounts/enrollments/progress/payment history scope is
    signed off.
-   [ ] No active legacy student is silently deprived of access.
-   [ ] Broken source links are listed and resolved or explicitly
    accepted.
-   [ ] Import is repeatable/idempotent and produces a reconciliation
    report.
-   [ ] Final delta import is completed at the agreed cutover window.
-   [ ] Legacy system is retained read-only/available for the agreed
    rollback period.

## 13. Security, performance and operations

-   [ ] Authorization checks cover pages, APIs, files, video, live
    links, AI and exports.
-   [ ] Authentication, password reset, rate limiting and session
    controls are active.
-   [ ] File upload validation and private storage are active.
-   [ ] Payment callback signature/amount/idempotency checks pass.
-   [ ] AI retrieval authorization and prompt-injection boundaries pass.
-   [ ] No critical/high security issue remains open.
-   [ ] Queue workers and scheduler are healthy.
-   [ ] Email delivery and retry behavior are verified.
-   [ ] Storage capacity and video delivery are appropriate for expected
    traffic.
-   [ ] Uptime/error/disk/queue/backup monitoring is configured.
-   [ ] Database and private media backup jobs are configured.
-   [ ] Restore test is completed and evidence recorded.
-   [ ] Rollback target and procedure are documented.
-   [ ] Production secrets are not committed to source control.

## 14. Training, cutover and handover

-   [ ] Admin guide delivered.
-   [ ] Instructor/course-authoring guide delivered.
-   [ ] Finance/manual payment/instructor settlement guide delivered.
-   [ ] Support/help-request guide delivered.
-   [ ] Known issues and deferred scope documented.
-   [ ] Repository URL, branch, release tag and commit recorded.
-   [ ] Production host/domain and deployment evidence recorded.
-   [ ] Provider configuration/credential ownership documented securely.
-   [ ] Final database/media backup verified.
-   [ ] Exact domain/TLS/DNS cutover approved.
-   [ ] Post-cutover smoke checks pass.
-   [ ] Rollback route remains available for agreed stabilization
    period.
-   [ ] Owner acceptance and operations handoff recorded.

## 14A. v3.1 gap-closure checks

### Family, identity, cohorts
-   [x] Parent creates a child account; child logs in with username/password and no email. Evidence: `tests/Feature/Identity/ChildAccountsTest.php` (`creates an email-less child that logs in with username and password`).
-   [x] Link code links an existing learner only after confirmation; code is single-use and expires. Evidence: `tests/Feature/Identity/GuardianLinkingTest.php` (`makes link codes single-use`); `tests/Feature/Identity/GuardianLinkingTest.php` (`expires link codes after 48 hours`); `tests/Feature/Identity/GuardianLinkingTest.php` (`keeps the link inactive until the learner confirms it`).
-   [ ] Only birth year/month stored; age band computed server-side.
-   [x] Parent acting in child context is recorded as actor in audit. Evidence: `tests/Feature/Identity/GuardianLinkingTest.php` (`audits actions in child context with the parent as actor`).
-   [ ] Parent pays; entitlement belongs to child.
-   [ ] Certificate uses certificate name in the template language; name locks after first certificate.
-   [ ] Cohort capacity is never exceeded under concurrent checkout; waitlist promotion works.
-   [x] Cohort live sessions are visible only to cohort members. Evidence: `tests/Feature/Live/JoinLinkTest.php` (`expects a cohort seat for a cohort session and ignores waitlist places and removed members`).

### Content protection and publishing
-   [x] Every media asset has a protection tier; paid lesson with T3 source cannot publish without exception reason. Evidence: `tests/Feature/Media/PaidT3PublishTest.php` (`blocks publishing a paid lesson that uses an open source`); `tests/Feature/Media/PaidT3PublishTest.php` (`lets an admin record an exception with a reason, audited, and then publishing works`).
-   [ ] Watermark visible on T1/T2 playback including fullscreen and mobile.
-   [x] Session limit and device limit enforced; admin device reset works. Evidence: `tests/Feature/Identity/SessionLimitsTest.php` (`signs the oldest session out when a third one starts`); `tests/Feature/Identity/SessionLimitsTest.php` (`refuses a fourth device at the door with a clear message`); `tests/Feature/Identity/SessionLimitsTest.php` (`lets an admin reset devices, signs the learner out everywhere and audits it`).
-   [x] Independent instructor edit to a published course creates a pending revision; live version unchanged until approval. Evidence: `tests/Feature/Catalog/CourseRevisionsTest.php` (`keeps the old title and curriculum on the public page until an admin approves the revision`).

### Paymob, manual payments, subscriptions
-   [x] Paymob callback signature verified; amount/currency/order reference matched. Evidence: `tests/Feature/Commerce/PaymobWebhookFulfillmentTest.php` (`rejects oversized, malformed, and invalid-signature webhooks without writing events and verifies Paymob SHA-512 HMAC`); `tests/Feature/Commerce/PaymobWebhookVerificationAcceptanceTest.php` (`flags a signed success in another currency as amount_mismatch`).
-   [x] Replayed/duplicate webhook does not duplicate entitlement or ledger. Evidence: `tests/Feature/Commerce/PaymobWebhookVerificationAcceptanceTest.php` (`fulfils once when the same signed success arrives three times`); `tests/Feature/Commerce/PaymobWebhookFulfillmentTest.php` (`fulfills paid orders once with idempotent webhook replay and single entitlement/ledger source`).
-   [x] Browser return page never marks an order paid. Evidence: `tests/Feature/Commerce/PaymentGatewayCheckoutTest.php` (`keeps return status read-only and never marks an order paid from checkout return or session start`).
-   [x] Daily reconciliation report lists mismatches. Evidence: `tests/Feature/Commerce/PaymentReconciliationTest.php` (`A1 a different amount or currency is an amount_mismatch`); `tests/Feature/Commerce/PaymentReconciliationTest.php` (`A1 the schedule lists payments:reconcile daily at 04:30 without overlap`).
-   [x] Instapay proof with a duplicate transfer reference cannot be approved. Evidence: `tests/Feature/Commerce/CommerceEdgeCasesTest.php` (`refuses a proof with bad values, one pending proof per order and a reused reference`).
-   [x] E-receipt adapter state matches O-023 decision (disabled until decided). Evidence: `tests/Feature/Commerce/RefundsReceiptsAcceptanceTest.php` (`the e-receipt adapter is the disabled null provider by default`).
-   [ ] Renewal reminders 7/3/1 days sent once each; grace period then plan-only access revoked; progress retained.

### Instructors, engagement, search, privacy
-   [x] Instructor application → review → agreement acceptance → payout profile flow works; payout data encrypted and Finance-only. Evidence: `tests/Feature/Instructors/T_P9_01_OnboardingAcceptanceTest.php` (`approves an application through the action: role kept, draft profile hidden, audits and event once`); `tests/Feature/Instructors/T_P9_01_OnboardingAcceptanceTest.php` (`encrypts payout details at rest, hides them from serialization and validates each method`); `tests/Feature/Instructors/T_P9_01_OnboardingAcceptanceTest.php` (`reveals details only to finance with reveal permission and audits the reveal before returning`).
-   [x] Wishlist works; reviews require entitlement and moderation. Evidence: `tests/Feature/Engagement/WishlistAndReviewsTest.php` (`restricts course reviews to entitled learners, enforces one row per pair, and anonymizes child/adult identities`); `tests/Feature/Engagement/T_P9_02_WishlistReviewsAcceptanceTest.php` (`refuses a non-entitled, expired or wishlist-only learner from reviewing and creates no row`).
-   [ ] Arabic search normalization verified (hamza/ta marbuta/alef maqsura/diacritics).
-   [ ] Marketing tags load only after consent and never on learning/child/AI screens.
-   [x] Data export and deletion requests complete; financial records retained anonymized. Evidence: `tests/Feature/Privacy/PrivacyRightsWorkflowTest.php` (`builds an export zip on the private disk with manifest, README and the subject topics only`); `tests/Feature/Privacy/PrivacyRightsWorkflowTest.php` (`anonymizes a deletion: the account is scrubbed, orders stay, and a second run writes no second audit`).
-   [x] Retention pruning job runs with configured values. Evidence: `tests/Feature/Privacy/PrivacyRightsWorkflowTest.php` (`prunes learning records only two years after recorded account closure`).
-   [x] Staging refresh uses anonymization command. Evidence: `tests/Feature/Privacy/PrivacyAnonymizerCommandTest.php` (`leaves no real email or phone in any text column after the staging run`).

### Quality gates
-   [ ] CI green: Pint, Larastan, Pest (unit/feature/arch), invariant suite.
-   [ ] Visual screenshots reviewed for key pages: AR/EN × mobile/desktop (× light/dark for SKIT).
-   [ ] No serious/critical axe violations on key pages.
-   [ ] AI eval thresholds (`06_AI...` §13.3) met on pilot lessons.
-   [ ] SPF/DKIM/DMARC verified for mail domain.
-   [ ] Restore drill meets RPO ≤ 24 h / RTO ≤ 4 h (or owner SLA).

## 15. Formal acceptance record

  Field                        Value
  ---------------------------- ------------------------------------------------
  Release / Phase              
  Build version / commit       
  Staging URL                  
  Production URL               
  Migration manifest/version   
  Critical issues open         
  Backup/restore evidence      
  Owner decision               Accepted / Accepted with exceptions / Rejected
  Exceptions and owner         
  Accepted by                  
  Date                         
