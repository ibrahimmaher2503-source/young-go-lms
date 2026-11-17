# Young Go LMS --- Reuse Registry

Living index of shared code. **Check here before creating anything new.**
Every task that creates a shared piece adds a row in the same commit.
The orchestrator rejects work that duplicates a registered piece.

Status: `planned` (defined, not built) · `built` · `deprecated` (with replacement).

## Backend

| Name | Path | Purpose | Status | Task |
|---|---|---|---|---|
| ArabicNormalizer | `app/Domain/Shared/Search/ArabicNormalizer.php` | Single Arabic/Latin normalization | built | T-P0-06 |
| HasSearchText | `app/Domain/Shared/Search/HasSearchText.php` | Fill `search_text` on save | built | T-P0-06 |
| Category / SaveCategory | `app/Models/Category.php`, `app/Domain/Catalog/Actions/SaveCategory.php` | Bilingual catalog taxonomy and authorized writes that preserve a two-level hierarchy | built | T-P2-01 |
| Course / CoursePolicy / CourseDirectory | `app/Models/Course.php`, `app/Policies/CoursePolicy.php`, `app/Domain/Catalog/Queries/CourseDirectory.php` | Course state, instructor scope and shared staff directory counters | built | T-P3-01 |
| CatalogQuery | `app/Domain/Catalog/Queries/CatalogQuery.php` | Public-only course scope, normalized text search and combinable catalog filters | built | T-P2-04 |
| PublishCourse / FeatureCourse | `app/Domain/Catalog/Actions/` | Explicit course status and featured mutations with execution-time policy checks | built | T-P3-01 |
| CmsPage / HomepageBlock / SaveCmsContent | `app/Models/CmsPage.php`, `app/Models/HomepageBlock.php`, `app/Domain/Content/Actions/SaveCmsContent.php` | Bilingual pages and ordered homepage blocks with permission-gated editor writes | built | T-P2-02 |
| Money / PriceFormatter | `app/Domain/Shared/Money/` | Integer-minor money + locale formatting | built | T-P0-06 |
| Market / MarketResolver | `app/Domain/Shared/Markets/` | EG active / SA disabled | built | T-P0-06 |
| CreateOrder / TransitionOrder / AdjustOrderTotal / Order / OrderItem / CoursePrice | `app/Domain/Commerce/` | Server-priced orders, state machine, audited post-paid adjustment, per-market course prices | built | T-P8-01 |
| LocalizedDate | `app/Domain/Shared/Time/` | UTC storage, local display | built | T-P0-06 |
| AgeBandResolver | `app/Domain/Catalog/AgeBandResolver.php` | Resolve a learner's configured age band from birth year and month | built | T-P1-07 |
| AgeRange / AgeRangeRule | `app/Domain/Catalog/AgeRange.php`, `app/Domain/Catalog/Rules/AgeRangeRule.php` | Inclusive, nullable age bounds and validation for course and band ranges | built | T-P1-07 |
| RBAC catalog and BasePolicy | `config/permissions.php`, `app/Policies/BasePolicy.php` | Initial role defaults and reusable policy permission check | built | T-P1-03 |
| AuditLogger | `app/Domain/Audit/AuditLogger.php` | Audit sensitive actions | built | T-P1-04 |
| EntitlementService | `app/Domain/Entitlements/EntitlementService.php` | Single access decision | planned | T-P4-01 |
| PrivateFileController + SignedFileUrl | `app/Domain/Media/` | Authorized private downloads | planned | T-P3-08 |
| LocalizedNotification | `app/Domain/Notifications/` | Base notification with dedupe | planned | T-P5-04 |
| PaymentGateway (contract) | `app/Domain/Commerce/Contracts/` | Gateway abstraction | planned | T-P8-02 |
| AiProviderInterface + FakeAiProvider | `app/Domain/AI/Contracts/` | AI abstraction | planned | T-P3-10 |
| Feature flags + EnableGatedFlag | `app/Domain/Settings/` | Pennant flags with approval reference | built | T-P0-07 |
| app:check-translations / app:check-rtl | `app/Console/Commands/` | AR/EN key parity and logical direction utility enforcement | built | T-P0-03 |
| PhoneNumbers | `app/Domain/Shared/Phone/PhoneNumbers.php` | EG/SA phone validation, E.164 storage format, national display | built | T-P0-07 |
| SetLocale / UpdatePreferredLocale | `app/Http/Middleware/SetLocale.php`, `app/Domain/Identity/Actions/UpdatePreferredLocale.php` | Locale resolution and persisted preference | built | T-P1-01 |
| CreateChildAccount / GuardianAccess | `app/Domain/Identity/Actions/CreateChildAccount.php`, `app/Domain/Identity/GuardianAccess.php` | Parent-created child accounts and guardian→child access check (gate `guardian.child.access`, 404 on deny) | built | T-P1-05 |
| ActingLearner | `app/Domain/Identity/ActingLearner.php`, `app/Http/Middleware/ResolveActingLearner.php` | Single source for the learner a request acts as (parent child-context); learner-facing queries must use current() | built | T-P1-06 |
| x-layouts.base + x-ui.ajax-form + forms.js | `resources/views/components/layouts/base.blade.php`, `resources/views/components/ui/ajax-form.blade.php`, `resources/js/forms.js` | Learner/parent/auth/public page shell and progressive JSON form submit with inline field errors | built | T-P1-10 |
| x-family.child-switcher | `resources/views/components/family/child-switcher.blade.php` | Password-confirmed switch into a child context | built | T-P1-10 |
| EnforceTwoFactor + TwoFactor actions | `app/Http/Middleware/EnforceTwoFactor.php`, `app/Domain/Identity/TwoFactor.php`, `app/Domain/Identity/Actions/*TwoFactor*.php` | TOTP enrollment/challenge, recovery codes, panel and route enforcement | built | T-P1-11 |
| ResetChildPassword | `app/Domain/Identity/Actions/ResetChildPassword.php` | Linked-parent child password reset with session revocation | built | T-P1-11 |
| LearnerNotificationRouter + GuardianCopyNotification | `app/Domain/Notifications/` | Every learner notification goes through here; email-less child -> in-app plus guardian email copies | built | T-P1-11 |
| ErrorReporter | `app/Domain/Shared/Observability/` | Local exception reporting adapter | built | T-P0-07 |
| app:doctor / app:req-coverage | `app/Console/Commands/` | Health check / requirement coverage (G10, G11) | built | T-P0-07 |
| AccessDecision + canAccessItem() | `app/Domain/Entitlements/` | Layered access with reason codes | planned | T-P4-09 |
| ReserveCohortSeat / JoinWaitlist / PromoteFromWaitlist | `app/Domain/Cohorts/Actions/` | Seat, waitlist and promotion hooks for checkout and manual grants | built | T-P5-01 |
| MeetingProvider (Manual driver) + LiveSessionTime | `app/Domain/Live/` | Meeting link validation and viewer-timezone display for live sessions | built | T-P5-02 |
| GetJoinLink / JoinWindow / UpcomingSessions | `app/Domain/Live/` | Protected meeting link, join window and learner schedule | built | T-P5-03 |
| LocalizedNotification + NotificationDispatcher | `app/Domain/Notifications/` | Localized, deduplicated notifications for every module (live notices, later help requests) | built | T-P5-04 |
| StartQuizAttempt / SubmitQuizAttempt / QuizAttemptView / QuizGuard | `app/Domain/Assessment/` | Quiz attempts, grading, result visibility and the assistant quiz-guard hook | built | T-P6-02 |
| SaveSubmissionDraft / SubmitAssignment / SubmissionView / SubmissionRevisions / SubmissionFileAccess | `app/Domain/Assignments/` | Learner assignment work: drafts, submit, late policy, revisions; the one rule for who may open a submission file (reviewer side reuses `SubmissionFileAccess` and `SubmissionRevisions`) | built | T-P6-03 |
| AssignmentSubmissionController (save, submit, file download, brief attachment) | `app/Http/Controllers/AssignmentSubmissionController.php` | Learner assignment HTTP; owner/parent/staff-only streaming of private submission files, never the storage path | built | T-P6-03 |
| learning.assignment-card, ui.submission-uploader | `resources/views/components/learning/`, `resources/views/components/ui/` | Assignment page card and private-upload field with existing-file list | built | T-P6-03 |
| DecideSubmission / AddSubmissionFeedback / ReviewAccess / SubmissionReview / SubmissionDecisionNotice | `app/Domain/Assignments/` | Reviewer decisions (grade, accept, reject, request revision) with late penalty, feedback thread, audit and one notice per decision; ReviewAccess is the one rule for who may review a course (assessment permission + course update gate) | built | T-P6-04 |
| GradeEssayAnswer / QuizScoring / EssayReviewedNotice | `app/Domain/Assessment/` | Essay answer grading; QuizScoring is the one place that recomputes attempt score, percent, pass state and needs_review (submit and review share it) | built | T-P6-04 |
| SubmissionReviewResource / EssayReviewResource | `app/Filament/Resources/SubmissionReview/`, `app/Filament/Resources/EssayReview/`, `resources/views/filament/review/` | Instructor and admin review queues (course, status, overdue filters) and review pages on the shared workspace shell, scoped to reviewable courses | built | T-P6-04 |
| learning.feedback-thread | `resources/views/components/learning/feedback-thread.blade.php` | Learner-side reviewer feedback thread | built | T-P6-04 |
| RecordProgress | `app/Domain/Learning/Actions/` | Only writer of progress | planned | T-P4-04 |
| HtmlSanitizer | `app/Domain/Shared/Security/` | Single sanitizer (rich text, messages) | planned | T-P3-15 |
| ImageReencoder | `app/Domain/Media/` | Strip EXIF/GPS on upload | planned | T-P3-08 |
| AssistantEligibility | `app/Domain/AI/` | AI availability decision | planned | T-P3-10 |

