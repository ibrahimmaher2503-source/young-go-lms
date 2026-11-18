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
-   [ ] Deactivated/suspended users cannot access protected routes.
-   [x] Roles and granular permissions are manageable. See the [P1 evidence](evidence/p1-milestone/README.md#checklist-docs04-3-and-14a--items-a-test-proves).
-   [ ] Backend policies enforce permissions on direct URL/API access.
-   [ ] Instructor can access assigned courses only.
-   [ ] Independent instructor cannot publish without approval.
-   [x] Finance role cannot alter curriculum/security unless explicitly
    granted.
-   [x] Parent account can link multiple verified child accounts. See the [P1 demo](evidence/p1-milestone/README.md#demo-script-executed).
-   [x] Parent cannot access unlinked children. See `ChildAccountsTest`, `GuardianLinkingTest` and `ChildRecoveryTest`.
-   [ ] Each child has a separate learner profile and progress.
-   [ ] Guardian consent and minor privacy policy are approved before
    minor launch.
-   [ ] Parent visibility into AI conversations follows the approved
    policy.

## 4. Public website, CMS and discovery

-   [ ] Homepage content can be managed without code changes.
-   [ ] Custom pages and policy pages are editable and publishable.
-   [ ] Course catalog search/filter/sort/pagination work together.
-   [ ] Categories/subcategories and age suitability are displayed
    correctly.
-   [ ] Course detail page shows accurate curriculum, instructor, price
    and access model.
-   [ ] Preview lessons/resources do not expose protected content.
-   [ ] Blog, contact enquiries and newsletter work where included in
    the release.
-   [ ] SEO title, description, canonical and Open Graph fields are
    correct in both languages.
-   [ ] Sitemap and robots rules do not expose private content.

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
-   [ ] Custom fields validate and render correctly.
-   [ ] Sections can be created, renamed, reordered, archived and
    previewed.
-   [ ] Lessons can be added, edited, reordered and archived.
-   [ ] Lesson types cover approved media/file/text/embed sources.
-   [ ] Media source validation and private access are enforced.
-   [ ] Each recorded lesson supports a dedicated curriculum PDF.
-   [ ] PDF download permission is enforced.
-   [ ] Course list counters, filters, actions and CSV export work.
-   [ ] Course access summary and manual grants are visible to
    authorized staff.

## 6. Student player and learning progress

-   [ ] Student sees only entitled courses.
-   [ ] Curriculum is grouped and ordered correctly.
-   [ ] Video/player loads for each supported source type.
-   [ ] Previous/next and continue/resume work.
-   [ ] Last position/progress is persisted where supported.
-   [ ] Lesson completion is server-authoritative.
-   [ ] Course progress percentage is consistent with configured rules.
-   [ ] PDF and resources are downloadable by entitled learners.
-   [ ] Preview content is limited to explicitly public items.
-   [ ] Locked/expired lessons display a clear reason.
-   [ ] Student cannot access another student's progress or submissions.

## 7. Live and hybrid learning

-   [ ] Instructor/admin can create a live session with course,
    instructor, date/time and timezone.
-   [ ] Manual Zoom/Meet/BigBlueButton/custom links can be stored where
    configured.
-   [ ] Join links are hidden from non-enrolled learners.
-   [ ] Join links are shown only in the allowed time window.
-   [ ] Reschedule/cancel status changes are audited and notified.
-   [ ] Reminders are deduplicated.
-   [ ] Attendance can be marked and corrected by authorized staff.
-   [ ] Replay can be attached as a recorded item.
-   [ ] AI assistant is absent/disabled during live sessions.

## 8. Quizzes, assignments and certificates

-   [ ] Quiz title/instructions/section/duration/total marks/pass
    score/attempt limit save correctly.
-   [ ] Single-choice, multiple-choice and true/false questions work.
-   [ ] Short-answer/subjective modes follow configured grading rules.
-   [ ] Next-lesson behavior after submission vs pass requirement works.
-   [ ] Server enforces timer, attempts and score calculation.
-   [ ] Correct answers are not exposed before permitted release.
-   [ ] Student can submit text and permitted files for assignments.
-   [ ] Upload MIME/type/size checks work.
-   [ ] Instructor can review, comment, grade, accept, reject or request
    revision.
-   [ ] Student receives feedback notification.
-   [ ] Completion rules include configured lesson/quiz/assignment/live
    requirements.
-   [ ] Certificate is issued only when eligible.
-   [ ] Certificate number/token is unique.
-   [ ] Public verification page exposes only approved safe fields.
-   [ ] Revocation/void state is visible and audited.

## 9. AI lesson assistant and help requests

-   [ ] Assistant is available only on eligible recorded lessons.
-   [ ] Assistant is not available during live sessions.
-   [ ] Current lesson has an approved curriculum PDF before AI
    activation.
-   [ ] AI retrieval is restricted to the current lesson PDF only.
-   [ ] Student entitlement is rechecked on every AI request.
-   [ ] AI answers are short, simple and age-appropriate.
-   [ ] AI does not deliver a full lesson or replace the instructor.
-   [ ] If source is insufficient, AI says so and offers human help.
-   [ ] "I understood" feedback is recorded.
-   [ ] "I still don't understand" creates one linked help request.
-   [ ] Help request contains student, course, lesson, question, AI
    response and video timestamp when available.
-   [ ] Responsible instructor receives notification.
-   [ ] Admin sees the same request.
-   [ ] Instructor can reply inside the platform request thread.
-   [ ] Student receives notification and can reply.
-   [ ] Request status and audit trail are available.
-   [ ] AI provider outage does not block video, PDF or learning.
-   [ ] Provider data policy, cost controls, retention and minor-consent
    requirements are approved before activation.

## 10. Commerce, subscriptions and instructor finance

-   [ ] Individual course purchase is supported.
-   [ ] Bundles grant all included eligible courses once.
-   [ ] Monthly subscription grants only plan-included courses.
-   [ ] Annual subscription grants only plan-included courses.
-   [ ] Course access mode controls one-time/subscription eligibility.
-   [ ] Unified entitlement engine is used by player, files, live links
    and AI.
-   [ ] Egypt is the only active checkout market at launch.
-   [ ] Saudi checkout is disabled.
-   [ ] Legal seller/invoice settings are approved for Egypt.
-   [ ] Payment provider credentials and callback security are
    configured.
-   [ ] Browser redirect alone does not mark payment as paid.
-   [ ] Duplicate webhook cannot duplicate payment fulfillment or
    access.
-   [ ] Instapay/manual payment evidence is reviewed by authorized
    finance staff.
-   [ ] Coupons respect date, usage and product restrictions.
-   [ ] Refunds preserve original transaction history and apply approved
    access rules.
-   [ ] All student money is collected centrally by Young Go.
-   [ ] No direct split payment to instructors is enabled.
-   [ ] Individual course commission percentage is configurable.
-   [ ] Bundle value allocation is traceable and
    owner/accountant-approved.
-   [ ] Subscription instructor earning is a fixed amount per
    student/course/period.
-   [ ] Subscription earnings are not based on watch time or engagement.
-   [ ] Duplicate student-course-period ledger entries are prevented.
-   [ ] Instructor statement reconciles to orders/subscriptions.
-   [ ] Settlement approval and paid reference are audited.
-   [ ] Exact commission rates, accounting base and settlement schedule
    are approved before live payouts.

## 11. Administration, reports and notifications

-   [ ] SKIT shell is consistent across admin/instructor/finance/content
    panels.
-   [ ] Role-aware sidebar includes all approved modules.
-   [ ] Course/user/payment/enrollment/help-request/finance lists
    support search/filter/sort/pagination.
-   [ ] CSV exports honor filters and permissions.
-   [ ] Dashboard KPIs are backed by actual records.
-   [ ] Reports reconcile to transaction and ledger records.
-   [ ] In-app/email notification templates are localized.
-   [ ] Notifications are deduplicated across retries.
-   [ ] Audit log captures sensitive actions.
-   [ ] Secrets and sensitive learner data are excluded from routine
    logs.

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
-   [ ] Parent creates a child account; child logs in with username/password and no email.
-   [ ] Link code links an existing learner only after confirmation; code is single-use and expires.
-   [ ] Only birth year/month stored; age band computed server-side.
-   [ ] Parent acting in child context is recorded as actor in audit.
-   [ ] Parent pays; entitlement belongs to child.
-   [ ] Certificate uses certificate name in the template language; name locks after first certificate.
-   [ ] Cohort capacity is never exceeded under concurrent checkout; waitlist promotion works.
-   [ ] Cohort live sessions are visible only to cohort members.

### Content protection and publishing
-   [ ] Every media asset has a protection tier; paid lesson with T3 source cannot publish without exception reason.
-   [ ] Watermark visible on T1/T2 playback including fullscreen and mobile.
-   [ ] Session limit and device limit enforced; admin device reset works.
-   [ ] Independent instructor edit to a published course creates a pending revision; live version unchanged until approval.

### Paymob, manual payments, subscriptions
-   [ ] Paymob callback signature verified; amount/currency/order reference matched.
-   [ ] Replayed/duplicate webhook does not duplicate entitlement or ledger.
-   [ ] Browser return page never marks an order paid.
-   [ ] Daily reconciliation report lists mismatches.
-   [ ] Instapay proof with a duplicate transfer reference cannot be approved.
-   [ ] E-receipt adapter state matches O-023 decision (disabled until decided).
-   [ ] Renewal reminders 7/3/1 days sent once each; grace period then plan-only access revoked; progress retained.

### Instructors, engagement, search, privacy
-   [ ] Instructor application → review → agreement acceptance → payout profile flow works; payout data encrypted and Finance-only.
-   [ ] Wishlist works; reviews require entitlement and moderation.
-   [ ] Arabic search normalization verified (hamza/ta marbuta/alef maqsura/diacritics).
-   [ ] Marketing tags load only after consent and never on learning/child/AI screens.
-   [ ] Data export and deletion requests complete; financial records retained anonymized.
-   [ ] Retention pruning job runs with configured values.
-   [ ] Staging refresh uses anonymization command.

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
