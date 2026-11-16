# YOUNG GO LMS --- DEVELOPMENT MASTER CONTEXT (DMC)

**Document ID:** YG-DMC-002\
**Version:** 2.1 --- v3.1 gap closure + agent loop\
**Date:** 16 November 2026\
**Project slug:** `young-go-lms` (proposed repository slug, not a
verified remote)\
**Purpose:** Persistent implementation contract for developers and
Codex. Read before coding and update after every delivered milestone.

------------------------------------------------------------------------

## 1. Mission and implementation outcome

Build the new Young Go LMS as a fully custom application from scratch
while preserving the existing Young Go logo, brand identity and
owner-stated domain `youngschool.com`. The existing platform remains
live until the new platform has passed acceptance and a controlled
cutover is approved.

The new application must be a complete LMS, not a prototype dashboard. A
working vertical slice must connect real data across course creation →
publication → registration/enrollment → entitled student learning →
progress. All modules integrate with the same course, identity,
entitlement and audit foundations.

**Do not use Academy LMS, EduEx, WordPress LMS or any packaged LMS as
the core.** Laravel, Filament, media providers, payment gateways and AI
APIs may be used as infrastructure/framework components, not as a
substitute for Young Go business logic.

## 2. Authoritative source hierarchy

The canonical precedence is `AGENTS.md` §2. Latest explicit owner instructions and approved
D-* decisions outrank the PRD; technical recommendations never become owner approval by repetition.
Original source files not included in the pack are historical provenance, not inspected evidence.
Log conflicts with exact citations, block only the affected decision, and continue independent work.

## 3. Locked product decisions

-   Fully custom LMS from scratch; no packaged LMS core.
-   Preserve existing Young Go name, logo, brand identity and current
    domain. Owner-stated hostname: `youngschool.com`; verify live DNS
    before cutover.
-   Existing old platform remains live during development and until
    sign-off.
-   All legacy courses and their associated course content/assets must
    be migrated.
-   Audience is all ages, with children and young people a key segment;
    age suitability is configurable per course.
-   Parent/guardian account links to one or more distinct child learner
    accounts.
-   Academy-owned courses and independent instructor courses coexist;
    independent content requires Young Go approval.
-   Recorded, live and hybrid delivery.
-   Full Arabic/English UI from first release; additional locales
    supported architecturally.
-   Egypt is active at launch; Saudi is prepared but sales/checkout
    disabled pending legal registration and activation.
-   Individual course purchases, bundles, monthly subscriptions and
    annual subscriptions are supported.
-   Young Go receives all student payments centrally; no gateway split
    payments to instructors.
-   Individual sale instructor earnings use a configurable percentage;
    bundle earnings use an admin-defined course allocation plus
    commission; subscription earnings use a fixed amount per
    student/course/subscription period, not engagement or watch-time.
-   AI is a short, lesson-specific clarification assistant for recorded
    lessons only, grounded in that lesson's curriculum PDF. It is
    disabled during live sessions.
-   Student can mark AI answer understood or still not understood.
    Unresolved requests go to responsible instructor and are copied to
    admin. Replies take place inside the request thread.
-   SKIT is mandatory for admin/instructor/operations panels. Student
    learning UI is custom and course-focused.
-   Keep Young Go separate from Young Film School / YFS.

## 4. Current factual state --- do not overstate

  ---------------------------------------------------------------------
  Item                               State at documentation handoff
  ---------------------------------- ----------------------------------
  Product direction                  Approved custom LMS

  Existing brand/logo                Owner confirmed reuse; source
                                     assets must be extracted/verified
                                     from current site

  Domain                             Owner-stated `youngschool.com`;
                                     DNS/hosting not verified

  Existing platform                  Owner confirms working legacy
                                     system and requires course
                                     migration; source access not
                                     verified in this documentation
                                     task

  Legacy course inventory            Screenshots show existing courses
                                     and detailed authoring/player
                                     functions; exact counts/assets
                                     require live/database audit

  Repository/remote                  UNKNOWN

  Branch/commit                      UNKNOWN

  New hosting/server                 UNKNOWN

  Runtime/database                   UNKNOWN; verify environment before
                                     lock

  Payment gateway                    Paymob recorded in D-025; account/API not verified

  Instapay process                   Existing admin navigation includes
                                     Instapay Payments; exact
                                     workflow/API availability requires
                                     inspection

  Video provider                     Multiple legacy source types
                                     shown; production
                                     account/credentials not verified

  Live provider                      Legacy PRD names Zoom/Meet;
                                     screenshot shows BigBlueButton
                                     area; actual configuration must be
                                     inspected

  AI provider                        NOT SELECTED

  Saudi legal entity                 Registration planned, not
                                     completed

  New LMS deployment                 NOT VERIFIED; no application code
                                     is included in this documentation
                                     package
  ---------------------------------------------------------------------

