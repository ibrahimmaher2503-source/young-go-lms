# YOUNG GO LMS --- ARCHITECTURE, DATA MODEL & API CONTRACT

**Version:** 1.3 (consistency and failure-boundary review) · **Date:** 16 November 2026\
**Status:** Planned architecture, not implemented or runtime-verified. Technical
defaults remain subordinate to owner decisions and OPEN policy gates. Final
stack/database require the environment audit (O-004).

## 1. Logical architecture

``` mermaid
flowchart TB
  PUBLIC[Young Go Public Website / Catalog] --> APP[Custom LMS Application]
  STUDENT[Student Learning Portal] --> APP
  PARENT[Parent / Guardian Portal] --> APP
  INSTRUCTOR[Instructor SKIT Panel] --> APP
  ADMIN[Admin / Finance SKIT Panels] --> APP
  APP --> ID[Identity / RBAC / Guardian Links]
  APP --> COURSE[Course Builder / Curriculum]
  APP --> ENT[Unified Entitlement Service]
  APP --> LEARN[Learning / Progress / Assessments]
  APP --> COM[Commerce / Subscription / Instructor Ledger]
  APP --> HELP[AI Help Requests / Notifications]
  APP --> DB[(Relational Database)]
  APP --> CACHE[(Cache / Queue / Jobs)]
  APP --> STORAGE[Private Files / PDFs / Object Storage]
  APP --> VIDEO[Video Delivery Adapter]
  APP --> PAY[Payment Adapter — Egypt Active]
  APP --> LIVE[Manual Live Link / Future Provider API]
  APP --> AI[AI Gateway]
  AI --> PDF[Current Lesson PDF Retrieval Only]
  APP --> EMAIL[Email / In-app Notifications]
```

Use a modular monolith for the initial product. Avoid microservices
until an observed scaling/team boundary justifies extraction.

## 2. Recommended stack candidates

-   Backend: Laravel/PHP candidate versions in R-001; verify their actual
    compatibility together from official versioned documentation and the host
    in T-P0-02 before pinning. This pack does not establish a supported pair.
-   Admin/instructor UI: Filament 5 customized to SKIT; Filament is not
    an LMS core.
-   Student/public UI: Blade + Livewire + Alpine + Tailwind is the reversible
    default R-026, subject to the same compatibility audit.
-   Database: MySQL or PostgreSQL after legacy schema and host
    inspection; PostgreSQL/pgvector is a possible AI option, not a lock.
-   Queue/cache: Redis where available.
-   Files: private filesystem or S3-compatible storage adapter.
-   Video: provider-neutral asset/playback adapter.
-   API: versioned internal JSON API where useful for frontend/future
    mobile.

## 3. Domain modules

1.  Identity and profiles.
2.  Roles and permissions.
3.  Guardian-child relationships.
4.  Public catalog and CMS.
5.  Categories and age suitability.
6.  Courses and course ownership.
7.  Curriculum sections and learning items.
8.  Media and lesson resources.
9.  Lesson curriculum PDFs and AI indexing state.
10. Student learning/progress.
11. Live sessions and attendance.
12. Quizzes/questions/attempts.
13. Assignments/submissions/feedback.
14. Certificates and verification.
15. Enrollment and unified entitlements.
16. Orders, payments, refunds, coupons.
17. Bundles and subscription plans/periods.
18. Instructor commission rules, earnings ledger and settlements.
19. AI conversations and lesson-scoped retrieval.
20. Help requests and request-thread messages.
21. Notifications and announcements.
22. Reporting and exports.
23. Audit, settings and integrations.
24. Legacy migration and reconciliation.

## 4. Core logical entities

