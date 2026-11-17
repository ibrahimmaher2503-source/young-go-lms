# YOUNG GO LMS --- MASTER PRODUCT REQUIREMENTS DOCUMENT

**Document ID:** YG-PRD-003\
**Version:** 3.2 --- Second gap review (LearnHouse reference study, engineering decisions, UX flows)\
**Date:** 16 November 2026 (v3.0: 15 November 2026)\
**Development partner:** I.S. Intelligent Solutions\
**Audience:** Product owner, PM, UX/UI, backend/frontend developers, QA,
migration and operations teams\
**Status:** Product direction approved; provider, legal and
infrastructure dependencies are explicitly open.

------------------------------------------------------------------------

## Document control

This PRD consolidates the June 2026 product and technical PRDs, the
November 2026 v2 documentation pack, the legacy-system screen review,
and the owner's latest decisions made on 15 November 2026.

Requirement labels: - **\[APPROVED\]** explicitly confirmed by the
product owner. - **\[LEGACY-CARRY\]** present in the June 2026 PRDs or
existing LMS screens and retained unless superseded. -
**\[RECOMMENDED\]** implementation recommendation, not a
commercial/legal decision. - **\[OPEN\]** must not be guessed by
developers; resolve when the dependency is reached.

**v3.2 note:** §29.14 adds second-review requirements; engineering decisions are in `13_ENGINEERING_DECISIONS.md` (ED-*), UX journeys in `15_UX_FLOWS_AND_PATTERNS.md`, LearnHouse comparison in `14_LEARNHOUSE_REFERENCE_STUDY.md`.

**v3.1 note:** Section 29 adds gap-closure requirements (IDs `YG-GAP-*`). They extend, and do not remove, any v3.0 requirement. Section 15 is now detailed by `06_AI_LESSON_ASSISTANT_SPEC.md` v3.0.

Official product name is **Young Go**. "Yango Go" in the June 2026 source PDFs refers to the same product.

Priority: **P0** = required for initial production/core learning; **P1**
= required for the complete commercial/operational release or next
planned delivery; **P2** = later expansion requiring separate approval.

Conflict order: latest explicit owner decision → this PRD → approved
decision register → DMC technical constraints → earlier PRDs as
historical source. Do not silently reintroduce superseded scope or
remove a legacy capability merely because it was not visible in one
screenshot.

## 1. Executive summary

Young Go LMS is the proprietary learning platform operated under the
existing Young Go brand and website identity. The current platform is to
be rebuilt as a fully custom LMS, not themed, forked, or based on
Academy LMS, EduEx, WordPress LMS, or another packaged LMS product. The
new application must preserve the existing Young Go logo, brand
identity, domain and useful operational capabilities while delivering a
cleaner, more capable and maintainable product.

The platform serves children, teenagers, young adults and adults. Its
principal educational focus is artistic, creative, talent-development
and personal-development learning: acting, drama, melodrama,
voice/dubbing, drawing, animation, creative skills and related
disciplines. It is not designed primarily as a conventional
academic/scientific course platform. Course age suitability is
configurable; the platform must not hard-code one age range.

Young Go delivers recorded, live and hybrid courses. A course is
structured into ordered sections and learning items. Recorded lessons
can include video from an upload or supported external source, a
dedicated downloadable curriculum PDF, other learning materials,
optional quizzes and practical assignments. Live sessions are scheduled
separately and have protected join links. The student learning interface
presents a clear curriculum, player, progress and next step.

A deliberately simple AI Learning Assistant is embedded in recorded
lessons only. It uses the curriculum PDF attached to the current
lesson/session as its course-specific reference, gives short and simple
clarifications, and does not replace the instructor or deliver long
lessons. It is not available during a live session. If the student still
does not understand, the student can raise a help request. The
responsible instructor receives a notification and the administration
sees the same request. Follow-up takes place inside the platform in that
request thread.

The commercial model supports individual course purchases, bundles, and
monthly/annual subscriptions. Young Go centrally collects student
payments and subsequently calculates and settles instructor earnings.
The instructor does not receive direct split payments from the checkout
gateway. The platform supports academy-owned courses and independently
supplied instructor courses under Young Go approval.

The initial active market is Egypt. The Egyptian legal entity is already
registered. Saudi Arabia must be technically prepared as a future market
but checkout and sales in Saudi Arabia remain disabled until the Saudi
legal entity and its commercial/payment setup are completed and
approved. The first release is fully bilingual Arabic/English, with
architecture ready for more languages.

The existing courses and their related course content must be migrated
into the new system. The old website remains operational until the new
platform has passed acceptance and the controlled domain cutover is
approved.

## 2. Product vision, goals and KPIs

### 2.1 Vision

Provide Young Go with a branded, reliable and extensible digital academy
that manages the full learning lifecycle: course discovery,
registration, family-linked student accounts, enrollment, recorded/live
learning, assessments, practical submissions, instructor support,
certificates, payments, instructor settlements and operational
reporting.

### 2.2 Business goals

1.  Own the LMS source code, domain model and business logic.
2.  Preserve the current Young Go brand and continuity of the existing
    website.
3.  Deliver a learning experience suitable for creative and
    talent-development courses across age groups.
4.  Make course creation and lesson publishing efficient for the academy
    and independent instructors.
5.  Reduce fragmented coordination and manual follow-up.
6.  Support one-time course purchase, bundles, monthly subscriptions and
    annual subscriptions through one entitlement engine.
7.  Centralize collections, financial records and instructor earnings.
8.  Provide parents/guardians with linked child accounts and appropriate
    visibility.
9.  Make the AI assistant useful for small lesson-specific
    clarifications, not a replacement teacher.
10. Migrate the existing course library and content without losing the
    live platform before cutover.

### 2.3 KPI framework

Numeric targets are OPEN until baseline traffic, course volume and
operational capacity are measured. The system must collect:
visitor-to-registration and course-page-to-enrollment conversion;
orders, paid conversion, discounts, refunds and net collections; active
students/parents/instructors; published courses and lesson counts;
lesson/course completion; live attendance; quiz pass rate; assignment
submission/review time; certificates issued/verified; instructor
earnings accrued/approved/settled; AI usage, source-grounding success,
unresolved requests, latency and cost; help-request resolution time;
availability, failed jobs, storage health and backup status.

## 3. Scope and boundaries

### 3.1 In scope

-   Existing Young Go public website identity, logo and domain
    continuity.
-   Public homepage, course catalog, course details, category pages and
    conversion flows.
-   Arabic and English UI from first release; translation-ready
    architecture.
-   Accounts for students, parents/guardians, instructors, admins,
    content managers, finance and support.
-   Parent account linked to one or more separate child learner
    profiles.
-   All age groups, with age suitability configured per course.
-   Academy-owned and independent-instructor courses with approval
    before publication.
-   Full course builder, bilingual course information, categories,
    levels, pricing, media, SEO and custom fields.
-   Curriculum builder with sections, lessons, quizzes, assignments,
    live sessions and resources.
-   Recorded lessons, lesson PDFs, supported media sources and protected
    access.
-   Live/hybrid scheduling, protected links, reminders, attendance and
    optional replay.