Never claim a repository, server, backup, deployment or integration
exists until verified from actual access and evidence.

## 5. Recommended technical architecture (not locked until environment audit)

### 5.1 Application shape

Use a **modular monolith** with clear domain boundaries, one
authoritative relational database, queued background work, cache and
provider adapters. Do not start with microservices. Keep modules
independently testable and extractable later only when scale or
operational need justifies it.

### 5.2 Technology candidates

  -------------------------------------------------------------------------
  Layer                Candidate                       Decision status
  -------------------- ------------------------------- --------------------
  Backend              Laravel / compatible PHP           Recommended; verify
                                                       target runtime and
                                                       package
                                                       compatibility

  Admin/instructor UI  Filament customized to SKIT   Recommended;
                                                       Filament is a UI
                                                       toolkit, not LMS
                                                       core

  Student/public UI    Custom responsive               R-026 default
                       Blade/Livewire + Alpine         R-026 default; pin versions
                       and Tailwind                   after compatibility review

  Database             PostgreSQL or MySQL             OPEN; inspect server
                                                       and legacy data

  Cache/queues         Redis where available           Recommended

  Storage              Private local/object storage    Provider OPEN
                       adapter; S3-compatible where    
                       appropriate                     

  Video                Provider-neutral delivery       Provider OPEN
                       abstraction; signed/authorized  
                       playback                        

  AI                   `AiProviderInterface`/gateway   Provider/model OPEN
                       adapter                         

  Live                 Manual meeting URL first;       Provider credentials
                       adapter for                     OPEN
                       Zoom/Meet/BigBlueButton         
  -------------------------------------------------------------------------

Do not upgrade/select runtime solely from this document. Verify
compatibility with target host and pinned packages before
implementation.

### 5.3 Domain modules

`Identity`, `Guardians`, `Roles`, `Catalog`, `CMS`, `Courses`,
`Curriculum`, `Media`, `Learning`, `Progress`, `Live`, `Assessments`,
`Assignments`, `Certificates`, `Enrollment`, `Entitlements`, `Commerce`,
`Subscriptions`, `Bundles`, `InstructorFinance`, `Notifications`,
`HelpRequests`, `AI`, `Reporting`, `Audit`, `Settings`, `Migration`,
`Integrations`.

### 5.4 Integration contracts

-   `VideoDeliveryProvider`: validate/register asset, authorized
    playback, status, metadata and optional transcript.
-   `PaymentGateway`: create checkout, verify webhook, query payment,
    refund if supported. Fulfill only after verified event.
-   `MeetingProvider`: manual URL adapter first; automated creation/sync
    later if selected.
-   `NotificationChannel`: in-app/email first; SMS/WhatsApp later.
-   `AiProviderInterface`: concise completion and usage metadata. Keep optional retrieval/indexing
    separate; introduce it only when PDF limits justify it (ED-033..037).
    Never couple one vendor to lesson controllers.
-   Framework storage facilities: private files and authorized delivery; no duplicate storage wrapper without a real need.

## 6. Core architecture rules

### 6.1 Unified entitlement service

Implement one authoritative service for course access. It resolves
purchase, bundle, subscription, free enrollment, manual grant,
offline/Instapay approval and migrated legacy access. Every protected
route/resource calls this service: lesson view, video playback, PDF,
attachment, live join link, quiz, assignment and AI retrieval. Never let
each module invent its own access rules.

### 6.2 Course ownership and instructor permissions

Course source is academy-owned or independent-instructor.
Instructor-course assignment is explicit and scoped. Independent
instructor content stays draft/pending until approved. Commission
configuration is separate from ownership. No instructor may view/edit
another instructor's unrelated course or students.