The names below are the current logical model, reconciled with ED-002,
ED-004 and ED-006. Do not implement superseded tables and migrate between
them merely because an earlier pack listed them. Final column types and
foreign keys follow the selected database; legacy mapping is a separate import.

  ---------------------------------------------------------------------
  Entity                             Purpose / key relationships
  ---------------------------------- ----------------------------------
  `users`                            All login identities; email/phone,
                                     status, locale, timezone

  `roles`, `permissions`,            RBAC and grants
  `user_roles`, `role_permissions`   

  `student_profiles`                 Learner preferences and profile
                                     fields

  `guardian_profiles`,               Parent identity, child
  `guardian_child_links`             relationship, verification and
                                     consent

  `instructor_profiles`              Instructor bio/public details and
                                     protected payout profile reference

  `categories`                       Hierarchical course taxonomy

  `courses`                          Course core, owner type, status,
                                     level, age suitability, format,
                                     access mode

  `courses` JSON fields              AR/EN metadata (no translations table)

  `course_instructors`               Assigned instructor, role and
                                     scope

  `course_commission_rules`          Instructor/course/sale-type
                                     commission settings

  `course_sections`                  Ordered curriculum sections

  `curriculum_items`                 Single mixed-type ordering, section,
                                     publication, preview and unlock rules

  `lessons`                          Typed lesson content and duration;
                                     no independent ordering column

  `media_assets`                     Provider, asset ID, URL/reference,
                                     status and playback metadata

  `lesson_resources`                 PDFs, files, images, links and
                                     download rules

  `lesson_curriculum_documents`      Per-lesson curriculum PDF,
                                     version, hash, approval and
                                     indexing status

  `quizzes`, `questions`,            Quiz definition and question bank
  `question_options`                 

  `quiz_attempts`, `quiz_answers`    Immutable attempt snapshot and
                                     responses

  `assignments`,                     Practical tasks and review history
  `assignment_submissions`,          
  `submission_feedback`              

  `live_sessions`, `live_attendance` Meeting schedule, provider
                                     reference and attendance

  `enrollments`, `entitlements`      Enrollment history and
                                     authoritative access rights

  `lesson_progress`,                 Learner+item facts; learner+course
  `course_progress`                  aggregate, independent of grant renewal

  `certificates`,                    Issued certificate and
  `certificate_templates`            verification

  `orders`, `order_items`,           Commercial transactions and status
  `payments`, `refunds`              history

  `coupons`, `coupon_redemptions`    Discount rules and use records

  `bundles`, `bundle_items`          Bundle composition and course
                                     allocation data

  `subscription_plans`,              Plan rules and active
  `subscription_plan_courses`,       billing/access periods
  `subscriptions`,                   
  `subscription_periods`             

  `instructor_ledger_entries`,       Accrued commission, adjustments
  `instructor_settlements`,          and paid settlement
  `settlement_items`                 

  `ai_conversations`, `ai_messages`, Student-owned AI records and cost
  `ai_usage`                         metadata

  `lesson_document_pages`,           Approved versioned source text and
  `lesson_document_chunks`           optional retrieval (§16 of AI spec)

  `help_requests`,                   Instructor escalation and
  `help_request_messages`,           in-platform thread
  `help_request_events`              

  `notifications`, `email_logs`,     Communication and delivery records
  `announcements`                    

  `pages`, `blog_posts`,             CMS/marketing operations
  `contact_enquiries`,               
  `newsletter_subscribers`           

  `audit_logs`, `settings`,          Security and platform operations
  `integration_settings`             

  `migration_batches`,               Repeatable import and
  `migration_records`,               reconciliation
  `migration_exceptions`             
  ---------------------------------------------------------------------

## 5. Key relationship and business rules

### 5.1 Course and curriculum

`Course → Sections → Learning Items`. Learning items may be recorded
lesson, live session, quiz, practical assignment or resource. Each
lesson has zero or one active curriculum PDF version; a recorded lesson
needs an approved readable PDF to enable AI.

`curriculum_items` owns order, required/published/preview state and unlock
rules. Its allowlisted typed reference resolves to exactly one record in the
same course. A standalone resource is a resource-type `lesson` with attached
`lesson_resources`; it is not an unimplemented fifth polymorphic type. Enforce
same-course section/type membership and unique typed attachment in Actions and
database constraints where supported. Reorders use a transaction and a revision
check; concurrent editors get a conflict rather than silently losing an order.
Referenced items are archived, not hard-deleted through attempts or history.

### 5.2 Unified entitlement

Every access grant is represented by a unified entitlement with source
type/reference, learner, course, start/end, state and audit actor.
Sources include one-time purchase, bundle, subscription, free
enrollment, manual grant, approved offline payment and legacy migration.
All protected resources call the same authorization service.

