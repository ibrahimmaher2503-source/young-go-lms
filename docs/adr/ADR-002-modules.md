# ADR-002 — Module map, table ownership and public surface

- **Status:** Accepted (orchestrator, T-P0-05) · conceptual; columns are finalized by each owning task's migrations
- **Date:** 2026-11-16
- **Refs:** Arch §3–§5, §7, §10, §11 (`05_ARCHITECTURE_DATA_API.md`); Conventions §3–§6; AI spec §16, §23; DMC §5.3–§5.4; ED-002/004/005/006/007/012/015/026/027
- **ERD:** `docs/erd/core.md` · **Coverage check:** `agents/logs/T-P0-05/coverage.txt`

## 1. Decision

1. Modules = the folders of Conventions §3 (`app/Domain/<Module>`). Arch §3 (24 conceptual domains) and DMC §5.3
   (28 names) describe the same system at a different grain; §2 maps every name to one folder. The two lists do not conflict.
2. Every table has **exactly one owning module**. Only the owner writes it. Other modules use the owner's
   public Actions, Queries, Events or Contracts (Conventions §4). Exception already in Conventions §4: only
   `Entitlements` may query `entitlements`.
3. A new table needs a row in §3 in the same change that creates its migration.
4. **AI rule:** the AI module reads curriculum documents, pages, key terms and lesson state only through
   Media/Curriculum/Entitlements Queries and `EntitlementService::canAccessItem()`, never through their models (Arch §5.5, AI spec §23).
5. Rich-text lesson bodies are not an AI source (D-018, AI spec §23).

## 2. Name mapping (Arch §3 / DMC §5.3 → module folder)

| Arch §3 domain / DMC §5.3 name | Module |
|---|---|
| 1 Identity and profiles · DMC `Identity` | Identity |
| 2 Roles and permissions · DMC `Roles` | Identity |
| 3 Guardian-child relationships · DMC `Guardians` | Guardians |
| 4 Public catalog and CMS · DMC `Catalog`, `CMS` | Catalog (discovery, search) + Cms (pages, blog, enquiries) |
| 5 Categories and age suitability | Catalog (categories); age bands = Catalog; course min/max age = Courses |
| 6 Courses and course ownership · DMC `Courses` | Courses |
| 7 Curriculum sections and learning items · DMC `Curriculum` | Curriculum |
| 8 Media and lesson resources · DMC `Media` | Media |
| 9 Lesson curriculum PDFs and AI indexing state | Media (documents, pages, key terms, approval) + AI (chunks/index) |
| 10 Student learning/progress · DMC `Learning`, `Progress` | Learning |
| 11 Live sessions and attendance · DMC `Live` | Live |
| 12 Quizzes/questions/attempts · DMC `Assessments` | Assessments |
| 13 Assignments/submissions/feedback · DMC `Assignments` | Assignments |
| 14 Certificates and verification · DMC `Certificates` | Certificates |
| 15 Enrollment and unified entitlements · DMC `Enrollment`, `Entitlements` | Entitlements |
| 16 Orders, payments, refunds, coupons · DMC `Commerce` | Commerce |
| 17 Bundles and subscription plans/periods · DMC `Bundles`, `Subscriptions` | Bundles · Subscriptions |
| 18 Commission rules, ledger, settlements · DMC `InstructorFinance` | InstructorFinance |
| 19 AI conversations and lesson-scoped retrieval · DMC `AI` | AI |
| 20 Help requests and thread messages · DMC `HelpRequests` | HelpRequests |
| 21 Notifications and announcements · DMC `Notifications` | Notifications |
| 22 Reporting and exports · DMC `Reporting` | Reporting (read models only; owns no tables) |
| 23 Audit, settings and integrations · DMC `Audit`, `Settings`, `Integrations` | Audit · Settings (incl. integration settings) |
| 24 Legacy migration and reconciliation · DMC `Migration` | Migration |
| (Conventions §3 only) `Instructors`, `Engagement`, `Privacy`, `Shared` | Instructors · Engagement · Privacy · Shared (owns no tables) |

## 3. Table ownership

Phase = first task that creates the table (task title/spec). **inferred** = no task text names the table; phase taken from the owning task's title.
Sources: §4 = Arch §4, §10.1 = Arch §10.1, §11.1 = Arch §11.1, AI = AI spec §16.