## Filament shared

| Name | Path | Purpose | Status | Task |
|---|---|---|---|---|
| TranslatableTabs | `app/Filament/Shared/Forms/TranslatableTabs.php` | AR/EN tabs for translatable fields | built | T-P1-08 |
| StatusBadgeColumn | `app/Filament/Shared/Tables/` | Enum → colored badge | built | T-P1-08 |
| MoneyColumn | `app/Filament/Shared/Tables/` | Money display | built | T-P1-08 |
| ApproveRejectAction + RecordReviewDecision | `app/Filament/Shared/Actions/ApproveRejectAction.php`, `app/Domain/Shared/Actions/RecordReviewDecision.php` | Review-queue approve/reject: reason rules, auth recheck, domain mutation + audit in one transaction | built | T-P1-08 |
| PublicCourseDetail + CourseSeo + EntitlementService::canPreviewPublicly | `app/Domain/Catalog/Queries/PublicCourseDetail.php`, `app/Domain/Catalog/CourseSeo.php`, `app/Domain/Entitlements/EntitlementService.php` | Published-only course lookup by slug/id, public curriculum preview, meta/OG/Course JSON-LD, guest free-preview decision | built | T-P2-05 |
| SeoFieldsGroup | `app/Filament/Shared/Forms/SeoFieldsGroup.php` | Bilingual SEO title/description/keywords + canonical Filament section (wizard SEO step T-P3-03) | built | T-P2-05 |
| SaveCourseWizard + CourseWizardSteps | `app/Domain/Catalog/Actions/SaveCourseWizard.php`, `app/Filament/Resources/Courses/Schemas/CourseWizardSteps.php`, `app/Filament/Resources/Courses/Pages/{CreateCourse,EditCourse}.php` | Course wizard steps 1-2, save/resume, field-level permissions (steps 3-7 extend it in T-P3-03) | built | T-P3-02 |
| CurriculumOutline + curriculum Actions | `app/Domain/Curriculum/` | Section/lesson management, atomic reorder, bulk status, one ordered query for authors and students | built | T-P3-04 |
| MediaProviders + VideoDeliveryProvider drivers, AttachLessonMedia, GrantMediaException, LessonPublishRule | `app/Domain/Media/` | Provider-neutral lesson media, protection tiers, paid-lesson T3 rule | built | T-P3-05 |
| AiProviderInterface, FakeAiProvider, NullAiProvider, PromptRenderer, AssistantEligibility | `app/Domain/AI/` | Provider-neutral assistant core: fake for tests, eligibility per AI spec §2 | built | T-P3-10 |
| PdfTextExtractor/PageRenderer, IngestCurriculumDocument, ArabicTextRepair, QualityScorer, KeyTermExtractor, DocumentReadiness, ReviewCurriculumDocument page | `app/Domain/AI/Ingestion/`, `app/Filament/Resources/Courses/Pages/` | Curriculum PDF → approved page-tagged text pipeline | built | T-P3-09 |
| ManageAssessmentItems, Quiz, Assignment shells, CurriculumOutline::ITEM_TYPES | `app/Domain/Curriculum/`, `app/Models/` | Quizzes/assignments as curriculum items | built | T-P3-11 |
| SubtitleTrack, VttValidator, ManageSubtitleTracks, LessonPlayback viewer tracks | `app/Domain/Media/` | AR/EN captions for lessons | built | T-P3-14 |
| UploadLessonMedia + ImageReencoder | `app/Domain/Media/Actions/UploadLessonMedia.php`, `app/Domain/Media/ImageReencoder.php` | Safe private uploads: content-based type check, random names, EXIF stripping | built | T-P3-06 |
| SignedFileUrl + PrivateFileAccess + PrivateFileController, LessonAccessChecker | `app/Domain/Media/`, `app/Domain/Entitlements/`, `app/Http/Controllers/PrivateFileController.php` | Signed, account-bound private downloads; entitlement stub that fails closed | built | T-P3-08 |
| UploadCurriculumDocument + ApproveCurriculumDocument + PdfInspector + ManageLessonResources | `app/Domain/Media/Actions/`, `app/Domain/Media/PdfInspector.php` | Versioned curriculum PDF (AI source foundation) and lesson materials | built | T-P3-08 |
| LocalizedDateColumn | `app/Filament/Shared/Tables/` | Date in user tz | built | T-P1-08 |
| ExportCsvAction | `app/Filament/Shared/Actions/` | Permission-aware CSV export | built | T-P1-08 |
| SeoFieldsGroup | `app/Filament/Shared/Forms/` | Bilingual SEO fields | planned | T-P2-05 |
| WorkspaceListRecords | `app/Filament/Shared/Pages/WorkspaceListRecords.php` | Native ListRecords shell with optional summary strip and Section/View schema sidebar; resource keeps ownership of the table | built | T-P1-08 |
| EntitlementService, AccessDecision, GrantEntitlement, RevokeEntitlement, ExpireEntitlements | `app/Domain/Entitlements/` | The single learner-access authority | built | T-P4-01 |
| EnrollInFreeCourse, CourseAccessSummary, ManualGrantResource | `app/Domain/Entitlements/` | Free enrollment, manual grants, access summary | built | T-P4-02 |
| EntitledCurriculumQuery, LearnController, learning.lesson-media / curriculum-list, player.js | `app/Domain/Curriculum/Queries/`, `resources/views/learn/`, `resources/js/` | Course player and learner curriculum | built | T-P4-03 |
| RecordProgress, RecalculateCourseProgress, ContinueLearning, LessonProgress, CourseProgress | `app/Domain/Progress/` | Server-authoritative lesson/course progress and resume | built | T-P4-04 |
| layouts.learner, learner.course-row/course-thumb, LearnerCourses, LearnerHomeController | `resources/views/components/`, `app/Domain/Entitlements/` | Learner shell, dashboard and my courses | built | T-P4-05 |
| RichContent, x-learning.rich-content, lessons.content | `app/Domain/Content/`, `resources/views/components/learning/` | Safe rich lesson content (single sanitizer) | built | T-P3-15 |
| DuplicateCourse, ArchiveCourse, DeleteCourse, CourseHistoryChecker (tag course.history) | `app/Domain/Catalog/` | Course lifecycle with history guard | built | T-P3-12 |
| LibraryAccess, AddLibraryItem, AttachLibraryItem, DeleteLibraryItem, LibraryResource | `app/Domain/Library/`, `app/Filament/Resources/Library/` | Reusable content library attached by reference | built | T-P3-13 |
| ParentDashboardController, LearnerCourseRows | `app/Http/Controllers/`, `app/Domain/Progress/Queries/` | Parent overview per linked child; shared course rows | built | T-P4-08 |
| SessionLimiter, EnforceSessionLimits, ResetUserDevices, LearnerDeviceResource, Watermark | `app/Domain/Identity/`, `app/Http/Middleware/`, `app/Filament/Resources/LearnerDevices/` | Session/device limits and playback watermark | built | T-P4-07 |
| Workspace table scroll region | `resources/views/filament/shared/table-scroll-hint.blade.php` | Labels overflowing table wrappers and makes them reachable by keyboard, including empty tables | built | T-P2-02 |
| WorkspaceDashboard | `app/Filament/Pages/WorkspaceDashboard.php` | Shared panel landing with permission-scoped actual resource links and summary | built | T-P1-08 |
| WorkspaceLogin | `app/Filament/Auth/WorkspaceLogin.php` | Native Filament authentication in the shared bilingual responsive layout | built | T-P1-08 |
| WorkspaceAvatarProvider | `app/Filament/Shared/WorkspaceAvatarProvider.php` | Local escaped initial SVG; no external avatar request | built | T-P1-08 |