One entitlement engine does **not** mean one overwritable learner-course row.
Keep independent grants unique by `(source_type, source_ref, learner_id,
course_id)`; access may remain valid through another active grant after one
expires or is reversed. Never derive access from an enrollment row or a cached
dashboard aggregate. Grant starts/ends are UTC half-open intervals
`starts_at <= now < ends_at` (null end means no expiry). Subscription grace,
refund/dispute effects and relative-drip anchor policy use approved settings;
do not infer them from the most recently inserted grant (ED-034/035).

### 5.3 Parent/child

A guardian-child link is a separate entity with
verification/status/consent metadata. Parent queries scope through
verified active links. Do not model parent as simply another student
role.

Account creation/link-code possession is a technical relationship, not proof
of legal guardianship. Sensitive child access/recovery/notification routing
requires the verified active relationship and approved O-015 policy. Unknown
age is not silently classified as adult for AI. Revocation invalidates context
on the next request; parent payer identity never substitutes for learner identity.

### 5.4 Instructor commission

Commission entries reference original sale/period and course. Individual
course = configured percentage. Bundle = admin-defined allocation per
included course then configured percentage. Subscription = fixed amount
per eligible student-course-period. Ledger generation is idempotent. No
watch-time or engagement calculation.

Snapshot price, currency, discount/tax components, bundle allocations and
approved commission terms on the source sale/period. Recalculating old earnings
from today's course settings is forbidden. Exact bases/rates/proration remain
O-008..O-012; missing approval blocks affected calculation/settlement, not a
silent zero/default. Corrections append linked reversal/adjustment entries;
paid settlements and original facts remain immutable (ED-034).

### 5.5 AI source restriction

Retrieval scope must be
`(authenticated_student, active_entitlement, current_recorded_lesson, approved_curriculum_pdf_version)`.
Do not index global courses for this assistant. Recheck permission
before prompt construction and response delivery.

Pin source content hash, prompt/guard versions and model configuration for
each request. Cached output obeys the same access, version and safety checks.
The model receives no tool privileges; a help request is a separately authorized
user Action. AI outage cannot prevent a learner using non-AI features.

## 6. Suggested API groups

All protected endpoints require authentication and backend policies.
Routes are indicative; naming may adapt while preserving contracts.

  ---------------------------------------------------------------------
  Group                              Example operations
  ---------------------------------- ----------------------------------
  Auth                               register, login, logout,
                                     forgot/reset password, verify
                                     email, current user

  Guardian                           list linked children,
                                     request/confirm link, switch child
                                     context, unlink under policy

  Catalog                            list/filter courses, course
                                     details, category list

  Course admin                       create/update/draft/submit for
                                     approval/publish/archive course

  Curriculum                         create/reorder sections and
                                     lessons, attach
                                     media/PDF/resources

  Learning                           get entitled curriculum,
                                     authorized playback, download
                                     resource, update progress

  Live                               list own sessions, get authorized
                                     join link, attendance action for
                                     instructor/admin

  Quiz                               create attempt, save answer,
                                     submit, view permitted result

  Assignment                         get task, submit file/text,
                                     instructor review/grade/feedback

  Certificate                        list own certificates, public
                                     verification, authorized
                                     revoke/issue

  Commerce                           create order, checkout, payment
                                     status, coupon validation, refund
                                     approval

  Entitlement                        admin manual grant/revoke, learner
                                     own access list

  Bundle/subscription                plan list, bundle details,
                                     purchase/activate/cancel/expire
                                     status

  Instructor finance                 instructor own statement;
                                     finance/admin ledger and
                                     settlement actions

  AI                                 open conversation, send lesson
                                     question, feedback
                                     understood/unresolved, usage
                                     status

  Help request                       create from AI, list own requests,
                                     instructor/admin queue, reply,
                                     status update

  Admin/report                       overview, filtered reports, CSV
                                     export, audit search

  CMS                                pages/blog/contact/newsletter CRUD
                                     and publishing
  ---------------------------------------------------------------------

Suggested error semantics: `401` unauthenticated, `403` unauthorized,
`404` hidden resource, `409` invalid state/idempotency conflict, `422`
validation, `429` rate/quota, `503` provider unavailable. Never return
protected snippets/files inside an authorization error.

### 6.1 Shared entry-point contract

