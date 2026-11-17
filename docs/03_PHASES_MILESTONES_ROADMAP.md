# YOUNG GO LMS --- PHASES, MILESTONES & DELIVERY ROADMAP

**Document ID:** YG-ROADMAP-002\
**Version:** 2.1 (v3.1 gap closure)\
**Date:** 16 November 2026\
**Status:** Proposed execution sequence; no dates, effort estimates or
staffing commitments are implied.

## Execution note (v3.1)

This roadmap is executed through the agent loop in `agents/`. Every milestone below is broken into numbered tasks in `agents/tasks/P00.md` ... `agents/tasks/P12.md` with dependencies, agent tier, steps, acceptance and checks. Task status lives in `agents/PROGRESS.md`. If a task file and this roadmap disagree, the PRD wins, then the task file.

v3.1 additions placed into phases:

| Addition | Phase / milestone |
|---|---|
| Design system tokens, brand extraction, UI kit | P0 (M0.4) + P1 (M1.5) |
| Child login, link codes, birth year/month, certificate names | P1 (M1.3, M1.6) |
| Arabic search normalization | P2 (M2.3) |
| Media protection tiers, revisions after publish | P3 (M3.4, M3.7) |
| Cohorts, capacity, waitlist | P5 (M5.0) |
| Watermark, session/device limits | P4 (M4.5) |
| AI v3.0 pipeline (M7.0--M7.7) | M7.0/M7.1 with P3; rest after P4 |
| Paymob, manual proof, reconciliation, e-receipt adapter, renewal | P8 |
| Instructor onboarding, wishlist, reviews, consent/analytics, WhatsApp adapter, privacy rights | P9 |

## Delivery principles

-   Deliver vertical, demonstrable functionality, not disconnected
    infrastructure layers.
-   Keep the old website live and unchanged until cutover.
-   Build shared foundations once: identity, roles, course model,
    entitlement service, audit and localization.
-   Every milestone has an observable output and acceptance gate.
-   Do not enable payments or AI with real learner data until necessary
    legal/provider/privacy gates are approved.
-   Use one focused validation per milestone; reserve deep regression
    and backup/restore for the final phase.

## Phase overview

  -------------------------------------------------------------------------
  Phase          Name             Main outcome              Dependency
  -------------- ---------------- ------------------------- ---------------
  P0             Discovery,       Verified environment,     First
                 legacy audit &   repo, brand assets,       
                 technical        architecture baseline and 
                 foundation       running custom skeleton   

  P1             Identity, roles, Secure user model,        P0
                 localization &   permissions,              
                 family accounts  Arabic/English and        
                                  parent-child links        

  P2             Public website,  Brand-preserving website, P0; overlaps P1
                 CMS & course     catalog and course pages  
                 discovery                                  

  P3             Course Builder & Full authoring wizard,    P1
                 curriculum       sections, lessons, media  
                 engine           and PDF materials         

  P4             Student learning Entitled course playback, P3 +
                 portal &         curriculum, progress and  entitlement
                 recorded player  resources                 foundation

  P5             Live & hybrid    Schedule, protected       P3/P4
                 learning         meeting links, reminders, 
                                  attendance and replay     

  P6             Quizzes,         Assessment and            P3/P4
                 assignments &    practical-work lifecycle  
                 certificates                               

  P7             Lesson AI        Short PDF-grounded help   P4 + P1;
                 assistant &      and in-platform           privacy gate
                 instructor help  instructor follow-up      
                 requests                                   

  P8             Commerce,        Individual sales,         P1/P3;
                 subscriptions &  bundles, monthly/annual   gateway/legal
                 instructor       plans, central collection dependencies
                 finance          and simple commission     
                                  ledger                    

  P9             Admin            Full SKIT operations,     P1-P8
                 operations,      reports, exports,         foundations
                 reporting &      newsletter/contact/blog   
                 communications   and notifications         

  P10            Legacy           Existing course library   P3/P4/P6;
                 course/data      migrated and reconciled   legacy access
                 migration        in staging                

  P11            Final QA,        Approved production       All required
                 launch, training release on existing       phases
                 & cutover        domain with rollback and  
                                  handoff                   

  P12            Saudi market     Activate Saudi checkout   Separate owner
                 activation &     only after legal/payment  approval
                 future expansion approval; optional later  
                                  capabilities              
  -------------------------------------------------------------------------