-   Student player, progress, resume, lesson completion and resources.
-   Quizzes, question options, attempts, scoring, pass/fail and attempt
    limits.
-   Practical assignments with image/video/document uploads and
    instructor review.
-   Certificates and public verification.
-   Personal lesson AI assistant with restricted behavior in Section 15.
-   In-platform instructor help-request thread; no unrestricted
    student-to-instructor chat requirement.
-   Individual purchases, bundles, monthly/annual subscriptions, coupons
    and manual grants.
-   Egypt-first collections; Saudi market configuration prepared but
    disabled.
-   Centralized collection and instructor earnings ledger/settlement.
-   Existing Instapay/manual-payment workflow where supported; no
    automatic Instapay API is assumed.
-   Admin/instructor operations panels following SKIT.
-   Notifications, messages/announcements, newsletter, contact
    enquiries, blog, CMS, SEO, reports, exports and audit trail.
-   Migration of every existing course and related learning
    content/assets.
-   Staging, production, backup, restore, monitoring, training and
    controlled cutover.

### 3.2 Out of scope unless separately approved

-   Native Android/iOS apps; responsive web is first.
-   AI voice conversations, live AI assistance, long-form AI tutoring,
    AI-generated course plans, adaptive learning paths or AI talent
    scoring.
-   AI grading/submission, automatic certificate decisions or AI changes
    to student records.
-   Open-ended direct messaging between students and instructors outside
    a help request.
-   Automatic Zoom/Meet/BigBlueButton meeting creation unless separately
    selected and approved; manual meeting links are the initial path.
-   Saudi checkout/invoicing/collection before legal entity and provider
    readiness.
-   Automatic payment splitting to instructors.
-   Family subscription pricing/shared household plans; linked parent
    accounts do not automatically imply family subscriptions.
-   SCORM/xAPI, proctoring, corporate LMS, multi-tenant SaaS, affiliate
    network or referral commissions.
-   Guaranteed DRM or prevention of screen recording.
-   Young Film School / YFS features; this is a separate project.

## 4. Brand, domain, markets and languages

### 4.1 Brand continuity \[APPROVED\]

-   Keep the existing Young Go name, current logo, brand colors and
    recognizable visual identity.
-   Do not create a new logo, rebrand or replace the current brand
    system.
-   UI/UX may be modernized for clarity, responsiveness, accessibility
    and usability while retaining the current identity.
-   Use the current website/domain identified by the owner as
    `youngschool.com`. Verify exact production hostname, DNS and TLS
    against the live system before cutover; do not change the domain as
    part of the rebuild.
-   Legacy site remains live until cutover sign-off. No destructive
    changes to current production during development.

### 4.2 Country rollout \[APPROVED\]

  ------------------------------------------------------------------------------
  Market       Initial status Currency/configuration   Commercial rule
  ------------ -------------- ------------------------ -------------------------
  Egypt        Active launch  EGP; Egyptian registered Sales/payment/invoicing
               market         legal entity             can be enabled after
                                                       Egyptian gateway/legal
                                                       configuration is supplied
                                                       and approved.

  Saudi Arabia Technically    SAR and country-specific No checkout, sales or
               prepared,      settings can be          Saudi invoice issuance
               commercially   configured               until Saudi entity
               disabled                                registration and owner
                                                       activation approval.
  ------------------------------------------------------------------------------

Use country/market configuration rather than hard-coded country logic.
Legal entity name, tax IDs, VAT treatment, invoice fields, gateway and
settlement bank details are OPEN. Do not assume I.S. Intelligent
Solutions is the legal seller.

### 4.3 Languages \[APPROVED\]

-   Full Arabic and English interface from the first release.
-   Correct Arabic RTL and English LTR across public pages, dashboards,
    forms, tables, player, email and PDFs where applicable.
-   Architecture allows additional locales without redesigning the data
    model.
-   Interface language is independent from course content language.
-   A course may be Arabic, English or bilingual. Course metadata
    supports translated values where provided; content-language marker
    describes actual instructional content.
-   Default locale is configurable; Arabic is recommended initially, not
    hard-coded.

## 5. Audience, age model and family accounts

### 5.1 Learner audience \[APPROVED\]

Young Go serves all ages. Main content positioning is children and young
people, but adults must also be supported. Admin controls course
suitability using configurable minimum/maximum age or age-band values.
Do not hard-code fixed age brackets.

Course categories and discovery filters should support
artistic/developmental subjects such as acting, theatre, melodrama,
voice acting/dubbing, drawing, animation, creative skills and future
categories. Taxonomy is admin-managed.

### 5.2 Parent/guardian account \[APPROVED\]

-   Parent/guardian has an independent account.
-   One parent account can link to multiple child learner accounts.
-   Each child retains a distinct learner profile, course access,
    progress, assessment history and certificates.
-   Parent dashboard shows linked children and permitted information:
    enrolled courses, progress, upcoming live sessions, pending
    assignments, published results, certificates, orders and
    help-request status.
-   Parent may manage child enrollment/purchase where account policy
    permits.
-   Relationship must be explicitly established and auditable; users
    cannot self-link to another child without
    verification/authorization.
-   A child's private AI conversation is not automatically exposed in
    full to the parent. Parent visibility/consent policy must be
    configurable and approved before minors go live. Help-request
    status/replies should be visible to child and, where policy permits,
    guardian.

### 5.3 Child safety and consent \[RECOMMENDED / launch gate\]

Before enabling minor accounts, confirm applicable legal/consent policy,
age capture, guardian verification, privacy notice, retention and
communication rules. Teacher communication is limited to course-related
help requests; no unmoderated private chat is required. Instructor
access is restricted to assigned courses and learners. AI answers are
age-appropriate and narrowly educational.

## 6. Roles and permission model

  -----------------------------------------------------------------------
  Role                    Main capabilities       Restrictions
  ----------------------- ----------------------- -----------------------
  Super Admin             Full platform           Sensitive changes
                          configuration,          logged; no silent
                          roles/permissions,      destructive
                          integrations,           financial/history
                          finance/security        changes
                          oversight, audit        

  Admin / Academy Manager Operations, courses,    Only granted
                          instructors, students,  modules/actions
                          approvals, reports and  
                          settings according to   
                          grants                  

  Content Manager         Course content, media,  No finance/security
                          categories, CMS, blog   access by default
                          and SEO                 

  Instructor --- Academy  Assigned academy        Assigned courses and
                          courses, lessons, live  authorized learners
                          sessions, assessments   only
                          and submissions         

  Instructor ---          Submit/manage own       Cannot publish without
  Independent             approved courses and    approval; no direct
                          learner work under      collections or
                          academy rules           unrelated finance
                                                  access

  Student                 Browse, enroll, learn,  Own records and
                          quizzes, submissions,   currently entitled
                          certificates and lesson content only
                          AI                      

  Parent / Guardian       Manage linked children  Linked children only;
                          and view permitted      no unrelated learner or
                          learning/financial      private AI chat access
                          status                  by default

  Finance / Enrollment    Orders, payment review, No system security or
  Officer                 Instapay/manual proof,  curriculum publishing
                          refunds, manual grants, unless granted
                          instructor              
                          ledger/settlement       

  Support Agent           Scoped learner help and No broad finance,
                          operational             answer-key or private
                          troubleshooting         AI access by default

  Marketing / CMS role    Pages, blog,            No student private data
                          newsletter, contact     or finance by default
                          leads and SEO where     
                          granted                 
  -----------------------------------------------------------------------