Web, Filament, Livewire and API call the same public Action/Query. Resolve
actor and permitted learner context on the server, validate bounded input,
authorize the object and operation, and serialize an allowlisted response.
Mutation Actions also enforce policy and current state so jobs/CLI cannot bypass
them. Document trusted system paths explicitly; request data cannot impersonate
a system actor. Cookie-authenticated mutations require CSRF protection; webhook
routes use provider verification instead. Apply pagination/size limits to reads.

Accept client idempotency keys for checkout creation, attempts/submissions and
AI requests where retries can duplicate effects. Scope each key to actor +
operation, store request fingerprint and outcome, return the same outcome on an
identical replay, and reject reuse for different input with `409`. A key is not
authorization. Provider event identity and source-level business uniqueness
remain required even when the client supplies no key. Do not let URLs select
unrestricted child, file, conversation or document identifiers.

## 7. Domain events and idempotency

  -----------------------------------------------------------------------
  Event                                    Consumer behavior
  ---------------------------------------- ------------------------------
  `PaymentVerified`                        Verify amount/order/reference;
                                           issue entitlement once; create
                                           ledger source event

  `ManualPaymentApproved`                  Issue entitlement once; audit
                                           finance approver

  `EntitlementActivated/Revoked/Expired`   Update learner access and
                                           block video/PDF/live/AI
                                           accordingly

  `CoursePublished/Unpublished`            Update catalog and lesson
                                           availability

  `LessonPdfApproved/Replaced`             Index new PDF version; retire
                                           prior active source

  `LessonProgressUpdated`                  Refresh learner
                                           progress/dashboard

  `QuizResultPublished`                    Notify learner and evaluate
                                           completion

  `AssignmentFeedbackPublished`            Notify learner and evaluate
                                           completion

  `LiveSessionChanged`                     Notify affected enrolled
                                           learners once

  `HelpRequestCreated`                     Notify assigned instructor and
                                           admin copy

  `HelpRequestReplied`                     Notify student and update
                                           request state

  `CertificateIssued/Revoked`              Generate/refresh certificate
                                           verifier

  `SubscriptionStarted/Renewed/Expired`    Update entitlements and
                                           subscription-period ledger
                                           entries

  `InstructorSettlementPaid`               Close settlement items and
                                           update instructor statement
  -----------------------------------------------------------------------

Use idempotency keys or unique source references for payment
fulfillment, notifications, subscription earnings, certificates and
migration records.

### 7.1 Transaction and delivery boundaries (ED-034)

| Boundary | Atomic local facts | External/delayed work and recovery |
|---|---|---|
| Verified payment/manual approval | Lock source order/attempt; validate immutable amount/currency/beneficiary snapshots; persist legal state transition, fulfillment marker, scoped grants and ledger source once | Notification/receipt jobs after commit; durable pending event/inbox or reconciliation repairs a crash between commit and dispatch |
| Refund/dispute | Persist verified provider fact plus linked adjustment/hold, source-specific access change under approved policy | Refund submission outside DB transaction, stable provider key if supported; ambiguous timeout → reconcile, never blind duplicate refund |
| Subscription renewal | One period per verified renewal source; immutable plan/course snapshot; source grants and one earning per learner-course-period | Expiry uses authoritative timestamps even if scheduler is late; reminders dedupe by period/offset |
| Cohort seat | Lock cohort/reservation and check capacity; unique membership; consume/release hold once | Late payment after expired/full reservation enters finance review; no overbooking or automatic unapproved refund |
| Progress/assessment | Atomic attempt transitions and item facts; deadline evaluated server-side against snapshotted rules | `course_progress` is a rebuildable read model; completion/certificate checks use authoritative facts, not a stale percent |
| PDF promotion | Pin immutable approved content; compare expected current version; swap active pointer only after READY and publication approval | Jobs carry document id/hash; stale extraction/index jobs cannot replace newer content; failure keeps last approved version active |
| Help/notification | One help request per learner/message; append state event and delivery reference | Recipient scope/consent rechecked at delivery; retries dedupe, failed sends appear in operations |