## P0 --- Discovery, legacy audit & technical foundation

### M0.1 --- Access and environment inventory

**Work:** inspect current live website/domain, existing LMS admin,
source repository (if available), database, file storage, video
providers, live provider, payment/Instapay workflow, server/runtime and
integrations. Obtain existing logo/brand assets and document actual
production hostname.

**Deliverables:** verified environment inventory; source/data access
list; brand asset inventory; course/media inventory method; risks and
blocking credentials list.

**Acceptance:** every technical fact is marked verified or unknown; no
guessed host/repo/gateway/storage; old production remains unchanged.

### M0.2 --- Architecture and delivery baseline

**Work:** confirm modular monolith, framework/runtime compatibility,
database option, storage/video strategy, localization, SKIT and
migration approach. Laravel 13/Filament 5 are candidates, not hard locks
until compatibility is checked.

**Deliverables:** architecture decision record; module map; initial
database/domain map; environment strategy; branch/release conventions.

**Acceptance:** choices are tied to observed environment and approved
requirements; open choices are logged.

### M0.3 --- Running custom application skeleton

**Work:** create/initialize repository, app boot, environment config,
base migrations, health route, authentication foundation, brand shell
and staging deployment pipeline where access exists.

**Deliverables:** working custom app, README, docs folder,
PROJECT_STATE/HANDOFF and deployment evidence if deployed.

**Acceptance:** app boots from documented setup; no legacy LMS package
is core; repository/environment facts recorded.

## P1 --- Identity, roles, localization & family accounts

### M1.1 --- Authentication and profiles

Registration, login/logout, password reset, email
verification/activation, profile, avatar, locale and notification
preferences.

**Accept:** invalid/duplicate accounts handled safely; inactive users
cannot access protected routes; users edit permitted fields only.

### M1.2 --- RBAC and role assignment

Super Admin, Admin, Content Manager, Academy Instructor, Independent
Instructor, Student, Parent/Guardian, Finance/Enrollment and Support
roles; granular permission catalog and role assignment screen.

**Accept:** backend policies block unauthorized URLs/API calls; menus
reflect permissions; role changes audited.

### M1.3 --- Parent-child account relationships

Verified parent/guardian links to multiple distinct child accounts;
child context switching; guardian dashboard permissions.

**Accept:** parent sees linked children only; child profiles/progress
remain distinct; unauthorized linking blocked; consent/verification
state recorded.

### M1.4 --- Age suitability model

Configurable min/max age or age-band fields and course discovery filter;
do not hard-code fixed age brackets.

**Accept:** admin configures suitability; course page and filters show
accurate guidance.

### M1.5 --- Localization foundation and SKIT shell

Arabic/English translation structure, locale switching, RTL/LTR, shared
SKIT admin/instructor layout, responsive sidebar/topbar/forms/tables.

**Accept:** first real admin/instructor screens render correctly in both
locales, including mixed text and table direction.

## P2 --- Public website, CMS & discovery

### M2.1 --- Brand-preserving website shell

Recreate existing Young Go identity using current logo/assets/colors;
responsive header/footer/navigation and public page layout.

**Accept:** no rebrand; identity recognizable; mobile/desktop work.

### M2.2 --- CMS and marketing pages

Homepage sections, custom pages, About/Contact/FAQ/Privacy/Terms/Refund
pages, banners/testimonials/instructor blocks and publish states.

**Accept:** authorized content manager updates content without code;
unpublished pages are not public.

### M2.3 --- Catalog, categories and course details

Hierarchical categories/subcategories, levels, age suitability,
format/language/instructor/price filters, search, sort, pagination,
details and preview.

**Accept:** published courses only; filters combine; course
data/price/access accurate.

### M2.4 --- SEO, blog, contact and newsletter