Roles/actions are permission-configurable. Course-level instructor
assignment and guardian-child relationship are object-level scopes.

## 7. Core user journeys

-   **J01 Visitor discovers course:** Home → category/search/filter →
    course details → preview/curriculum → register/login →
    purchase/bundle/subscription → checkout/manual payment → entitlement
    → learner dashboard.
-   **J02 Parent manages child:** Parent registers/logs in → links child
    through approved verification → selects child context →
    browses/enrolls/pays where permitted → monitors progress/live
    schedule/results/certificates.
-   **J03 Instructor creates course:** Instructor panel → draft →
    bilingual/basic information → pricing/access → overview
    media/thumbnail → SEO → sections →
    lessons/resources/PDF/quizzes/assignments/live → preview → approval
    → publish.
-   **J04 Student studies recorded lesson:** Entitled course →
    curriculum → resume video → download PDF/materials → ask lesson AI →
    continue → progress saved.
-   **J05 Student asks human help:** AI answer → "Understood" or "Still
    not understood" → unresolved request with
    course/lesson/question/timestamp → instructor and admin notified →
    instructor replies in request thread → student responds/marks
    understood → resolved.
-   **J06 Student attends live:** Upcoming session → reminder →
    protected meeting link → attendance → optional replay published as
    recorded item. AI is unavailable during live.
-   **J07 Assessment:** Quiz/assignment → submit → objective score or
    instructor review → feedback → completion engine.
-   **J08 Certificate:** Completion rules pass → unique certificate →
    download → public safe verifier.
-   **J09 Purchase:** Order → server-calculated price/discount/currency
    → verified gateway/manual payment → entitlement once → course
    access. Failed/pending never grants paid access.
-   **J10 Instructor settlement:** Verified sale/period → ledger entry →
    finance review → approved settlement → paid reference/date → audit.

## 8. Public website, catalog and CMS requirements

  ----------------------------------------------------------------------------
  ID             Requirement           Acceptance criteria      Priority
  -------------- --------------------- ------------------------ --------------
  YG-WEB-001     Preserve current      Existing assets reused;  P0
                 brand/logo/identity   responsive improvements  
                                       do not rebrand           

  YG-WEB-002     CMS homepage          Manage hero, benefits,   P0
                                       featured courses,        
                                       categories, instructors, 
                                       testimonials, CTAs       

  YG-WEB-003     Course catalog        Search,                  P0
                                       category/subcategory,    
                                       age, level, format,      
                                       language, instructor,    
                                       price, availability      
                                       filters combine          

  YG-WEB-004     Course detail         Overview, outcomes,      P0
                                       requirements, curriculum 
                                       preview, instructor,     
                                       age, language,           
                                       price/access, FAQs,      
                                       preview and CTA          

  YG-WEB-005     CMS pages             About, Contact, FAQ,     P0
                                       Privacy, Terms, Refund   
                                       and custom pages         
                                       editable/publishable     

  YG-WEB-006     Blog                  Categories, tags,        P1
                                       featured image, author,  
                                       publish state, bilingual 
                                       fields, SEO              

  YG-WEB-007     Contact/lead capture  Enquiries stored,        P1
                                       searchable, assignable   
                                       and exportable           

  YG-WEB-008     Newsletter            Subscribers,             P1
                                       consent/source/status,   
                                       export and unsubscribe   

  YG-WEB-009     SEO                   Clean URLs, metadata,    P0
                                       canonical/OG,            
                                       sitemap/robots; only     
                                       public approved content  
                                       indexed                  

  YG-WEB-010     Responsive public UI  Desktop/tablet/mobile,   P0
                                       Arabic/English           
  ----------------------------------------------------------------------------

## 9. Course management and Course Builder

### 9.1 Course management list

Provide summary counters for active, paid, free, upcoming and
suspended/unpublished courses; filters for category, instructor,
price/access and status; search/sort/pagination/CSV; course
title/category/instructor/section and lesson counts/enrollment
count/price/status; permission-scoped actions to view public page,
preview player, open academic progress, edit, duplicate, manage
sections/lessons, feature/unfeature, archive and safe delete. Show
course access summary: active/expired enrollments and manual grants.

### 9.2 Multi-step course wizard \[LEGACY-CARRY\]

Retain the useful existing steps but improve navigation, validation and
save/resume: 1. **Basic:** title and bilingual content, short URL/slug,
short description, rich description, category/subcategory, level,
content language, age suitability, course format, ownership/source,
status and featured flag. 2. **Information:** FAQs, requirements,
learning outcomes and optional AR/EN translations; repeating rows
add/edit/reorder/remove. 3. **Pricing:** free/paid, individual purchase,
subscription-only or both, price/currency, access duration, eligible
plans, bundle eligibility and manual access. Active market controls
currency/checkout. 4. **Media:** overview provider/link, preview video
and thumbnail upload with preview. 5. **SEO:** keywords, SEO title, meta
description in English/Arabic, canonical/slug rules. 6. **Custom
Fields:** admin-defined fields with type, validation, visibility and
optional translation. 7. **Finish:** validation summary, preview, save
draft, submit for approval or publish by permission.

Wizard must save at any step, resume later, show step validation,
preserve entered data, support create/edit/duplicate/archive, and
prevent instructors from changing ownership/commission/finance/approval
without grants. Course translations are independent of interface
language.

### 9.3 Course status

Support Draft, Pending Review, Published/Active, Private,
Upcoming/Scheduled, Suspended/Unpublished and Archived. Separate
publication status from sales/access mode. Featured is a display flag,
not a publication state. Scheduled content may be enabled at course or
lesson level.

### 9.4 Course ownership and instructor model \[APPROVED\]

-   **Academy-owned:** Young Go controls the course; assigned
    instructors may teach/manage it.
-   **Independent-instructor:** independent instructor submits/owns
    instructional offering under Young Go commercial/content rules.
-   Both use the same builder, curriculum, player, enrollment and
    assessment engines.
-   Independent course cannot become public until academy approval.
-   Academy controls checkout and receives all student payments
    centrally.
-   Commission configuration is separate from course ownership and can
    vary by instructor/course.

### 9.5 Curriculum manager

Course → ordered sections → ordered learning items. Support
add/rename/reorder/edit/archive sections; add/edit/reorder/remove
lessons/items; accessible drag/drop or equivalent with persisted order;
section/lesson publish and visibility controls; course preview matching
student order; lesson materials action; labels/sequence numbers; safe
archive/delete preserving attempts, submissions, payments and
certificates.

### 9.6 Lesson creation flow \[APPROVED DIRECTION\]