| Table | Module | Phase (task) | Source | Note |
|---|---|---|---|---|
| `users` | Identity | P1 (T-P1-02) | §4, §10.1 | extended: `username` UK, names AR/EN, birth year/month (§10.1); `two_factor` columns (§11.1, T-P1-11) |
| `roles` | Identity | P1 (T-P1-03) | §4 | spatie/laravel-permission table |
| `permissions` | Identity | P1 (T-P1-03) | §4 | spatie/laravel-permission table |
| `user_roles` | Identity | P1 (T-P1-03) | §4 | **implemented as** spatie `model_has_roles` (+ `model_has_permissions`) |
| `role_permissions` | Identity | P1 (T-P1-03) | §4 | **implemented as** spatie `role_has_permissions` |
| `student_profiles` | Identity | P1 (T-P1-02) | §4 | |
| `certificate_names` | Identity | P1 (T-P1-02) inferred | §10.1 | §10.1 allows user columns instead; decided in T-P1-02 (R-012) |
| `user_sessions` | Identity | P4 (T-P4-07) | §10.1 | name differs from Laravel's `sessions` table on purpose (§5) |
| `user_devices` | Identity | P4 (T-P4-07) | §10.1 | |
| `account_sharing_flags` | Identity | P4 (T-P4-07) inferred | §10.1 | |
| `view_as_sessions` | Identity | P9 (T-P9-10) | §11.1 | ED-027; T-P4-10 instructor preview may reuse it |
| `guardian_profiles` | Guardians | P1 (T-P1-05) inferred | §4 | |
| `guardian_child_links` | Guardians | P1 (T-P1-05) | §4 | |
| `guardian_link_codes` | Guardians | P1 (T-P1-06) | §10.1 | |
| `instructor_profiles` | Instructors | P2 (T-P2-09) inferred | §4 | payout data lives in `instructor_payout_profiles` |
| `instructor_applications` | Instructors | P9 (T-P9-01) | §10.1 | |
| `instructor_agreements` | Instructors | P9 (T-P9-01) | §10.1 | |
| `instructor_agreement_acceptances` | Instructors | P9 (T-P9-01) | §10.1 | |
| `instructor_payout_profiles` | Instructors | P9 (T-P9-01) | §10.1 | encrypted |
| `age_bands` | Catalog | P1 (T-P1-07) | task | ownership assigned here; editable age ranges |
| `blog_categories` | Cms | P2 (T-P2-07) | task | |
| `tags` | Cms | P2 (T-P2-07) | task | |
| `notification_dispatches` | Notifications | P5 (T-P5-04) | task | unique dedupe_key |
| `categories` | Catalog | P2 (T-P2-01) | §4 | |
| `pages` | Cms | P2 (T-P2-02) | §4 | |
| `blog_posts` | Cms | P2 (T-P2-07) | §4 | |
| `contact_enquiries` | Cms | P2 (T-P2-08) | §4 | |
| `newsletter_subscribers` | Cms | P2 (T-P2-08) | §4 | |
| `courses` | Courses | P3 (T-P3-01) | §4, §11.1 | + `sequence_mode` (§11.1), age suitability columns (T-P1-07 model) |
| `course_translations` | Courses | — | §4 | **superseded** by JSON translatable columns on `courses` (ED-006, §11.1); not created |
| `course_instructors` | Courses | P3 (T-P3-01) | §4 | |
| `course_revisions` | Courses | P3 (T-P3-07) | §10.1 | |
| `cohorts` | Courses | P5 (T-P5-01) | §10.1 | Conventions §3 puts cohorts in Courses |
| `cohort_members` | Courses | P5 (T-P5-01) | §10.1 | |
| `cohort_instructors` | Courses | P5 (T-P5-01) | §10.1 | |
| `course_sections` | Curriculum | P3 (T-P3-04) | §4 | |
| `lessons` | Curriculum | P3 (T-P3-04) | §4, §11.1 | no sort column (ED-002) |
| `curriculum_items` | Curriculum | P3 (T-P3-04) | §11.1 | **only ordering source** (ED-002, ED-025) |
| `media_assets` | Media | P3 (T-P3-05) | §10.1, §11.1 | single table (ED-007); `protection_tier`, `library_item_id` |
| `lesson_media_assets` | Media | — | §4 | **superseded** by `media_assets` (ED-007, §11.1); not created |
| `lesson_resources` | Media | P3 (T-P3-08) | §4 | |
| `library_items` | Media | P3 (T-P3-13) | §11.1 | ED-026 |
| `subtitle_tracks` | Media | P3 (T-P3-14) | §11.1 | ED-008 |
| `lesson_curriculum_documents` | Media | P3 (T-P3-08) | §4, AI | per-lesson PDF, version, hash, approval |
| `lesson_document_pages` | Media | P3 (T-P3-10 schema; T-P3-09 ingestion) | AI | extracted/approved text per page |
| `lesson_key_terms` | Media | P3 (T-P3-10 schema; T-P3-09 ingestion) | AI | |
| `ai_pdf_sources` | Media | — | §4 | **superseded** by `lesson_curriculum_documents` (AI spec §16; Arch §10.1 points to AI §16); not created |
| `lesson_document_chunks` | AI | P3 (T-P3-10 schema; T-P3-09 ingestion) | AI | chunked mode only (ED-011) |
| `ai_pdf_chunks` | AI | — | §4 | **superseded** by `lesson_document_chunks` (AI spec §16); not created |
| `ai_prompt_versions` | AI | P3 (T-P3-10) | AI | |
| `ai_conversations` | AI | P3 (T-P3-10) | §4, AI | |
| `ai_messages` | AI | P3 (T-P3-10) | §4, AI | |
| `ai_feedback` | AI | P3 (T-P3-10) | AI | |
| `ai_usage` | AI | P3 (T-P3-10) | §4, AI | opaque student hash |
| `ai_answer_cache` | AI | P3 (T-P3-10) | AI | |
| `ai_quota_counters` | AI | P3 (T-P3-10) | AI | |
| `ai_eval_cases` | AI | P3 (T-P3-10) | AI | |
| `ai_eval_runs` | AI | P3 (T-P3-10) | AI | |
| `ai_safeguarding_flags` | AI | P3 (T-P3-10) | AI | |
| `help_requests` | HelpRequests | P7 (T-P7-04) | §4, AI | |
| `help_request_messages` | HelpRequests | P7 (T-P7-04) | §4, AI | |
| `help_request_events` | HelpRequests | P7 (T-P7-04) | §4, AI | |
| `lesson_progress` | Learning | P4 (T-P4-04) | §4, §11.1 | keyed by learner + `curriculum_item_id`, **not** per enrollment (§11.1 overrides §4) |
| `course_progress` | Learning | P4 (T-P4-04) | §11.1 | ED-004 |
| `live_sessions` | Live | P5 (T-P5-02) | §4 | |
| `live_attendance` | Live | P5 (T-P5-05) | §4 | |
| `quizzes` | Assessments | P6 (T-P6-01) | §4 | |
| `questions` | Assessments | P6 (T-P6-01) | §4 | |
| `question_options` | Assessments | P6 (T-P6-01) | §4 | |
| `quiz_attempts` | Assessments | P6 (T-P6-02) | §4 | immutable snapshot |
| `quiz_answers` | Assessments | P6 (T-P6-02) | §4 | |
| `assignments` | Assignments | P6 (T-P6-03) | §4 | |
| `assignment_submissions` | Assignments | P6 (T-P6-03) | §4 | |
| `submission_feedback` | Assignments | P6 (T-P6-04) | §4 | |
| `certificates` | Certificates | P6 (T-P6-06) | §4 | |
| `certificate_templates` | Certificates | P6 (T-P6-06) | §4 | |
| `enrollments` | Entitlements | P4 (T-P4-02) inferred | §4 | |
| `entitlements` | Entitlements | P4 (T-P4-01) | §4 | only module allowed to query it (Conventions §4) |
| `orders` | Commerce | P8 (T-P8-01) | §4 | |
| `order_items` | Commerce | P8 (T-P8-01) | §4, §11.3 | `sellable_type/id` (ED-012) |
| `payments` | Commerce | P8 (T-P8-03) | §4 | |
| `refunds` | Commerce | P8 (T-P8-06) | §4 | |
| `coupons` | Commerce | P8 (T-P8-05) | §4 | |
| `coupon_redemptions` | Commerce | P8 (T-P8-05) | §4 | |
| `payment_attempts` | Commerce | P8 (T-P8-02) | §10.1 | |
| `payment_webhook_events` | Commerce | P8 (T-P8-03) | §10.1 | |
| `manual_payment_proofs` | Commerce | P8 (T-P8-04) | §10.1 | |
| `payment_reconciliations` | Commerce | P8 (T-P8-11) | §10.1 | |
| `e_receipts` | Commerce | P8 (T-P8-06) | §10.1 | adapter disabled until O-023 |
| `bundles` | Bundles | P8 (T-P8-07) | §4 | |
| `bundle_items` | Bundles | P8 (T-P8-07) | §4 | |
| `subscription_plans` | Subscriptions | P8 (T-P8-08) | §4 | |
| `subscription_plan_courses` | Subscriptions | P8 (T-P8-08) | §4 | |
| `subscriptions` | Subscriptions | P8 (T-P8-08) | §4 | |
| `subscription_periods` | Subscriptions | P8 (T-P8-08) | §4 | |
| `subscription_reminders` | Subscriptions | P8 (T-P8-08) | §10.1 | |
| `course_commission_rules` | InstructorFinance | P8 (T-P8-09) | §4 | |
| `instructor_ledger_entries` | InstructorFinance | P8 (T-P8-09) | §4 | |
| `instructor_settlements` | InstructorFinance | P8 (T-P8-10) | §4 | |
| `settlement_items` | InstructorFinance | P8 (T-P8-10) | §4 | |
| `wishlists` | Engagement | P9 (T-P9-02) | §10.1 | |
| `course_reviews` | Engagement | P9 (T-P9-02) | §10.1 | |
| `notifications` | Notifications | P5 (T-P5-04) inferred | §4 | Laravel database-notification table shape |
| `email_logs` | Notifications | P5 (T-P5-04) inferred | §4 | |
| `announcements` | Notifications | P9 (T-P9-05) | §4 | |
| `consents` | Privacy | P1 (T-P1-02) | §10.1, §11.1 | terms/privacy/guardian/cookies/ai types |
| `data_requests` | Privacy | P9 (T-P9-08) | §10.1 | |
| `retention_policies` | Privacy | P9 (T-P9-08) | §10.1 | |
| `audit_logs` | Audit | P1 (T-P1-04) | §4 | **implemented as** spatie/laravel-activitylog `activity_log` |
| `settings` | Settings | P0 (T-P0-07) | §4 | spatie/laravel-settings table |
| `integration_settings` | Settings | P9 (T-P9-09) inferred | §4 | secrets encrypted; may be settings groups instead of a table |
| `feature_flags` | Settings | P0 (T-P0-07) | §11.1 | **implemented as** Pennant `features` table (ED-015) |
| `flag_approvals` | Settings | P0 (T-P0-07) | §11.1 | |
| `migration_batches` | Migration | P10 (T-P10-03) | §4 | |
| `migration_records` | Migration | P10 (T-P10-03) | §4 | |
| `migration_exceptions` | Migration | P10 (T-P10-03) | §4 | |