Bilingual SEO fields, canonical/OG, sitemap/robots, blog
categories/tags, contact enquiry inbox and newsletter subscriber
management.

**Accept:** metadata correct; enquiries/subscribers stored/exportable;
consent/unsubscribe supported.

## P3 --- Course Builder & curriculum engine

### M3.1 --- Course/category/instructor domain

Course CRUD, academy/independent ownership, instructor assignment, age
suitability, language, format, statuses and featured flag.

**Accept:** instructors manage assigned courses only; independent course
cannot publish before approval.

### M3.2 --- Multi-step course wizard

Basic, Information, Pricing, Media, SEO, Custom Fields and Finish steps.
Save/resume drafts; Arabic/English content; validation summary.

**Accept:** data persists across steps; incomplete drafts retained;
approval/publish permission-controlled.

### M3.3 --- Curriculum manager

Sections and ordered learning items, add/edit/reorder/archive, preview
and per-item visibility.

**Accept:** persisted order matches student player; historical
attempts/submissions survive content archive.

### M3.4 --- Lesson media and resources

YouTube, Vimeo, upload, MP4/direct link, Wasabi/object storage, Google
Drive reference, audio, document, text, image and allowlisted
iframe/embed. Duration, description, preview/free flag, media status and
resource folder.

**Accept:** supported source renders; private assets require
entitlement; invalid/unavailable media is reported clearly.

### M3.5 --- Lesson curriculum PDF

Upload/version/download permissions and AI indexing eligibility per
recorded lesson.

**Accept:** entitled student downloads; private URL cannot be reused by
unauthorized account; AI only enables after PDF processing/approval.

### M3.6 --- Quiz and assignment authoring hooks

Attach quiz or practical assignment to course/section/lesson from
curriculum flow; detailed runtime in P6.

**Accept:** items appear in correct curriculum position and retain
association.

## P4 --- Student learning portal & recorded player

### M4.1 --- Unified entitlement foundation

Implement entitlement records/service for free enrollment and manual
grants first; later connect purchases/bundles/subscriptions.

**Accept:** one service determines access and records source, dates and
status.

### M4.2 --- Student dashboard and course library

My courses, continue learning, progress, upcoming sessions, pending
work, results and certificates.

**Accept:** student sees own data only; parent view follows guardian
scope.

### M4.3 --- Recorded course player

Responsive video/player, section/lesson navigation, durations,
completion states, previous/next, resume position, progress bar and
resources.

**Accept:** learner resumes correctly; progress server-persisted; locked
content explains why.

### M4.4 --- Protected PDF/resources and previews

Entitlement-protected lesson PDF/material download and controlled public
preview.

**Accept:** preview exposes designated content only; private resource
cannot be downloaded by copied URL without access.

## P5 --- Live & hybrid learning

### M5.1 --- Live session scheduling

Course/section/cohort association, instructor, date/time/timezone,
provider, link, access window and status.

**Accept:** session appears correctly for enrolled learners and
instructors.

### M5.2 --- Protected meeting links

Manual Zoom/Meet/BigBlueButton/custom links; visibility by enrollment
and time window.

**Accept:** unauthorized learner cannot retrieve join URL through
UI/API/direct request.

### M5.3 --- Reminders and lifecycle

Scheduled reminders, reschedule/cancel/completed states and deduplicated
notifications.

**Accept:** correct recipients receive one notification per event;
retries do not duplicate.

### M5.4 --- Attendance and replay

Manual attendance, correction audit and optional replay asset.

**Accept:** attendance belongs to correct session/enrollment; replay
uses recorded permissions; AI disabled in live.

## P6 --- Quizzes, practical assignments & certificates

### M6.1 --- Quiz runtime and attempts

Single/multiple choice, true/false, short answer where deterministic,
time limit, marks, pass score, attempt count and next-lesson gating.

**Accept:** server enforces time/attempts/pass rules; question snapshots
preserve history; hidden answers not exposed.

### M6.2 --- Practical assignment submissions

Text/image/video/audio/document uploads, due date, accepted types, max
size, status and revision rules.

**Accept:** submission tied to correct learner/course/assignment and
protected from other students.