Make common lesson creation fast: choose type, enter title/section,
attach media, set duration, add description/materials, attach curriculum
PDF, configure preview/free access and save. Supported types carried
from existing interface: - YouTube URL; Vimeo URL; uploaded video;
MP4/approved direct video URL. - Wasabi/object-storage asset; Google
Drive reference (permissions checked). - Audio file; document/PDF; rich
text; image/file resource. - Approved iframe/embed using allowlist and
safe sandbox policy.

Use provider adapter so video source changes do not rewrite course
logic. Validate source type, ownership/access settings, duration and
playback availability. Do not assume every external URL can be embedded
or protected.

### 9.7 Lesson curriculum PDF \[APPROVED\]

-   Each recorded session/lesson supports its own curriculum PDF.
-   PDF is downloadable by an entitled student from that lesson.
-   Same approved PDF is the primary knowledge source for that lesson's
    AI assistant.
-   AI is disabled if PDF is absent, unreadable, unapproved or not
    indexed successfully.
-   PDF is private by default; only entitled students can download,
    unless explicitly public preview.
-   Store metadata, version/hash, uploader, lesson relationship,
    approval/indexing state and download permission.
-   Replacing PDF creates a new version and refreshes AI index; retain
    version trace where needed.

### 9.8 Media security

Validate MIME/type, size, extension and storage policy. Private files
stay outside public webroot or use authorized temporary links. Check
entitlement on every download/playback. Iframe domains are allowlisted.
No permanent private-media URL is the sole protection.

## 10. Student learning experience

### 10.1 Student dashboard

Show own enrolled courses, continue-learning, progress, upcoming live
sessions, pending assignments, recent quiz results, certificates,
notifications and relevant activity. Parent-linked child context is
separate.

### 10.2 Course player \[LEGACY-CARRY\]

Retain current player concepts shown in screenshots: current
course/lesson title/type; responsive player; curriculum panel grouped by
section; lesson title/duration/completion; overall progress and
completed/total count; previous/next and resume; resource download; live
details for live item; AI panel only for eligible recorded lessons;
clear locked/preview/expired states with reason.

### 10.3 Progress

Save last lesson and playback position where provider permits, lesson
start/complete, progress percentage and last activity. Rules are
server-authoritative. Course completion follows configured rules, not
client checkbox alone. Manual overrides require permission and audit.

## 11. Live and hybrid learning

Support recorded-only, live-only and hybrid. Live session fields: title,
description, course, optional section/cohort, instructor,
date/time/timezone, duration/end, provider, meeting URL, access window
and lifecycle status. Initial approach supports manually supplied Zoom,
Google Meet, BigBlueButton or custom links where available; API
automation is not assumed. Join links are hidden from non-enrolled users
and exposed only in allowed window. States: draft, scheduled, live/open,
completed, cancelled, rescheduled. Changes notify and audit. Upcoming
sessions appear in dashboard/player. Email/in-app reminders are
configurable/deduplicated. Attendance can be marked by authorized
instructor/admin. Replay can be attached as recorded learning item. **AI
is unavailable during live.** Replay is AI-eligible only after
publication as recorded lesson with its own approved PDF.

## 12. Quizzes and assessments

Quiz can be linked to course, section or lesson. Fields: title,
instructions, association, duration/time limit, total marks, pass mark,
max attempts, result visibility, randomization where enabled, and
post-submission progression behavior. Retain current options: allow next
lesson after submission, or require pass score before unlocking next
lesson.

Question types: single-choice, multiple-choice, true/false, short answer
(auto-grade only with deterministic rules), essay/subjective for manual
review when configured. Each question has prompt, options, correct key,
points, explanation, order and active state. Hide correct answers until
allowed. Attempts use question/version snapshot so later edits do not
rewrite history.

Server enforces time, attempts, eligibility, submission, scoring and
pass/fail. AI cannot retrieve hidden answer keys or solve active graded
questions using private quiz records.

## 13. Practical assignments and submissions

Optional per lesson/section/course. Instructor can request acting scene,
drawing, voice recording or other creative task. Configuration: title,
instructions, attachments, due date, allowed
text/image/video/audio/document, max file size, accepted MIME, review
method, late policy, max revisions and status. Student submits
text/files. Instructor reviews, comments, grades, accepts, rejects or
requests revision. States: Draft, Submitted, Under Review, Accepted,
Needs Revision, Rejected, Resubmitted. Submission and grade changes are
auditable; learner receives in-app/email notification.

## 14. Certificates and completion

Configurable completion criteria: required lesson percentage, quiz pass,
assignment acceptance, live attendance or authorized manual approval.
Branded PDF using current Young Go identity. Unique certificate number
and verification token/URL; QR-ready output recommended. Verification
page exposes only approved safe fields. Issuance is idempotent;
revoke/void is explicit and audited. Certificate template/legal issuer
text are OPEN until final assets/wording supplied.

## 15. Personal AI Lesson Assistant \[APPROVED --- SIMPLIFIED SCOPE\]

### 15.1 Purpose

A helper for understanding a small point in the recorded lesson
currently being watched. Not a general chatbot, replacement instructor,
long-form tutor or course creator.

### 15.2 Availability/context

-   Show inside eligible recorded lesson only; never show/activate
    during live.
-   Primary/restricted source is curriculum PDF attached to current
    lesson/session.
-   Do not search other courses or lessons for current question.
-   Do not use private teacher notes, unpublished content, answer keys
    or other students' data.
-   Verify active course/lesson entitlement on every request.
-   Response language follows learner locale/request and is
    age-appropriate.

### 15.3 Student interaction

1.  Student pauses/continues recorded lesson and opens assistant.
2.  Student asks about a term, phrase, example or point.
3.  AI returns a very short simple clarification (normally one short
    paragraph; no full lesson).
4.  Student selects **"I understood"** or **"I still don't
    understand."**
5.  Understood records feedback and closes prompt.
6.  Still not understood creates help request linked to student, course,
    lesson, instructor, question, AI response and video timestamp where
    available.

### 15.4 Response rules

Use current lesson PDF as authoritative source. Explain only the point
asked, in simple age-appropriate wording. Do not produce long summaries,
full lessons, study plans, broad practice programs, voice simulations or
talent assessments. Do not claim PDF says something it does not. If PDF
lacks information, say briefly that lesson material does not clarify it
and offer human help. No formal grading, instructor, finance or admin
actions. Provider/model is OPEN.

### 15.5 Human escalation/in-platform communication

-   Assign unresolved request to instructor responsible for
    course/lesson.
-   Instructor receives immediate in-app notification; email may be
    additional.
-   Admin panel shows same request.
-   Request thread is the required communication channel; student and
    instructor exchange replies inside it. No unrestricted
    direct-message/chat system is required.
-   States: New, In Progress, Instructor Replied, Waiting for Student,
    Resolved, Closed.
-   Store timestamps/actors; notify student on instructor reply.
-   Store lesson/video timestamp for instructor context.
-   Parent visibility follows guardian policy; do not expose child's
    entire AI history by default.

### 15.6 AI safety/operations

Authenticate and check entitlement every request. Retrieve only approved
current lesson PDF; reauthorize before prompt construction. Treat PDF
and learner prompt as untrusted. No write tools for
enrollment/payment/refund/grading/publishing/progress/certificates.
Apply quotas/timeouts/cost tracking. AI outage must not block
video/PDF/learning; show help-request fallback. Confirm minor
privacy/consent, retention, provider processing and data-region policy
before enabling for minors.

