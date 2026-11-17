# YOUNG GO LMS --- ARCHITECTURE, DATA MODEL & API CONTRACT

**Version:** 1.2 (v3.2 second review) · **Date:** 16 November 2026\
**Status:** Architecture recommendation; final stack/database confirmed
only after environment audit.

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

> Superseded for versions by `docs/adr/ADR-001-stack.md` (T-P0-02): PHP 8.4+, Laravel 13, Filament 5 + Livewire 4, Blade/Livewire student UI (R-026), MySQL 8.4 provisional (O-004).

-   Backend: Laravel 13 / PHP 8.3+ (verify runtime and package
    compatibility).
-   Admin/instructor UI: Filament 5 customized to SKIT; Filament is not
    an LMS core.
-   Student/public UI: custom responsive frontend selected for video
    player and AI side panel needs.
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

Conceptual entities only; final columns/types/indexes/foreign keys
follow legacy source inspection.

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

  `course_translations`              AR/EN metadata and future locales

  `course_instructors`               Assigned instructor, role and
                                     scope

  `course_commission_rules`          Instructor/course/sale-type
                                     commission settings

  `course_sections`                  Ordered curriculum sections

  `lessons`                          Ordered lesson records, type,
                                     duration, preview, required state

  `lesson_media_assets`              Provider, asset ID, URL/reference,
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

  `lesson_progress`                  Per-enrollment lesson status and
                                     resume position

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

  `ai_pdf_sources`, `ai_pdf_chunks`  Lesson-scoped PDF indexing and
                                     retrieval references

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

### 5.2 Unified entitlement

Every access grant is represented by a unified entitlement with source
type/reference, learner, course, start/end, state and audit actor.
Sources include one-time purchase, bundle, subscription, free
enrollment, manual grant, approved offline payment and legacy migration.
All protected resources call the same authorization service.

### 5.3 Parent/child

A guardian-child link is a separate entity with
verification/status/consent metadata. Parent queries scope through
verified active links. Do not model parent as simply another student
role.

### 5.4 Instructor commission

Commission entries reference original sale/period and course. Individual
course = configured percentage. Bundle = admin-defined allocation per
included course then configured percentage. Subscription = fixed amount
per eligible student-course-period. Ledger generation is idempotent. No
watch-time or engagement calculation.

### 5.5 AI source restriction

Retrieval scope must be
`(authenticated_student, active_entitlement, current_recorded_lesson, approved_curriculum_pdf_version)`.
Do not index global courses for this assistant. Recheck permission
before prompt construction and response delivery.

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

## 8. Security and operational constraints

-   Policies at route, service and query layers; no UI-only access
    control.
-   Private file storage and expiring links; entitlement recheck for
    media/PDF/live/AI.
-   Signed webhook verification and transaction idempotency.
-   No card data storage; safe gateway metadata only.
-   Secure upload validation; no executable files in public webroot.
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
| `media_assets` (extends `lesson_media_assets`) | `protection_tier` (T1/T2/T3), `exception_reason`, provider, external_id, status |
| `user_sessions` / `user_devices` | user_id, device fingerprint hash, user agent, last_ip (hashed or truncated), last_seen_at, revoked_at |
| `account_sharing_flags` | user_id, signal, window, status |
| `course_revisions` | course_id, author_id, payload (diff/snapshot), status (draft/pending/approved/rejected), reviewer_id, reason |
| `payment_attempts` | order_id, gateway (`paymob`), intention/reference ids, status, raw payload (sanitized), signature_valid |
| `payment_webhook_events` | gateway, event_id (unique), received_at, processed_at, result |
| `manual_payment_proofs` | order_id, payer name, sender ref (masked), transfer_reference (unique), amount, transferred_at, file_ref, review status |
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
      → store payment_webhook_events (unique event id) → PaymentVerified event (once)
      → EntitlementService::grantFromOrder() (idempotent) → ledger source event
```

The exact Paymob API (checkout/intention endpoints, signature fields) is taken from Paymob's official documentation at implementation time and recorded in `docs/integrations/PAYMOB.md`.

### 10.3 Additional adapters

| Contract | First driver | Later |
|---|---|---|
| `PaymentGateway` | `PaymobGateway`, `ManualTransferGateway` | additional gateway, Saudi gateway |
| `EReceiptProvider` | `NullEReceiptProvider` (disabled) | ETA provider per O-023 |
| `VideoDeliveryProvider` | `SignedStorageVideoProvider` (S3-compatible/local signed URLs), `YouTubeProvider`, `VimeoProvider`, `ExternalUrlProvider` | private streaming provider (O-005) |
| `NotificationChannel` | mail, database | WhatsApp/SMS (O-021) |
| `AiProviderInterface` | `FakeAiProvider` (tests/dev) | selected provider(s) (O-014) |
| `OcrProvider` | via AI adapter or local OCR | — |
| `PdfRenderer` | headless-Chromium HTML-to-PDF (Arabic shaping) | — |

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

### 11.3 Sellables
`Course`, `Bundle`, `SubscriptionPlan` implement `Sellable` (`priceFor(Market)`, `entitlementGrants(learner)`, `ledgerRule()`); `order_items.sellable_type/id`. (ED-012)