### M6.3 --- Instructor review and grading

Review queue, comments, grade, accept/reject/request revision and
publish feedback.

**Accept:** only assigned instructor/authorized admin can grade; learner
notified; changes audited.

### M6.4 --- Completion engine and certificates

Configurable completion conditions, PDF certificate, unique ID/token and
public verification.

**Accept:** certificate issued only after configured gates; duplicate
jobs do not duplicate; revoked certificates show invalid.

## P7 --- Simple AI lesson assistant & instructor help requests

> v3.1: P7 milestones are replaced by M7.0--M7.7 in `06_AI_LESSON_ASSISTANT_SPEC.md` §20. The text below is kept for history.

### M7.1 --- PDF processing and indexing

Extract text from current lesson curriculum PDF, store
source/version/index status and query only within lesson.

**Accept:** only approved PDF for current lesson indexed; replacement
invalidates old active source; unreadable/missing PDF disables AI.

### M7.2 --- Contextual AI assistant UI

Embedded panel on recorded lesson player, short-answer prompt, age-aware
wording and Arabic/English response.

**Accept:** not shown during live; answer short and grounded only in
current lesson PDF; unsupported question is acknowledged.

### M7.3 --- Understanding feedback and help request

Buttons "I understood" / "I still don't understand"; capture question,
AI answer, lesson, video timestamp and student.

**Accept:** unresolved action creates one request; repeated clicks
idempotent; understood does not create request.

### M7.4 --- Instructor/admin queue and in-platform thread

Assign responsible instructor; notify instructor; show admin copy;
status and replies.

**Accept:** instructor replies inside request; student notified/can
reply; admin can monitor/intervene; no general open chat.

### M7.5 --- AI operations controls

Provider configuration, quotas, timeouts, usage/cost visibility and
disable switch.

**Accept:** AI outage does not block video/PDF/learning; unauthorized
course/lesson data never enters provider context.

## P8 --- Commerce, bundles, subscriptions & instructor finance

### M8.1 --- Product pricing and order engine

Individual course price, free/paid, subscription-only/both, currency,
access duration, server-side pricing and order records.

**Accept:** totals server-calculated and immutable after payment except
audited adjustment.

### M8.2 --- Central payment and Egypt activation

Payment adapter, offline/manual/Instapay review, provider callback
verification, refunds and payment history.

**Accept:** only verified gateway payment or authorized manual approval
grants access; Egypt only; provider stays disabled until
credentials/legal config approved.

### M8.3 --- Bundle products

Bundle create/edit, included courses, pricing, availability, discount
and per-course allocation values/weights for instructor revenue.

**Accept:** one purchase grants all included eligible courses once;
allocation traceable. Formula owner/accountant-approved.

### M8.4 --- Monthly and annual subscriptions

Plans, included courses/categories, term, price, start/end,
renewal/expiry state and access revocation.

**Accept:** active plan grants only included courses;
expiry/cancellation updates entitlement; family plan not assumed.

### M8.5 --- Instructor commission ledger

Percentage on individual sale, bundle course allocation plus percentage,
fixed amount per student-course-subscription period.

**Accept:** ledger generated once per eligible event; no
watch-time/engagement calculation; no direct split payout.

### M8.6 --- Statements and settlements

Instructor statements, finance approval, settlement batch, manual paid
reference and adjustment/reversal.

**Accept:** totals reconcile to ledger; settlement history
immutable/audited.

### M8.7 --- Saudi market profile (disabled)

Prepare SAR, country settings, legal entity fields, payment adapter and
invoice settings, but keep checkout disabled.

**Accept:** no Saudi checkout/order/payment until activation approval.

## P9 --- Operations, SKIT, reporting and communications

### M9.1 --- Admin/instructor operations completion

Role-aware CRUD for courses, users, instructors, guardians, enrollments,
grants, orders, subscriptions, bundles, payments, help requests,
certificates and settings.

**Accept:** roles see authorized modules/data; key operations audited.

### M9.2 --- Reports and CSV exports

Platform, student, course, instructor, live, assessment, finance,
instructor earnings, content migration and AI/help reports.