## 16. Instructor help-request workflow

This is not general ticketing/chat. It is educational follow-up linked
to a recorded lesson. Student creates request from AI or
unsupported-source fallback. Assign to responsible instructor; if none,
route to admin. Instructor sees course/student/lesson/question/AI
answer/timestamp/status. Admin sees same request and assignment/status
history. Instructor replies in thread; student receives notification and
can respond. Instructor/admin marks resolved/closed; student can confirm
understanding. No external WhatsApp/phone workflow is required.

## 17. Commerce, plans, bundles and access

### 17.1 Sales models \[APPROVED\]

Support individual course purchase, bundles, monthly plans, annual
plans, free access and authorized manual grants/offline payment. Course
access modes: free, individual purchase, subscription-only, or both.
Plan inclusion/access duration is commercially configurable.

### 17.2 Unified entitlement engine

All valid sources create/update one authoritative entitlement:
individual purchase, bundle, active subscription, free enrollment,
approved manual grant, approved offline/Instapay payment or migrated
legacy access. Record source, student, course, start/end, status,
issuer/order/plan/grant and audit provenance. Player, video, PDFs,
attachments, live links and AI all use this service.

### 17.3 Checkout/payment

-   Young Go receives all student payments centrally; no gateway split
    to instructors.
-   Egypt gateway is **Paymob** \[APPROVED, D-025\], implemented behind the `PaymentGateway` adapter so a second gateway can be added later. See YG-GAP-PAY-*.
-   Preserve current Instapay/manual operation where used:
    order/reference/proof, finance review, approve/reject, then
    entitlement. No automatic Instapay API assumed.
-   Server calculates price/discount/currency/totals; never trust
    browser totals.
-   Activation requires verified gateway callback or authorized manual
    approval; browser redirect alone is not proof.
-   Handle pending, paid, failed, cancelled, refunded and disputed
    states.
-   Idempotency prevents duplicate callbacks/entitlements.
-   Store gateway IDs/safe metadata; never store PAN/CVV.
-   Coupons support percent/fixed, date, usage cap and eligible
    products.
-   Refunds preserve original transaction history and update entitlement
    only under approved policy.

### 17.4 Instructor revenue and settlement \[APPROVED SIMPLE MODEL\]

Young Go collects first and settles later. No watch-time, engagement
minutes or lesson completion calculation.

  -----------------------------------------------------------------------
  Sale type               Calculation direction   Configurable values
  ----------------------- ----------------------- -----------------------
  Individual course       Percentage commission   Rate per
                          on commissionable       instructor/course
                          course sale amount      

  Bundle                  Admin allocates bundle  Course allocation and
                          value across included   commission rate
                          courses; instructor     
                          commission applies to   
                          allocated share         

  Monthly/annual          Fixed amount per        Fixed amount per
  subscription            student enrolled in     student/course/plan
                          each included           period
                          instructor course for   
                          the relevant            
                          subscription period     
  -----------------------------------------------------------------------

Subscription amount is earned once per student-course-subscription
period, not per lesson, video view, click or repeated access. Prevent
duplicate ledger entries. Exact rates/amounts are not set here.

Required: protected instructor financial profile; configurable
commission rules; ledger linked to source sale/period;
accrued/pending/approved/held/paid/reversed states; instructor
statement; finance review and manual settlement recording; immutable
financial history with adjustment/reversal entries; reports by
instructor/course/sale type/period/status.

**OPEN accounting details:** commissionable base after
discount/tax/gateway fees/refunds; bundle allocation defaults;
subscription proration/cancellation; settlement frequency;
withholding/tax documents. Owner/accountant approval required before
live settlements.

### 17.5 Country activation

Egypt is the only active sales market at initial launch. Saudi
configuration is prepared but disabled until Saudi entity, payment
account, invoice/tax rules and owner activation approval are documented.

## 18. Manual grants, enrollment and registrations

Retain dedicated auditable Manual Grants area: select learner/course;
grant type and duration (lifetime or fixed date range); start/end;
reason; granted by/date/status/revocation; filters and CSV. Unlike the
old system's displayed notice that manual grants do not create
enrollment/progress records, the new system integrates them with unified
entitlement. A grant remains distinguishable by source and does not
fabricate a payment/order. It appears consistently in course access
summaries and learner access. Registration management supports
user/course/status/date/source filters, export and active/expired view.
Revocation is explicit/audited.

## 19. Admin and instructor operations

### 19.1 SKIT \[APPROVED\]

All admin, finance, content and instructor panels use SKIT: collapsible
responsive role-aware sidebar; topbar/account context; real KPIs/charts;
searchable/filterable/sortable/paginated tables; permission-scoped
row/bulk actions and exports; accessible forms/validation;
loading/empty/error/success states; light/dark; verified Arabic
RTL/English LTR including mixed text, table order, numbers, icons and
breadcrumbs. Student/public screens use a dedicated learning UI, not
admin dashboard template.

### 19.2 Legacy sidebar/module inventory \[LEGACY-CARRY\]

Navigation must account for: Dashboard; Young Go Home Page; Custom
Pages; Subscription Plans; Manual Grants; Role Assignments; Instapay
Payments; Coupon Checkout Usage; Young Go Settings; Course Management;
Add New Course; Categories; Coupons; Enrollments/Registrations; Reports;
Users; Messages/announcements and help-request inbox; Newsletter;
Contact enquiries; Blog; Profile. Labels/grouping may improve, but no
useful capability is dropped without owner approval.

## 20. Notifications and communications

Event-based in-app/email notifications: account/verification/reset;
access granted/expiring/revoked; payment
pending/approved/failed/refunded and Instapay review; live
scheduled/reminder/rescheduled/cancelled; assignment
published/due/submitted/graded/revision; quiz result; certificate
issued/revoked; course announcement; AI help request to
instructor/admin; instructor reply; instructor settlement
statement/status. Localized, recipient-scoped and deduplicated.
SMS/WhatsApp later via adapter; no vendor assumed.

## 21. Reporting and exports

-   Platform overview: users, students, parents, instructors, courses,
    enrollments, completion, live sessions, certificates.
-   Course performance: enrollments, progress, completion,
    ratings/reviews where enabled, assessment outcomes and revenue.
-   Student progress: enrolled courses, completed lessons, last
    activity, attendance, results, assignments and certificates.
-   Instructor operations: assigned courses, learners, upcoming
    sessions, grading/help backlog and performance.
-   Finance: orders, payments, Instapay/manual approvals, coupons,
    refunds, net collections and discrepancies.
-   Instructor earnings: commission ledger, bundle allocation,
    subscription fixed amounts and settlements.
-   AI/help: requests, unresolved count, response times, provider
    usage/cost and failures; no full private chats in aggregate reports.
-   Content: course status, missing thumbnails/PDFs/media,
    section/lesson counts and migration exceptions.

All reports honor actor permissions, filters and date range. Totals
reconcile to transactions/ledger; retries do not inflate counts.