### 6.3 Parent-child relationships

Store guardian-child relationship as an explicit, verifiable
relationship, not a role flag alone. A parent can link multiple child
accounts. Every parent read/action checks relationship and permission.
Never accept client-supplied child ID without authorization.

### 6.4 AI scope

AI retrieves only the approved curriculum PDF attached to the current
recorded lesson. No global RAG, other course, transcript, general chat,
live context or hidden answer key. Query-time entitlement and lesson
checks are mandatory before retrieval and response. Assistant is
read-only. See `06_AI_LESSON_ASSISTANT_SPEC.md`.

### 6.5 Instructor earnings

Payments are collected centrally by Young Go. Generate internal ledger
entries from verified commercial events. Do not implement direct payment
split. Subscription earnings are fixed per eligible
student-course-period and idempotent. Exact commission values and
accounting basis remain configurable/open.

### 6.6 Market activation

Egypt is active. Saudi is a disabled market profile until legal entity,
gateway, invoice/tax setup and owner approval are recorded. Do not
expose Saudi checkout accidentally through a currency switch.

## 7. Proposed repository organization

``` text
young-go-lms/
├── app/
│   ├── Domain/{Identity,Guardians,Catalog,Courses,Curriculum,Media,Learning,Live,
│   │ Assessments,Assignments,Certificates,Entitlements,Commerce,InstructorFinance,
│   │ HelpRequests,AI,Notifications,Reporting,Audit,Settings,Migration}/
│   ├── Filament/                 # SKIT resources/pages/actions
│   ├── Http/{Controllers,Requests,Resources,Middleware}/
│   ├── Policies/
│   ├── Jobs/                     # media, notifications, AI indexing, exports
│   └── Providers/
├── config/{learning.php,media.php,ai.php,commerce.php,markets.php}
├── database/{migrations,seeders,factories}
├── resources/{views,js,css}/
├── lang/{ar,en}/
├── routes/{web.php,api.php}
├── storage/app/private/
├── docs/                         # delivery pack and continuity files
└── README.md
```

This is a starting structure, not a reason to create empty abstractions.
Build a real working slice with minimal necessary layers.

## 8. SKIT UI contract

All admin/instructor/finance/content screens must have responsive
role-aware collapsible sidebar; topbar/account context; real data
KPIs/charts; search/filter/sort/pagination; permission-scoped
actions/exports; accessible forms and validation;
loading/empty/error/success states; light/dark; verified Arabic RTL and
English LTR including mixed text, tables, numbers, icons and
breadcrumbs. Do not ship unstyled default CRUD. Do not force SKIT onto
the student player; student UI is a custom learning experience.

## 9. Security, privacy and data ownership

-   Enforce authentication and object-level authorization server-side.
-   Student sees own records; instructor sees assigned course data;
    parent sees linked children only; finance/support see granted
    scopes.
-   Verify payment callbacks, amounts, order references and idempotency
    before fulfillment.
-   Never store card PAN/CVV.
-   Private uploads are validated, stored outside public webroot and
    delivered through authorization.
-   Use short-lived signed URLs where supported; revocation blocks
    future access.
-   Never log passwords, secrets, raw payment credentials or unnecessary
    full AI messages.
-   AI retrieval uses lesson and entitlement gates; PDF content is
    untrusted and cannot override system rules.
-   Confirm minors' consent, guardian verification, retention and AI
    provider processing before minor launch.
-   Preserve financial, grade, submission, certificate and audit history
    through reversals/versioning, not destructive edits.

## 10. Development and Codex execution rules

1.  **Scope-first delivery:** a documentation review delivers reviewed instructions.
    Only an authorized implementation task creates application code; then deliver real vertical slices, not speculative scaffolding.
2.  Use one concise English Codex instruction per coherent
    implementation batch; combine related work into one clear task.
3.  Before editing, inspect actual repository and CodeGraph if
    available. If CodeGraph is unavailable, continue with fast
    repository inspection; do not block work.
4.  Never assume repository, branch, host, SSH user, credentials or
    auto-approve access. Use only access explicitly provided/authorized.
5.  Keep legacy production untouched during development. Use separate
    development/staging environment.
6.  Prefer additive, reversible migrations. Preserve data. Avoid
    unrelated refactors.