Do not hold a database transaction open across a provider call, OCR, file upload
or email. Write durable local intent first when needed, perform external work,
then finalize with a state/unique-key check. At-least-once jobs must tolerate
duplicates, out-of-order events, stale models and worker death. Queue jobs carry
IDs/version hashes rather than full private text or stale serialized models.
Set bounded attempts/backoff/timeouts; expose failed/quarantined items and an
audited replay action. Critical work requires a durable recovery path; an
after-commit dispatch alone is not crash-proof. Use the existing source table
as a pending-work record where sufficient, not an event platform by default.

Record and test unique constraints for payment source, grant source, ledger
source+beneficiary+period, settlement membership, active attempt, help-message
source and migration source. Cross-table foreign keys and validation prevent
course/learner mismatches. Polymorphic references need allowlisted types and
explicit integrity checks because a generic foreign key cannot prove them.

## 8. Security and operational constraints

-   Policies at route, service and query layers; no UI-only access
    control.
-   Private file storage and expiring links; entitlement recheck for
    media/PDF/live/AI.
-   File authorization follows its purpose: learner content uses
    `canAccessItem`; submissions, payment proofs, identity documents and privacy
    exports also use their object/role policy. Course access alone never opens
    another student's submission or a finance/private identity file.
-   Signed media URLs already issued may remain usable until provider expiry.
    Record the real revocation bound for the selected provider and test it;
    do not promise instantaneous revocation of an already downloaded file.
-   Signed webhook verification and transaction idempotency.
-   No card data storage; safe gateway metadata only.
-   Secure upload validation (actual content/MIME, byte/page/dimension limits),
    quarantine and bounded parsing outside the web worker; no executable files
    in public webroot. Provider URL allowlists reject private/link-local targets
    and unsafe redirects; never fetch arbitrary pasted URLs server-side.
-   Secret references outside source control.
-   Audit sensitive changes with actor, target, timestamp and safe
    before/after values.
-   AI PDF text is untrusted input and cannot change assistant policy.
-   Logs avoid full private AI content and personal data unless required
    by approved retention policy.
-   Database and media backup/restore verified before launch.

## 9. Deployment topology

`HTTPS edge → application → relational DB + cache/queue → workers/scheduler → private storage/video provider → payment/live/AI/email adapters`.

Use development, staging and production separation. Keep old production
site untouched until cutover. Verify TLS, environment secrets,
migrations, queue workers, scheduler, storage, monitoring,
backup/restore and rollback before DNS change.

## 10. v3.1 additions

### 10.1 Additional entities

| Entity | Purpose / key fields |
|---|---|
| `users` (extended) | `username` (unique, for child login), `name_ar`, `name_en`, `display_name`, `birth_year`, `birth_month`, `locale`, `timezone`, `status` |
| `guardian_link_codes` | code hash, learner_id, expires_at, used_at |
| `certificate_names` (or user columns) | `certificate_name_ar`, `certificate_name_en`, locked_at |
| `cohorts` | course_id, name (translatable), starts_at, ends_at, capacity, enrollment_opens_at/closes_at, status |
| `cohort_members` | cohort_id, learner_id, entitlement_id, status (active/waitlisted/removed), position |
| `cohort_instructors` | cohort_id, instructor_id |
| `media_assets` | `protection_tier` (T1/T2/T3), `exception_reason`, provider, external_id, status; canonical name, no duplicate `lesson_media_assets` table |
| `user_sessions` / `user_devices` | user_id, device fingerprint hash, user agent, last_ip (hashed or truncated), last_seen_at, revoked_at |
| `account_sharing_flags` | user_id, signal, window, status |
| `course_revisions` | course_id, author_id, payload (diff/snapshot), status (draft/pending/approved/rejected), reviewer_id, reason |
| `payment_attempts` | order_id, gateway (`paymob`), intention/reference ids, status, raw payload (sanitized), signature_valid |
| `payment_webhook_events` | gateway/account, stable provider event identity or documented fingerprint (unique scope), payload hash, verified_at, received_at, processing state, processed_at, result; transaction status events are not collapsed into one transaction id |
| `manual_payment_proofs` | order_id, payer name, sender ref (masked), transfer_reference, amount, transferred_at, private file_ref, review status; duplicate detection scoped to approved channel/account semantics, ambiguous matches require finance review |
| `payment_reconciliations` | run date, gateway, matched, mismatched, report ref |
| `e_receipts` | order_id, provider, document uuid, status, payload ref (adapter disabled until O-023) |
| `subscription_reminders` | subscription_id, offset_days, sent_at (unique per period+offset) |
| `instructor_applications` | applicant user, discipline, portfolio links, id document ref, status, reviewer |
| `instructor_agreements` / `instructor_agreement_acceptances` | version, body (translatable), published_at / instructor_id, version, accepted_at, ip |
| `instructor_payout_profiles` | instructor_id, encrypted details, verified_at |
| `wishlists` | user_id, course_id (unique pair) |
| `course_reviews` | course_id, learner_id, rating, body, status (pending/published/hidden), moderated_by |
| `consents` | user_id or guardian_id, type (cookies, guardian, ai), version, granted_at, withdrawn_at |
| `data_requests` | requester_id, subject_id, type (export/delete), status, completed_by, file_ref |
| `retention_policies` | data_class, days |
| `ai_*` tables | see `06_AI_LESSON_ASSISTANT_SPEC.md` §16 |