## 22. Audit, settings and integrations

Audit role/permission changes, guardian links, course ownership,
publication, pricing, commissions, manual grants, payment decisions,
refunds, grading, certificate issue/revoke, AI policy/configuration and
help assignment/resolution. Settings include brand, contact, locales,
market activation, course defaults, notification and certificate
templates, integrations. Secrets stored securely outside source and
logs. Provider adapters for payment, video/media, live,
email/notifications and AI.

## 23. Data and business invariants

1.  Access derives from unified entitlement service.
2.  Student/parent/instructor access is object-scoped and enforced
    server-side.
3.  Pending/failed payments never grant paid access.
4.  One verified payment/order fulfills entitlements only once.
5.  Manual grants are auditable and do not fabricate payment.
6.  Instructor revenue comes from internal ledger; gateway does not
    split pay.
7.  Subscription instructor earnings are fixed per eligible
    student-course-period, not usage-based.
8.  AI uses only current eligible recorded lesson's approved curriculum
    PDF.
9.  AI is disabled during live sessions.
10. Unresolved AI help goes to assigned instructor and admin copy.
11. Help communication stays inside platform and linked to lesson.
12. Financial, grade, submission, certificate and audit history is not
    silently deleted.
13. Private media/files are entitlement-protected.
14. Store canonical UTC timestamps; display configured timezone.
15. Saudi market remains disabled until approved.
16. Existing production remains unchanged until approved cutover.

## 24. Non-functional requirements

  ---------------------------------------------------------------------
  Area                               Requirement
  ---------------------------------- ----------------------------------
  Security                           HTTPS, secure auth, RBAC/object
                                     policies, CSRF/XSS/SQLi
                                     protection, rate limits, safe
                                     uploads, signed media access,
                                     audit

  Privacy                            Data minimization, guardian
                                     consent controls, private AI
                                     threads, retention, restricted
                                     instructor/student communication

  Performance                        Pagination, indexed queries,
                                     caching, async jobs; benchmark
                                     after server/media sizing

  Availability                       Monitoring, queue health, backup
                                     and rollback; numeric SLA OPEN

  Media                              Video delivery scalable
                                     independently of app server;
                                     provider-neutral adapter

  Localization                       Complete AR RTL / EN LTR; course
                                     language independent of UI locale

  Accessibility                      Keyboard-operable forms, readable
                                     typography, contrast and clear
                                     errors

  Compatibility                      Current major desktop/mobile
                                     browsers; final matrix agreed
                                     later

  Maintainability                    Modular monolith, migrations,
                                     domain services, versioned APIs,
                                     documented integrations

  Recovery                           Database/private files backed up;
                                     restore path verified before
                                     cutover

  Observability                      Structured logs, failed jobs,
                                     payment reconciliation, AI usage,
                                     uptime and storage alerts
  ---------------------------------------------------------------------

## 25. Migration and cutover requirements

Every existing course must be inventoried. Preserve course metadata,
translations, categories, level, age suitability where available,
price/access, thumbnail, preview, FAQs, requirements, outcomes,
SEO/status; curriculum hierarchy/order; lesson type/duration/video
reference/description/resources/PDFs;
quizzes/questions/options/keys/settings and history where in scope;
assignments/submissions/feedback; live/replay references; certificates
where available/authorized. Preserve old URLs or redirects where
feasible. Assess learner accounts, enrollments, progress, payments,
grants and certificates for continuity; exact personal/financial history
scope requires inventory and owner sign-off. Import to staging,
reconcile, then final delta at cutover. Keep old platform available
until acceptance, backup and rollback sign-off.

## 26. Launch acceptance gates

1.  Visitor discovers published course and registers/logs in.
2.  Parent accesses only linked children and permitted records.
3.  Admin/instructor creates, previews, approves and publishes complete
    bilingual course.
4.  Course supports ordered sections, recorded lessons, PDFs, quizzes,
    assignments and live sessions.
5.  Every migrated legacy course reconciles to source inventory.
6.  Entitled student opens course, resumes lesson, downloads PDF and
    sees accurate progress.
7.  Non-entitled user cannot access protected media/PDF/live/AI.
8.  AI works only on recorded lesson PDFs, gives concise grounded
    explanations and is unavailable during live.
9.  "Still not understood" creates instructor request and admin copy;
    replies occur in-platform.
10. Individual, bundle and subscription access use one entitlement
    service.
11. Egypt checkout follows approved legal/gateway setup; Saudi checkout
    is disabled.
12. Instructor earnings follow approved simple rules and reconcile to
    central collections.
13. SKIT and student UI pass responsive AR/EN review.
14. Critical authorization, payment idempotency, file access, migration,
    backup/restore and rollback checks pass.
15. Training, support ownership, operational docs and handoff are
    complete.

## 27. Open decisions and dependencies

Do not invent: exact live hostname/DNS; legacy code/database/storage
access; new repo/hosting/runtime/database; final Laravel/Filament
versions; video provider/volume; Paymob account configuration and enabled methods; Instapay
process; ETA e-receipt obligation (O-023); tax/invoice/legal seller; commission percentages, bundle
allocation, subscription fixed amounts and settlement schedule; Saudi
entity/gateway/tax; AI provider/terms/region/retention/cost/minor
consent; age-band values and guardian verification; parent visibility
into full AI chats (default no); family plans; exact
user/enrollment/progress/payment history migration; certificate
design/legal issuer; completion defaults; delivery
dates/estimates/staffing.

## 28. Source references

-   `Yango_Go_PRD.pdf` --- June 2026, 21 pages.
-   `Yango_Go_Full_Technical_PRD_Implementation_Specification.pdf` --- 4
    June 2026, 16 pages.
-   November 2026 PRD v2, DMC, AI spec, architecture/data/API, decisions
    and release plan.
-   Owner-supplied LMS screenshots (15 November 2026): course wizard,
    curriculum manager, lesson/quiz forms, manual grants, course list,
    admin navigation and student player.
-   Owner decisions in this conversation (15 November 2026).

## 29. Gap-closure requirements \[v3.1\]

These requirements close the gaps found in the v3.0 review. Labels: **[APPROVED]** owner decision; **[RECOMMENDED-DEFAULT]** reversible engineering default that developers implement now (per Decision Register §D) and the owner may change later without redesign; **[OPEN]** must not be guessed.

### 29.1 Family accounts and child login

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-FAM-001 | Parent can create a child learner account from the parent dashboard. Child email is optional; child gets a unique username and password. | Child logs in with username/password without an email; parent-child link is created as `verified` because the parent created it. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-FAM-002 | Existing learner accounts can be linked to a parent through a one-time link code (valid 48 h) confirmed from the learner account. | Link only becomes active after confirmation; codes are single-use and audited. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-FAM-003 | Store birth **year and month** only (data minimization), used to compute age band and course suitability warnings. | No full birth date stored; age band computed server-side. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-FAM-004 | Parent can switch into a child's context after re-entering the parent password (or valid recent auth); every action in child context records the acting parent. | Audit shows `actor=parent, subject=child`. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-FAM-005 | Purchases for a child are paid by the parent account; the entitlement is issued to the child. | Order `payer_id=parent`, entitlement `learner_id=child`. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-FAM-006 | Guardian verification method beyond the above, and consent wording. | Remains O-015. Minors' AI stays disabled until approved. | OPEN | P0 gate |