7.  Each milestone delivers a functional slice, not just empty tables or
    placeholder screens.
8.  Use one focused validation pass per coherent batch. Do not repeat
    equivalent tests without a new reason.
9.  Do not skip critical security, access-control or payment checks
    before exposure.
10. Reserve final comprehensive regression, migration reconciliation,
    backup/restore and deep QA for the final phase after planned
    functionality is delivered.
11. After each milestone, update `PROJECT_STATE.md`, `HANDOFF.md`,
    `CHANGELOG.md`, decision register if changed, and milestone status.
12. Never claim deployment/verification without evidence: actual commit,
    deployment output, live endpoint or verified backup/restore result.
13. Keep Codex instructions concise, unambiguous English and minimize
    token waste.
14. Do not start or merge Young Film School/YFS work into this
    repository.

## 10A. Agent loop execution (v3.1)

Implementation runs through the orchestrator loop defined in `agents/ORCHESTRATOR.md`:

- One orchestrator agent (host-supported model appropriate to the risk) owns planning, task selection, review and commits.
- Implementation work is delegated to actual host subagents with scoped briefs (`.claude/agents/` supplies Claude role templates). The orchestrator reviews every subagent diff against `agents/REVIEW_PROTOCOL.md` and runs `agents/VERIFICATION_GATES.md` before marking a task done.
- Tasks come only from `agents/tasks/*.md`; status only in `agents/PROGRESS.md`.
- Shared code must be checked in `docs/REUSE_REGISTRY.md` before writing new components; new shared pieces are registered in the same task.
- **Testing clarification (R-024):** the automated invariant/feature test suite running in CI is the "one focused validation" of D-024. It does not repeat manual regression and does not replace the final deep QA phase. Critical security, payment and authorization tests are never skipped.
- UI follows `11_DESIGN_SYSTEM.md` and `15_UX_FLOWS_AND_PATTERNS.md`; code follows `12_CODE_ARCHITECTURE_CONVENTIONS.md`; technical choices follow `13_ENGINEERING_DECISIONS.md`.
- v3.2: acceptance tests are written first; every task passes an independent verification wave (`prd-auditor`, `qa-verifier`, `ux-reviewer`, `reviewer` for high risk) and gate G10 (requirement coverage against `docs/TRACEABILITY_MATRIX.md`).

## 11. Delivery workflow

For every milestone: read relevant PRD IDs and acceptance criteria;
inspect implementation/data model; implement end-to-end
UI/backend/database/authorization; update docs/migrations; perform one
focused validation; report exact behavior/evidence/blockers/next
milestone; update continuity files. A milestone is not complete merely
because a page renders. User action must persist valid data and produce
the correct authorized outcome.

## 12. Environments and release safety

-   Separate development, staging and production where practical; do not
    develop directly on production.
-   Keep secrets in environment/secret storage, not source.
-   Document migrations and rollback paths.
-   Verify scheduler, queues, storage permissions, mail, provider
    callbacks and monitoring before go-live.
-   Maintain database/private-file backups outside application host
    where possible.
-   Do not switch DNS until new site, migrated data, payment/access
    rules, backups and rollback are approved.
-   Old platform remains available until cutover; keep rollback route
    during agreed stabilization window.

## 13. Continuity record required after each milestone

``` text
Project: Young Go LMS
Phase/Milestone: <ID and title>
Repository: <verified URL or UNKNOWN>
Branch/Commit: <actual values or UNKNOWN>
Environment/Host: <verified or UNKNOWN>
Domain: youngschool.com (DNS/cutover state must be verified)
Implemented: <visible end-to-end behavior>
Deployed: <yes/no + evidence>
Focused validation: <one concise result>
Data/migrations: <actual result>
Backup/rollback: <verified reference or UNKNOWN>
Open decisions/blockers: <only real blockers>
Next milestone: <single next action>
```

## 14. First implementation target

First meaningful slice: working custom application/authentication; SKIT
admin shell with real roles/permissions; brand-preserving public shell
and DB-backed catalog; course creation/publishing foundation; real
entitled student course/player route; one authoritative entitlement
check; continuity docs with verified facts.

Do not build decorative dashboards before course → enrollment → learning
works. Integrate simplified AI only after lesson PDF storage and
entitlement checks exist; never bolt it on as a public chatbot.