## UI components (Blade)

| Name | Path | Status | Task |
|---|---|---|---|
| ui primitives (button, input, card, modal, ...) | `resources/views/components/ui/` | built | T-P1-09 |
| layouts (public, student, parent, auth) | `resources/views/components/layouts/base.blade.php` (one shell, `variant` prop) | built | T-P1-10 |
| course-card | `resources/views/components/learning/course-card.blade.php` | built | T-P2-04 |
| curriculum-list | `resources/views/components/learning/curriculum-list.blade.php` | planned | T-P4-03 |
| lesson-player | `app/Livewire/Player/LessonPlayer.php` | planned | T-P4-03 |
| ai-assistant-panel | `app/Livewire/Player/AiAssistantPanel.php` | planned | T-P7-03 |
| rich-content renderer | `resources/views/components/learning/rich-content.blade.php` | planned | T-P3-15 |
| library-picker | `app/Filament/Shared/Forms/LibraryPicker.php` | planned | T-P3-13 |
| locked-state | `resources/views/components/learning/locked-state.blade.php` | planned | T-P4-09 |
| child-context-strip | inline in `resources/views/components/layouts/base.blade.php` (`data-child-context`) | built | T-P1-10 |
| error pages | `resources/views/errors/` (403, 404, 419, 429, 500, 503) | built | T-P1-10 |