Searchable tables carry a `search_text` column filled by the shared Arabic normalizer (see conventions §8).

### 10.2 Payment flow (Paymob)

```
Checkout (server prices order) → PaymentGateway::createCheckout(order) [PaymobGateway]
  → learner pays on Paymob → browser returns to /checkout/return (status display only)
  → Paymob server callback → /webhooks/paymob
      verify signature → match merchant order ref + amount + currency
      → durably record verified payment_webhook_events → acknowledge/retry per provider contract
      → Commerce\\Actions\\FulfillVerifiedOrder (state + source uniqueness, local transaction)
      → Entitlements\\Actions\\GrantEntitlement + ledger source record (once)
      → after-commit delivery with pending-work/reconciliation recovery
```

The exact Paymob API (checkout/intention endpoints, signature fields) is taken from Paymob's official documentation at implementation time and recorded in `docs/integrations/PAYMOB.md`.

That integration file must also evidence event identity, merchant/account
binding, raw-body signature handling, units/currency, replay semantics, status
ordering, acknowledgement/retry behavior and refund/query support in sandbox.
A browser return never marks paid. Stale pending/failed callbacks cannot undo
settled/refunded facts; unknown provider states are quarantined for reconciliation.

### 10.3 Additional adapters

| Contract | First driver | Later |
|---|---|---|
| `PaymentGateway` | Paymob driver + fake for required checkout/query operations | Refund capability only when supported; manual proof approval is an Action, not a fake gateway |
| E-receipt seam | Disabled pending O-023; preserve required source data | Add a driver only for the selected obligation/provider |
| `VideoDeliveryProvider` | Selected T1/T2 driver + fake after O-005; allowlisted external embeds for approved tiers | HLS/transcoding only if the chosen delivery needs it; no speculative driver suite |
| Notifications | Laravel mail/database facilities | Channel implementation only when O-021 and scoped task require it |
| `AiProviderInterface` | Completion + fake; §11 of AI spec | Separate OCR/embedding capabilities when ingestion needs them |
| OCR | Selected local tool or approved remote capability | Private PDF processing subject to same provider/data gate as chat |
| PDF output | Selected Arabic-capable renderer through its existing API | Add a custom contract only for a demonstrated substitution/testing need |

### 10.4 Additional events

`ChildAccountCreated`, `GuardianLinked`, `CohortSeatReserved/Released`, `WaitlistPromoted`, `CourseRevisionSubmitted/Approved/Rejected`, `PaymentWebhookReceived`, `PaymentReconciliationCompleted`, `ManualPaymentProofSubmitted`, `SubscriptionRenewalDue`, `InstructorApplicationReviewed`, `InstructorAgreementAccepted`, `DataRequestCompleted`, `AccountSharingFlagged`. All consumers idempotent by source reference.

## 11. v3.2 changes (see `13_ENGINEERING_DECISIONS.md`)