Modules with no tables: `Shared` (value objects, normalizer, contracts), `Reporting` (read models over other modules' Queries).

## 4. Framework and package tables (no domain owner)

Created by the framework or packages; no module writes them directly except through the package API.
`migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions` (only if the database session driver is used),
`password_reset_tokens`, `personal_access_tokens` (Sanctum), `media` (spatie/laravel-medialibrary model attachments;
lesson video/PDF assets use `media_assets`), `pulse_*` (Pulse). Horizon stores data in Redis.
Package tables that implement a domain table are listed in §3 under that table (spatie permission, activitylog, settings, Pennant).

## 5. Naming notes

- Laravel `sessions` (framework, login session store) ≠ `user_sessions` (Identity, device/session limits R-015). Keep both names; never reuse `sessions` for domain data.
- `media` (spatie, model attachments) ≠ `media_assets` (Media, playable lesson assets with protection tier).

## 6. Public surface per module

Only names the docs already define are listed. Everything else is defined by the owning task (Conventions §5 pattern:
one `handle()` per Action). Contracts come from Arch §10.3 and DMC §5.4.

| Module | Documented Actions / Queries / services | Contracts | Events assigned here (names: Arch §7, §10.4) |
|---|---|---|---|
| Shared | `ArabicNormalizer`, `HasSearchText`, `Money`/`PriceFormatter`, `Market`/`MarketResolver`, `LocalizedDate` (Conventions §6) | `PdfRenderer` (Arch §10.3) | — |
| Identity | defined by T-P1-02, T-P1-03, T-P4-07 | — | `AccountSharingFlagged` |
| Guardians | defined by T-P1-05, T-P1-06 | — | `ChildAccountCreated`, `GuardianLinked` |
| Instructors | defined by T-P9-01 | — | `InstructorApplicationReviewed`, `InstructorAgreementAccepted` |
| Catalog | discovery/search queries, defined by T-P2-04 | — | — |
| Cms | defined by T-P2-02, T-P2-07, T-P2-08 | — | — |
| Courses | defined by T-P3-01..03, T-P3-07, T-P5-01 | `Sellable` implemented by `Course` (ED-012) | `CoursePublished/Unpublished`, `CourseRevisionSubmitted/Approved/Rejected`, `CohortSeatReserved/Released`, `WaitlistPromoted` |
| Curriculum | defined by T-P3-04 | — | — |
| Media | `PrivateFileController` + signed URL service (Conventions §6); ingestion defined by T-P3-09 | `VideoDeliveryProvider`, `StorageProvider` (DMC §5.4) | `LessonPdfApproved/Replaced` |
| Learning | `Learning\Actions\RecordProgress` (Conventions §11B); T-P4-03, T-P4-04, T-P6-05 | — | `LessonProgressUpdated` |
| Live | defined by T-P5-02..05 | `MeetingProvider` (DMC §5.4) | `LiveSessionChanged` |
| Assessments | defined by T-P6-01, T-P6-02 | — | `QuizResultPublished` |
| Assignments | defined by T-P6-03, T-P6-04 | — | `AssignmentFeedbackPublished` |
| Certificates | defined by T-P6-06 | — | `CertificateIssued/Revoked` |
| Entitlements | `EntitlementService::canAccessItem()` → `AccessDecision` (Arch §11.2), `EntitlementService::grantFromOrder()` (Arch §10.2), `GrantEntitlement` (Conventions §5) | — | `EntitlementActivated/Revoked/Expired` |
| Commerce | `CreateOrder`, `ApplyCoupon`, `OrdersForFinanceQuery` (Conventions §3.1) | `PaymentGateway` (`PaymobGateway`, `ManualTransferGateway`), `EReceiptProvider`, `Sellable` (ED-012) | `PaymentVerified`, `ManualPaymentApproved`, `PaymentWebhookReceived`, `PaymentReconciliationCompleted`, `ManualPaymentProofSubmitted` |
| Bundles | defined by T-P8-07 | `Sellable` implemented by `Bundle` | — |
| Subscriptions | defined by T-P8-08 | `Sellable` implemented by `SubscriptionPlan` | `SubscriptionStarted/Renewed/Expired`, `SubscriptionRenewalDue` |
| InstructorFinance | defined by T-P8-09, T-P8-10 | — | `InstructorSettlementPaid` |
| Engagement | defined by T-P9-02 | — | — |
| AI | pipeline defined by T-P3-10, T-P7-01..06 | `AiProviderInterface` (`FakeAiProvider`), `OcrProvider` (Arch §10.3) | — (none listed in Arch §7/§10.4) |
| HelpRequests | defined by T-P7-04 | — | `HelpRequestCreated`, `HelpRequestReplied` |
| Notifications | `LocalizedNotification` base with dedupe key (Conventions §6) | `NotificationChannel` (mail, database; WhatsApp/SMS later) | — |
| Privacy | defined by T-P9-08 | — | `DataRequestCompleted` |
| Reporting | defined by T-P9-06, T-P9-07 | — | — |
| Audit | `AuditLogger` (Conventions §6) | — | — |
| Settings | defined by T-P0-07 | — | — |
| Migration | defined by T-P10-03 | — | — |

Every Arch §7 and §10.4 event has exactly one emitting module above. Consumers are idempotent by source reference (Arch §7, §10.4).

## 7. Consequences

- Arch tests from T-P0-03 enforce: modules call each other only via Actions/Queries/Events/Contracts; only `Entitlements` queries `entitlements`.
- Later tasks add columns and indexes in their own migrations; this ADR fixes **ownership and names only**.
- Superseded names (`course_translations`, `lesson_media_assets`, `ai_pdf_sources`, `ai_pdf_chunks`) must not be created.