## PRD visual masters — reference assets

The20selectedimages and their source/PRD/shared-component mapping are indexed at docs/design-system/reference-pack-2026-11-17/manifest.json. Read COMMON_COMPONENTS.md to distinguish existing primitives/native components from planned learning/payment/AI patterns. These reference IDs are design vocabulary, not new wrapper classes or evidence of implemented features.
| AgeBand model + AgeRangeRule + AgeRange | `app/Domain/Catalog/Models/AgeBand.php`, `app/Domain/Catalog/Rules/AgeRangeRule.php`, `app/Domain/Catalog/AgeRange.php` | Configurable age bands and range validation (no seeded cutoffs, O-015) | built | T-P1-07 |
| Link-code actions + LinkCodeHasher | `app/Domain/Identity/Actions/{IssueLinkCode,RedeemLinkCode,ConfirmGuardianLink,EnterChildContext,ExitChildContext}.php`, `app/Domain/Identity/LinkCodeHasher.php` | One-time guardian link codes and child context | built | T-P1-06 |
| Audit viewer + audit-events labels | `app/Filament/Resources/Audit/AuditLogResource.php`, `lang/*/audit-events.php` | Read-only audit viewer; add a label for every new audit event | built | T-P1-04 |
| CourseCompletionRule + SaveCompletionRules | `app/Domain/Completion/CourseCompletionRule.php`, `Actions/SaveCompletionRules.php` | Per-course completion rules (lesson %, required quizzes, required assignments, attendance %, manual approval); defaults in `config/learning.php` | built | T-P6-05 |
| CompletionReport | `app/Domain/Completion/CompletionReport.php` | Read-only measure of one learner against the rules (per-criterion have/need/met) | built | T-P6-05 |
| CourseCompletionEvaluator + CourseCompletion | `app/Domain/Completion/Actions/CourseCompletionEvaluator.php`, `app/Domain/Completion/CourseCompletion.php` | Idempotent completion writer, one row per learner and course; dispatches `CourseCompleted` once | built | T-P6-05 |
| ApproveCourseCompletion | `app/Domain/Completion/Actions/ApproveCourseCompletion.php` | Audited manual approval/override with a reason (course update gate) | built | T-P6-05 |
| CompletionTrigger + CourseCompletionInputChanged + EvaluateCompletion | `app/Domain/Completion/CompletionTrigger.php`, `Events/`, `Listeners/` | Call `CompletionTrigger::changed($learner, $course)` after anything that can change completion; a failure never breaks the caller | built | T-P6-05 |
| CourseCompleted event | `app/Domain/Completion/Events/CourseCompleted.php` | Listen here to issue certificates | built | T-P6-05 (used by T-P6-06) |
| ManageCourseCompletion page | `app/Filament/Resources/Courses/Pages/ManageCourseCompletion.php`, `resources/views/filament/courses/completion.blade.php` | Staff rules editor and per-learner completion status and approval | built | T-P6-05 |
| Certificate model + certificates table | `app/Domain/Certificates/Certificate.php`, `database/migrations/2026_11_17_380000_create_certificates_table.php` | One live certificate per completion (`active` + unique), unique number and token, snapshot of safe fields, revoke columns; revoked rows keep their link | built | T-P6-06 |
| IssueCertificate + IssueCertificateOnCompletion + CertificateIssuedNotice | `app/Domain/Certificates/Actions/IssueCertificate.php`, `Listeners/`, `Notifications/` | Idempotent issuance on `CourseCompleted` (never fails the caller), one learner notice per completion | built | T-P6-06 |
| RevokeCertificate + ReissueCertificate | `app/Domain/Certificates/Actions/` | Explicit, audited (`certificate.revoked`, `certificate.reissued`), reason required, course update gate | built | T-P6-06 |
| CertificatePdf + certificate view | `app/Domain/Certificates/CertificatePdf.php`, `resources/views/certificates/pdf.blade.php` | Branded AR/EN A4 PDF via spatie/laravel-pdf (Chromium), inline SVG QR (bacon, already installed) to the verify URL | built | T-P6-06 |
| CertificateController + verify page | `app/Http/Controllers/CertificateController.php`, `resources/views/certificates/verify.blade.php`, routes `certificates.download`, `certificates.verify` | Owner/linked-parent download (404 others, no-store); public `/verify/{token}` with safe fields, throttled, noindex | built | T-P6-06 |
| CertificateSettings | `app/Domain/Settings/CertificateSettings.php` | Issuer text AR/EN placeholder (O-019 open) | built | T-P6-06 |
| Certificate actions on completion page | `ManageCourseCompletion::revokeCertificateAction/reissueCertificateAction` | Staff revoke and re-issue per learner | built | T-P6-06 |
| AskLessonAssistant | `app/Domain/AI/Actions/AskLessonAssistant.php` | The only assistant request pipeline: eligibility, thread limits, lesson-PDF context, re-check before prompt, provider call via `AiProviderInterface`, output guard + one retry, persistence, audit `ai.assistant_asked`. Throws `AssistantRefused` before any provider call | built | T-P7-01 |
| ContextBuilder + AssistantContext | `app/Domain/AI/Assistant/` | Approved page-tagged text of the lesson's active READY version only; full-context mode, or chunked retrieval (keyword + embeddings) above `ai.full_context_max_tokens` | built | T-P7-01 |
| OutputGuard + GuardVerdict + GuardFailure | `app/Domain/AI/Assistant/` | Deterministic answer check: strict JSON, caps (80 AR / 70 EN words, 4 sentences, trim or retry), cited pages exist, quote verified on cited page (Arabic-normalised fuzzy), leakage | built | T-P7-01 |
| TextNormalizer | `app/Domain/AI/Assistant/TextNormalizer.php` | Arabic normalisation (alef/ya/ta marbuta, diacritics, tatweel, digits); reuse for the T-P7-02 answer-cache key and quiz-guard similarity | built | T-P7-01 |
| StyleTier + ReplyLanguage + StudentHash | `app/Domain/AI/Assistant/` | Age to style tier (cut-offs OPEN, O-015; unknown = kids), reply language from question/locale, opaque student hash for provider and `ai_usage` | built | T-P7-01 |
| AiConversation, AiMessage, AiUsage models | `app/Models/` | Tables from the T-P3-10 migration; messages never store lesson text or the supporting quote | built | T-P7-01 |
| AssistantStatus | `app/Domain/AI/Queries/AssistantStatus.php` | Availability + reason + approved starter terms for the player (GET `.../assistant`) | built | T-P7-01 |
| AssistantController + routes/assistant.php | `app/Http/Controllers/AssistantController.php`, `routes/assistant.php` | `GET /api/v1/lessons/{lesson}/assistant`, `POST .../assistant/messages` (SSE `token` events then `result`; JSON 403/409/422/429/503 otherwise) | built | T-P7-01 |
| AssistantPanel + AssistantOpener (Blade) | `resources/views/components/learning/assistant-panel.blade.php`, `assistant-opener.blade.php` | Lesson assistant UI: side column at 2xl, bottom sheet below; rendered only when the learner is eligible; all states; reuses ui.button/badge/alert/skeleton/textarea/icon | built | T-P7-03 |
| assistant.js | `resources/js/assistant.js` | Vanilla module (registered in app.js): pause + timestamp capture, SSE answer render, page chips, simpler action, state machine; feedback actions wait for T-P7-04 (`data-feedback-url`) | built | T-P7-03 |
| InputGuard + PiiMasker + InjectionHeuristics + InputVerdict + GuardKind | `app/Domain/AI/Guards/` | Input guards in order: PII masking (email, phone, national id, address, own name), moderation (self-harm → safeguarding), injection heuristics, active-quiz guard; blocked questions never reach the provider or consume quota | built | T-P7-02 |
| ModerationClassifier + KeywordModerationClassifier + FakeModerationClassifier | `app/Domain/AI/Contracts/`, `app/Domain/AI/Providers/` | Moderation adapter: local keyword baseline, deterministic fake in local/test; real adapter with O-014 | built | T-P7-02 |
| QuizQuestionMatcher (+ `QuizGuardQuestions::promptsFor`) | `app/Domain/AI/Guards/`, `app/Domain/Assessment/Queries/` | Similarity of the normalised question to the open attempt's quiz prompts (via `QuizGuard::openAttempt`, keys never read) and a lesson page hint | built | T-P7-02 |
| AnswerCache + CachedAnswer + AiAnswerCache model | `app/Domain/AI/Assistant/`, `app/Models/` | Per-lesson cache keyed by document version + normalised question + reply language + style tier, TTL 30 days, no personal data; new version invalidates | built | T-P7-02 |
| QuotaLedger + QuotaReservation + BudgetGate | `app/Domain/AI/Quota/` | Atomic reserve → commit → refund of the daily counters eligibility reads; monthly course/platform budgets with 80% audit alert and graceful pause; reset times | built | T-P7-02 |
| AiSafeguardingFlag model | `app/Models/` | Flag raised for non-adult self-harm input (category only); recipients and wording OPEN (O-028) | built | T-P7-02 |
| `assistant-ask` / `assistant-status` rate limiters | `app/Providers/AppServiceProvider.php`, `routes/assistant.php` | Per-account throttle on the assistant endpoints, JSON 429 `rate_limited` | built | T-P7-02 |
| HelpRequest, HelpRequestMessage, HelpRequestEvent, AiFeedback models | `app/Models/` | Help request raised from an assistant answer (unique `ai_message_id`, question/answer snapshot, cited pages, video position, SLA `due_at`/`overdue_at`), its reply thread and event trail; `ai_feedback` holds understood/not_understood (unique per answer and kind) | built | T-P7-04 |
| HelpRequestStatus, HelpRequestAccess, HelpRouting, HelpRequestTrail, HelpRequestNotices (+ `HelpRequestNotice`), ReplyBody, HelpLinks | `app/Domain/AI/Help/` | States, who may see/answer (manage = all, reply = assigned only), routing to the manage-scope course instructor else admin queue, event + audit writer, once-per-event notices via `NotificationDispatcher` (no AI text in notice bodies), reply validation | built | T-P7-04 |
| RecordAssistantFeedback, ReplyToHelpRequest, ReplyAsStudent, ChangeHelpRequestStatus, EscalateOverdueHelpRequests | `app/Domain/AI/Help/Actions/` | Idempotent feedback (max 3 open per learner and lesson), staff and learner replies, state changes, SLA escalation once per cycle (`help:escalate-overdue`, `ai.help.sla_hours`) | built | T-P7-04 |
| AssistantFeedbackController + `assistant.feedback` | `app/Http/Controllers/AssistantFeedbackController.php`, `routes/assistant.php` | `POST /api/v1/lessons/{lesson}/assistant/feedback` `{message_id, type}`: 200 recorded / request_exists, 201 request_created, 404/409/422 | built | T-P7-04 |
| HelpRequestController + `help-request-thread` | `app/Http/Controllers/HelpRequestController.php`, `resources/views/components/learning/help-request-thread.blade.php`, `resources/views/learner/help-request.blade.php` | Learner thread (`/my/help/{id}`), reply only after the instructor wrote; parent context shows status and instructor replies only | built | T-P7-04 |
| HelpRequestResource (+ List/View pages) | `app/Filament/Resources/HelpRequests/`, `resources/views/filament/help/request.blade.php` | Admin and instructor queue (filters course/status/overdue, scoped query = 404 for others), request context, reply thread, resolve/close; safeguarding flags only with `ai.conversation.view` | built | T-P7-04 |
| AssistantEngine | `app/Domain/AI/Assistant/AssistantEngine.php` | The one provider exchange (prompt render, request, provider call, output guard + one retry, usage totals) shared by `AskLessonAssistant`, the test console and the evaluation harness; persists nothing. Leak detection now covers the whole rule text (before the last source tag) | built | T-P7-05 |
| SandboxRunner + SandboxResult | `app/Domain/AI/Sandbox/` | One question through the real input guards and the engine with no persistence (no conversation, message, usage, quota, cache or flag, no eligibility check); returns guard results and the verified quote. Reuse for any "try it" surface | built | T-P7-05 |
| InputGuard::inspectAgainst + QuizQuestionMatcher::matchesPrompts/pageHintFor | `app/Domain/AI/Guards/` | Same guard order and similarity rules against given quiz prompts and pages instead of a learner attempt (console, evaluation) | built | T-P7-05 |
| EvalDataset, EvalCase, EvalSource, EvalCategory + `database/ai-eval/` | `app/Domain/AI/Evaluation/`, `database/ai-eval/sources.json`, `cases.json` | Versioned EN/AR golden and red-team dataset (answerable, dialect, cross-language, not-in-source, off-topic, injection, injected source text, quiz guard, PII, abuse, self-harm, adversarial model output, age tiers) with scripted fake-provider answers and expectations | built | T-P7-05 |
| RunAssistantEvaluation + EvalReport + RecordingProvider | `app/Domain/AI/Evaluation/Actions/RunAssistantEvaluation.php`, `EvalReport.php`, `RecordingProvider.php` | Scores the dataset against AI spec 13.3 (grounded, refusal, length, red-team, age tier; p95 and Arabic quality reported, not gated for the fake), checks prompt invariants (PII never sent, tier text, source tag cannot be broken out of), stores one `ai_eval_runs` row (scores only) | built | T-P7-05 |
| `ai:evaluate` (alias `ai:eval`) | `app/Console/Commands/EvaluateAssistantCommand.php`, `config/ai.php` `eval` | `--suite --provider=fake\|configured --threshold --report --json --no-store`; exit 1 below thresholds; JSON report in `storage/app/ai-eval/`; real provider only with `--allow-real` / `AI_EVAL_ALLOW_REAL`. CI step in `.github/workflows/quality.yml` runs it with the fake provider on every push and PR | built | T-P7-05 |
| AiEvalRun model | `app/Models/AiEvalRun.php` | Row of `ai_eval_runs` (T-P3-10 table): provider, prompt version, metrics JSON (scores and counts), pass/fail | built | T-P7-05 |
| AskInTestConsole + TestConsoleAccess | `app/Domain/AI/Console/` | Instructor test console action: course update + `lesson_document.upload` required (404/403 for others), reads the lesson's active document, per-minute throttle, writes only audit `ai.console_asked` (ids, no text); works while the assistant is off for the lesson | built | T-P7-05 |
| AssistantTestConsole page | `app/Filament/Pages/AssistantTestConsole.php`, `resources/views/filament/ai/test-console.blade.php` | Admin and instructor panel page "Assistant test console": lesson picker, question, language, age group; shows answer, cited pages, quote, input/output guard results; labelled test mode | built | T-P7-05 |
| ResilientProvider + CircuitBreaker + ProviderRegistry + CircuitState + AiProviderCircuitOpen | `app/Domain/AI/Resilience/` | Provider routing for the request pipeline: primary + optional fallback route, per-route circuit breaker in the shared cache (N consecutive failures in a window open it for a cool-off, one half-open trial), slow answers count as failures, open circuit with no route throws `AiProviderCircuitOpen` (fallback state, not reported). Bound contextually into `AssistantEngine` only; the registry lists the known provider keys and resolves the operator choice from `AiSettings` | built | T-P7-06 |
| Assistant admin actions (EnableAssistantGlobally, DisableAssistantGlobally, SetCourseAssistant, SetLessonAssistant, SaveAssistantQuotas, SaveAssistantProvider, SaveAssistantRetention, ResetProviderCircuit, PruneAiData) + AssistantAdminAccess | `app/Domain/AI/Admin/` | Every operator write for the assistant: authorizes (ai.configure, course manage), validates, audits (`ai.global_enabled` ... `ai.retention_pruned`). Reuse for any new assistant setting | built | T-P7-06 |
| UsageReport, LessonInsights, AssistantReadiness, ProviderHealthReport, CourseAssistantOverview | `app/Domain/AI/Queries/` | Read models of the admin pages: usage/cost/error from `ai_usage` only, anonymized per-lesson insights, go-live checklist with open owner decisions, provider health, course/lesson switch list | built | T-P7-06 |
| Assistant admin pages (AssistantControls, AssistantUsageReport, AssistantInsights, AssistantAvailability) + `filament/ai/styles` | `app/Filament/Pages/`, `resources/views/filament/ai/` | Nav group "Lesson assistant": controls (super admin), usage report (ai.view_usage), insights and switches (admin and own-course instructors) | built | T-P7-06 |
| `ai:prune` | `app/Console/Commands/PruneAiDataCommand.php`, `routes/console.php` | Daily retention pruning of messages (180 d), usage (24 mo), expired cache, old counters; keeps messages of help requests and conversations of safeguarding flags | built | T-P7-06 |