**Accept:** filters/exports match source records and permissions; no
duplicate transaction counting.

### M9.3 --- Notifications/templates

In-app/email event notifications and localized templates for core
events.

**Accept:** deduplicated, correct recipient/language and actionable
links.

### M9.4 --- Operational modules from legacy sidebar

Custom pages, subscription plans, manual grants, role assignments,
Instapay Payments, coupon checkout usage, Young Go settings, newsletter,
contact, blog, messages/help inbox and profile.

**Accept:** all useful legacy operations represented; intentional
removals require owner approval.

## P10 --- Legacy migration

### M10.1 --- Source inventory and mapping

Inspect legacy database/files; inventory courses, sections, lessons,
media, PDFs, quizzes, assignments, live sessions, users and access
records.

**Accept:** every source entity mapped to target, excluded with reason
or flagged as exception.

### M10.2 --- Course/content migration tooling

Build repeatable import scripts/commands with dry-run, logs, checkpoints
and safe rerun behavior.

**Accept:** rerun does not duplicate courses, sections, lessons or
assets.

### M10.3 --- Course library import

Migrate all courses, bilingual metadata, category/level, prices/access,
thumbnails, preview links, SEO, FAQs, requirements, outcomes, curriculum
order, lesson types/media, PDFs and resources.

**Accept:** counts reconcile; sampled courses render/play; broken links
listed as exceptions.

### M10.4 --- Related learner/business data (scope sign-off)

Inventory/prepare migration for student accounts, guardian links if any,
enrollments, active access, manual grants, progress, quiz attempts,
assignments/submissions, certificates, orders/payments and reviews.
Exact historical scope is owner-approved after inspection.

**Accept:** no active learner silently loses access; migrated
financial/grade/certificate history retains provenance.

### M10.5 --- Rehearsal, delta and cutover freeze

Run staging import, reconcile, fix mappings, freeze legacy writes at
agreed cutover window, run final delta.

**Accept:** final reconciliation signed off; rollback snapshot exists.

## P11 --- Final QA, launch, training & handover

-   **M11.1 Final integrated QA:** multi-role journeys, security/access,
    payment states, AI scope, media, AR/EN, responsive, migration
    reconciliation and performance sized to actual traffic.
-   **M11.2 Backup/restore:** verify database/private media backup,
    retention, restore and rollback target; keep backups outside app
    host where possible.
-   **M11.3 Training:** admin, content manager, instructor, finance and
    support guides; authoring/help/payment/settlement workflows.
-   **M11.4 Cutover:** verify hostname/DNS/TLS, deploy approved release,
    import final delta, switch domain, smoke-check and monitor with
    rollback preserved.
-   **M11.5 Handover:** repository/branch/commit, environment map,
    provider notes, runbook, backup status, known issues, training and
    next backlog. Old system retirement needs separate owner approval.

## P12 --- Saudi activation and future expansion (separate approval)

Complete Saudi entity/legal/tax review; configure payment account, bank
settlement, currency, invoice/refund policies; test independently;
obtain explicit owner approval before enabling sales. Native apps, voice
AI, adaptive pathways, SCORM/xAPI and other expansions require separate
scope/approval.

## Critical dependencies and parallel work

-   P0 access audit is required for migration, hosting and domain
    cutover.
-   P1 identity/RBAC and entitlement foundation are dependencies for
    protected learning/commerce.
-   P3 Course Builder is required for P4/P5/P6/P7 and migration mapping.
-   P4 recorded player + lesson PDF is required for P7 AI.
-   P8 financial rules/provider approvals are required for live paid
    checkout/settlement.
-   P10 inventory/mapping can begin during P3/P4; production import
    waits for stable target schema.
-   P2 public site and P3 builder can overlap after shared
    brand/identity foundations.
-   P5 live scheduling can overlap P6 assessment after curriculum
    entities exist.

## Milestone completion rule

A milestone is complete only when acceptance criteria are demonstrated
with real persisted data, authorization is enforced, errors/states are
handled, relevant docs are updated and one focused validation is
recorded. A screen existing or import running alone is not completion.