### 29.2 Names and identity

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-ID-001 | Users store `name_ar`, `name_en` (at least one required) and `display_name`. Certificates use `certificate_name_ar` / `certificate_name_en`, editable by the learner until the first certificate is issued, then by admin only (audited). | Certificate renders the correct script per template language. | RECOMMENDED-DEFAULT | P0 |

### 29.3 Cohorts, capacity and waitlist

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-COH-001 | A live or hybrid course can have cohorts (groups): name, start/end date, capacity, instructor(s), schedule, enrollment open/close dates, status. | Live sessions can target a cohort; only cohort members see them. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-COH-002 | Capacity is enforced at checkout and manual grant; when full, learners can join a waitlist; admin can promote from waitlist. | No over-booking under concurrent checkout (row lock/atomic counter). | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-COH-003 | Recorded-only courses do not require cohorts. | Course without cohorts behaves as v3.0. | RECOMMENDED-DEFAULT | P0 |

### 29.4 Content protection and account sharing

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-VID-001 | Every media source has a **protection tier**: **T1 Protected** (private object storage or private streaming provider with signed, expiring playback, e.g. HLS), **T2 Restricted** (private Vimeo with domain restriction), **T3 Open** (YouTube unlisted, Google Drive, public URLs). | Tier is stored and shown in the curriculum manager. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-VID-002 | Paid lessons must use T1 or T2. T3 is allowed for free/preview lessons, or as a documented migration exception. | Publishing a paid lesson with a T3 source is blocked unless an admin records an exception reason. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-VID-003 | Dynamic watermark overlay on T1/T2 playback showing a masked learner identifier (e.g. `ID-48213`), moving position periodically. | Watermark visible on desktop/mobile and in fullscreen. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-SEC-001 | Concurrent session limit per learner (default 2 active sessions) and registered device limit (default 3). Oldest session is signed out on overflow; admin can reset devices. | Limits configurable; events audited. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-SEC-002 | Suspicious sharing signal: many distinct IPs/devices in a short window flags the account for admin review (no automatic ban). | Flag appears in admin; no learner-facing accusation. | RECOMMENDED-DEFAULT | P2 |
| YG-GAP-VID-004 | Video provider for T1 remains OPEN (O-005); implement adapter with a local/S3-compatible signed-URL driver first. | Switching provider requires no course-engine change. | OPEN provider | P0 |

### 29.5 Course changes after publication

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-CHG-001 | Changes by an independent instructor to a published course create a **revision** (pending review). The published version stays live until the revision is approved. | Students never see unapproved changes; reviewer sees a diff summary. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-CHG-002 | Academy-owned course changes by roles with `course.publish` apply directly and are audited. | Audit records before/after. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-CHG-003 | Review decisions require a reason on rejection; instructor is notified. | Rejection reason visible to instructor. | RECOMMENDED-DEFAULT | P0 |

### 29.6 Payments (Paymob) and receipts

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-PAY-001 | Paymob is the Egypt gateway. Integrate Paymob's current recommended checkout API (verify against official Paymob docs at implementation), starting with cards and mobile wallets; other methods (e.g. kiosk/cash reference, installments) are enabled per Paymob account configuration. | Method list comes from configuration, not code. | APPROVED (D-025) | P0 |
| YG-GAP-PAY-002 | Payment is fulfilled only from a server-verified Paymob callback/webhook (HMAC or the signature mechanism Paymob documents), with amount, currency and merchant order reference matched to the server order. Browser redirect only shows status. | Tampered or replayed callbacks are rejected and logged; duplicate callbacks are idempotent. | APPROVED invariant | P0 |
| YG-GAP-PAY-003 | Reconciliation job compares local payments with Paymob transaction records daily and lists mismatches for finance. | Mismatch report available to Finance role. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-PAY-004 | Instapay/manual proof captures: payer name, sender account/phone (masked in lists), transfer reference, amount, date, screenshot upload. Duplicate reference numbers are blocked; amount mismatch is highlighted. Review SLA indicator for finance. | Duplicate reference cannot be approved twice. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-PAY-005 | Receipt/invoice data model holds the fields required for Egyptian e-receipt/e-invoice submission (seller tax data, buyer data, items, taxes, totals, document UUID/status) behind an `EReceiptProvider` adapter. Whether submission is mandatory and whether Paymob or another provider performs it is O-023. | Adapter defaults to `disabled`; no fake submission. | OPEN (O-023) | P1 |

### 29.7 Subscriptions renewal

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-SUB-001 | Default renewal is **manual**: reminders 7, 3 and 1 day before expiry with a renewal checkout link; 3-day grace period; access to plan-only courses ends after grace. | Expired plan revokes plan-sourced entitlements only; individually purchased courses unaffected. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-SUB-002 | Learner progress, submissions and certificates are retained after expiry and restored on renewal. | Renewal shows previous progress. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-SUB-003 | Automatic renewal with saved cards is enabled only if supported by the Paymob account and approved by the owner. | Feature flag `subscriptions.auto_renew=false` by default. | OPEN | P2 |
| YG-GAP-SUB-004 | Mid-period cancellation keeps access until period end; no proration by default (O-011 may change). | Cancellation recorded; ledger unaffected for paid period. | RECOMMENDED-DEFAULT | P1 |

### 29.8 Independent instructor onboarding

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-INS-001 | Public "Teach with Young Go" application: profile, discipline, samples/portfolio links, ID document upload (private). | Applications listed for admin review with status. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-INS-002 | Instructor accepts a versioned instructor agreement (text, version, timestamp, IP). New versions require re-acceptance before creating new courses. | Acceptance stored and auditable. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-INS-003 | Encrypted payout profile (bank/wallet details) required before the first settlement; visible only to Finance. | Encrypted at rest; masked in UI. | RECOMMENDED-DEFAULT | P1 |

### 29.9 Engagement features carried from June 2026 PRD

| ID | Requirement | Decision | Label | Pri |
|---|---|---|---|---|
| YG-GAP-ENG-001 | Wishlist/favorites on courses. | Carried forward. | LEGACY-CARRY | P1 |
| YG-GAP-ENG-002 | Ratings and reviews by entitled learners with admin moderation (pending → published/hidden). Child reviews show first name + initial only. | Carried forward with moderation. | LEGACY-CARRY | P1 |
| YG-GAP-ENG-003 | Open course discussions/Q&A between students. | **Not built.** Superseded by lesson help requests for child safety. Owner may reopen. | RECOMMENDED-DEFAULT | — |