### 11.1 Model changes
| Change | Detail | ED |
|---|---|---|
| `curriculum_items` | course_id, section_id, sort, itemable_type (`lesson`, `quiz`, `assignment`, `live_session`), itemable_id, status draft/published, visibility, unlock_rule (none/absolute/relative_days), unlock_at, unlock_days, required (bool). **Only ordering source**; `lessons` lose their sort column. | ED-002, ED-025 |
| `courses.sequence_mode` | free / sequential | ED-025 |
| `course_progress` | learner_id, course_id, percent, completed_required, total_required, last_item_id, last_position_seconds, last_activity_at, completed_at (unique learner+course) | ED-004 |
| `lesson_progress` | keyed by learner_id + curriculum_item_id (not entitlement) | ED-004 |
| Translations | JSON translatable columns replace the `*_translations` tables listed in §4 | ED-006 |
| `media_assets` | single table (replaces `lesson_media_assets` naming); adds `library_item_id` | ED-007, ED-026 |
| `library_items` | owner_type academy/instructor, owner_id, media_asset_id or file ref, title (translatable), tags; referenced by lessons/resources | ED-026 |
| `subtitle_tracks` | media_asset_id, locale, file ref, label, default | ED-008 |
| `two_factor` columns | secret (encrypted), recovery_codes (encrypted), confirmed_at on users | ED-016 |
| `consents` | adds `terms`, `privacy`, `guardian` types with versions | YG-GAP-ID-002 |
| `feature_flags` | Pennant storage + `flag_approvals` (flag, approval_ref, approved_by, at) | ED-015 |
| `view_as_sessions` | staff_id, learner_id, started_at, ended_at, reason | ED-027 |

### 11.2 Access decision
`EntitlementService::canAccessItem()` returns `AccessDecision{allowed, reason_code, unlock_at}`. Reason codes: `course_unpublished`, `item_unpublished`, `not_entitled`, `entitlement_expired`, `not_in_cohort`, `sequence_locked`, `drip_locked`, `preview_only`, `live_window_closed`. Each has AR/EN copy in `lang/*/access.php`. (ED-005)

ED-035 replaces the ambiguous final "preview override": use explicit server
contexts `learner`, `public_preview`, `author_preview`, `support_view_as`.
Validate account status/context/ownership first. Normal learning checks
publication → any qualifying grant → cohort → sequence → drip → operation
window. Public preview is an explicit published item/resource allowance only;
it does not unlock the rest of the course, AI, progress or assessment writes,
or bypass cohort/sequence/drip. Author preview requires assigned-author/admin
policy and permits draft viewing only; support view-as evaluates the target
learner's actual rights. Both preview modes and support view-as block writes
and AI calls. Separate instructor test-console permission is not an impersonation
exception. Do not expose hidden resource details in public denial reasons.

The decision carries mode/capabilities as needed by the caller; `allowed=true`
for a preview never means `canWriteProgress=true`. Resolve relative-drip anchors
from an approved source rule; overlapping grants must not arbitrarily relock a
learner. Add `account_inactive`, `context_forbidden` and `operation_forbidden`
reason codes where applicable. `AssistantEligibility` builds on this result and
adds PDF/flags/consent; it does not reimplement access queries.

### 11.3 Sellables
Keep `order_items.sellable_type/id` allowlisted and snapshot its source data.
ED-033 refines ED-012: a `Sellable` contract is optional for a real common read
operation; models do not grant access or calculate ledger effects. Shared
checkout Actions dispatch the three known sellable types and call the owning
domain Actions. A small explicit type branch is preferable to three speculative
plugin frameworks. Cross-domain schema/API changes update tasks, fixtures,
consumers and the reuse registry together.

## 12. Dependency ownership and evidence

Identity/Guardian policy resolves actor + learner context; Entitlements owns
all grant writes/reads; Curriculum owns item identity/order/publication; Media
owns private source assets; Learning/Assessments own progress/attempt facts;
Commerce owns verified money facts; InstructorFinance owns ledger/settlement;
AI consumes authorized snapshots and cannot mutate these domains. Reporting
uses permission-scoped Queries and exports; it never becomes a second writer.
Outer workflow Actions may call another domain's public Action in one local
transaction; domain models and internal `Support/` are not orchestration APIs.
Avoid bidirectional service calls: access consumes narrow completion facts,
while completion uses the same rules without recursively calling access.

Before an implementation task is accepted, show its boundary tests: duplicate
and concurrent events, rollback/worker restart, stale versions, wrong actor and
cross-course IDs, denied file capabilities, overlapping grants, money rounding,
Arabic source mismatch, and approved policy branches. These are required
evidence cases, not claims that tests or application code exist in this pack.