### 29.10 Search, analytics, notifications

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-SRCH-001 | Arabic-aware search: normalized search columns (أ/إ/آ→ا, ة→ه, ى→ي, remove diacritics and tatweel; lowercase Latin) for courses, categories, instructors and blog. | Searching "اداء" finds "أداء"; "مسرحيه" finds "مسرحية". | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-MKT-001 | Admin-managed GA4 and Meta Pixel IDs, loaded only after cookie consent; never loaded inside the learning player, child dashboards or AI panel. | Consent banner AR/EN; no tags without consent. | LEGACY-CARRY | P1 |
| YG-GAP-NTF-001 | WhatsApp/SMS channel adapter is built in P9 behind `NotificationChannel`; first use case is live-session reminders. Provider OPEN (O-021). | Channel disabled until provider configured. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-NTF-002 | Transactional email domain authenticated with SPF, DKIM and DMARC before launch. | DNS records verified in launch checklist. | RECOMMENDED-DEFAULT | P0 |

### 29.11 Privacy rights and retention

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-PRV-001 | Learner/parent can request data export and account deletion. Deletion anonymizes personal data while retaining legally required financial records and certificate verification (marked anonymized). | Request workflow with admin completion and audit. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-PRV-002 | Retention settings per data class (AI messages, help requests, logs, submissions). Defaults: AI messages 180 days, logs 90 days, others per policy. | Scheduled pruning job; values configurable. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-PRV-003 | Staging uses anonymized copies of any real learner data, especially minors. | Anonymization command exists and is required for staging refresh. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-PRV-004 | Legal review of Egyptian personal data protection law (and Saudi PDPL before Saudi activation) for minors, consent and AI processing. | Counsel sign-off recorded before minor launch. | OPEN | P0 gate |

### 29.12 Quality, accessibility and recovery

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-QA-001 | Automated test suite (Pest) covers every business invariant in Section 23, entitlement sources, payment idempotency, ledger idempotency, authorization scopes and AI guards. Runs in CI on every batch. This suite **is** the milestone's focused validation required by D-024; it does not replace final deep QA. | CI green required to mark a task done. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-QA-002 | Static analysis (Larastan) and formatting (Pint) required in CI; architecture tests enforce module boundaries (see `12_CODE_ARCHITECTURE_CONVENTIONS.md`). | CI fails on violations. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-QA-003 | Visual regression screenshots for key pages in AR/EN × mobile/desktop × light/dark (admin) per `11_DESIGN_SYSTEM.md`. | Screenshots attached to milestone review. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-A11Y-001 | Target WCAG 2.1 AA for public, student, parent and SKIT screens; automated axe checks on key pages. | No serious/critical axe violations at launch. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-DR-001 | Recovery targets: RPO ≤ 24 h, RTO ≤ 4 h (recommended until owner sets SLA). Daily DB backup + daily private media backup off-host; monthly restore drill before launch. | Restore evidence recorded. | RECOMMENDED-DEFAULT | P0 |

### 29.13 Design system and code organization

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-UI-001 | All UI uses the token-based design system in `11_DESIGN_SYSTEM.md` from the first screen; brand values are extracted from the current Young Go site in P0. | No hard-coded colors/spacing outside tokens (lint/arch check). | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-CODE-001 | Code follows `12_CODE_ARCHITECTURE_CONVENTIONS.md`: domain Actions as the single home of business logic reused by web, Livewire, Filament, API and jobs; shared UI components registered in `docs/REUSE_REGISTRY.md`. | Arch tests pass; registry updated per task. | RECOMMENDED-DEFAULT | P0 |

### 29.14 Second-review additions \[v3.2\]

| ID | Requirement | Acceptance | Label | Pri |
|---|---|---|---|---|
| YG-GAP-ID-002 | Registration records acceptance of current Terms and Privacy versions (and guardian consent version for child creation). | `consents` row with version and timestamp per acceptance. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-SEC-003 | TOTP two-factor authentication mandatory for super admin, admin and finance; optional for others; recovery codes. | Panels refuse access without 2FA for those roles. (ED-016) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-FAM-007 | Parent can reset a linked child's password; action audited. Parent email must be verified before creating children. | Reset works only for linked children; audit entry. (ED-017) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-FAM-008 | Notifications for a child without email: in-app for the child; email copies to verified guardians in their locale. | Routing tests. (ED-017) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-CNT-001 | Curriculum ordering uses one `curriculum_items` list for all item types; new items default to draft; bulk publish/unpublish. | Player order equals manager order for mixed types. (ED-002, ED-024) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-CNT-002 | Rich-text lesson content with a restricted, sanitized block set. | XSS fixtures blocked; AR/EN rendering correct. (ED-003) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-CNT-003 | Reusable content library for media/resources; referenced items cannot be deleted. | Same asset attached to two lessons; delete blocked. (ED-026) | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-LRN-001 | Course sequence mode free/sequential; sequential requires completing previous required item. | Locked item shows reason. (ED-025) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-LRN-002 | Drip: item unlock by absolute date or relative days after entitlement/cohort start; shows unlock date. | Dataset tests across layers. (ED-005, ED-025) | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-LRN-003 | Optional AR/EN subtitle tracks (VTT) per video lesson; CC toggle. | Track renders; upload validated. (ED-008) | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-LRN-004 | Player controls: play/pause, seek, playback speed (0.75–2×), captions, fullscreen, keyboard shortcuts, remembered speed. | Keyboard and mobile tests. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-LRN-005 | "View as learner" preview for staff/instructors without writing progress. | No `lesson_progress` rows created in preview. (ED-024) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-LRN-006 | `course_progress` per learner-course maintained by events and preserved across entitlement renewals. | Idempotent recalculation. (ED-004) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-INS-004 | Instructor sees learners of assigned courses with progress, last activity, quiz/assignment status. | Scope tests (assigned only). | LEGACY-CARRY (REQ-REC-007) | P0 |
| YG-GAP-CMS-001 | Public instructor profile pages (approved instructors) with their published courses. | Unapproved instructors not public. | LEGACY-CARRY | P1 |
| YG-GAP-CMS-002 | Category landing pages with own URL, SEO fields and filtered catalog. | Indexed in sitemap. | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-OPS-001 | Learner 360 record for permitted staff. | Permission-scoped. (ED-027) | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-OPS-002 | Support "view as learner": read-only, audited, banner, no writes, no AI calls. | Write attempts rejected in view-as. (ED-027) | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-OPS-003 | `app:doctor` health command and `app:demo-reset` for non-production. | Doctor fails on broken queue/storage; demo-reset refuses in production. (ED-022) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-OPS-004 | Branded AR/EN error pages (403, 404, 419, 429, 500, 503 maintenance). | Rendered in both locales. | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-OPS-005 | Feature flags for gated capabilities with approval reference. | Gated flag cannot be enabled without reference. (ED-015) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-PERF-001 | Performance budgets: LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms on mid-range mobile 4G for public/student pages. | Measured at milestone gates and P11. (ED-030) | RECOMMENDED-DEFAULT | P1 |
| YG-GAP-SEC-004 | Uploaded images re-encoded (EXIF/GPS stripped); private random file names. | Uploaded photo has no GPS metadata. (ED-031) | RECOMMENDED-DEFAULT | P0 |
| YG-GAP-UX-001 | All screens follow `15_UX_FLOWS_AND_PATTERNS.md` principles, patterns and journey acceptance. | `ux-reviewer` scores ≥ 4 each, average ≥ 4.5. (ED-023) | RECOMMENDED-DEFAULT | P0 |
