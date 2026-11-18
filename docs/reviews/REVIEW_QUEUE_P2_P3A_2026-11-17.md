# P2/P3A independent review — 2026-11-17

What this change does: These eight tasks add taxonomy/CMS administration and the course, curriculum, media and private-PDF foundations. This review checks their current source and existing evidence against the task specs and higher-priority requirements. It changes no product code or task status.

## Review boundary and snapshot

- HEAD: `aa903cd3aad82043f17b81b3141368a40f4532fb`.
- Original task commits: T-P2-01 `cdf2c3b`; T-P2-02 `f8b1cd6`; T-P3-01 `6ef6b6f`; T-P3-02 `2e3a741`; T-P3-04 `d8df8f5` (later visual adjustment `1c3abba`); T-P3-05 `d72cab2`; T-P3-06 `bcb15bc`; T-P3-08 `452f96d`. Reviewed their change inventories and current owning code, including downstream callers introduced after those commits.
- Assigned product source is tracked and clean at inspection. Relevant untracked files are `tests/Feature/Curriculum/CurriculumEdgeCasesTest.php` and `tests/Feature/Media/MediaPrimitivesEdgeCasesTest.php`; these are pre-existing work, not review-produced evidence. Many assigned screenshots are modified. AI source/config, continuity/checklist documents, package files and other screenshots/tests are also dirty elsewhere. None were edited or reset.
- Read AGENTS, REVIEW_PROTOCOL, VERIFICATION_GATES, brief template; P02/P03 specs; PRD §9.1–9.8 and referenced WEB/GAP rows; decisions D-007/D-018/D-026; ED-002/003/005/006/007/024/026/028/029/031; architecture file/media/model sections; security/UI conventions; UX J03 and pattern table; AI §6.1/6.7; reuse registry, traceability, visual acceptance and reference pack instructions/manifest.
- Read-only inspection. No DB changes, browser journeys, test execution, provider calls, fixes or commits. Root owns fresh global checks and status updates. Reported historic test counts in PROGRESS are not fresh verification.
- Load assumption: concurrent course authors and learners; no production traffic, media-provider credentials or storage deployment is established here.

## Must fix

### 1. Protected playback links bypass the current learner access decision — BLOCKER

**Task / rule:** T-P3-05; PRD §9.8; architecture security; AGENTS #5; R2/R5B. `routes/web.php` registers `media.play` with only `signed` middleware; `app/Http/Controllers/MediaPlaybackController.php:20` reads an asset and streams it without a user or entitlement check. `app/Domain/Media/Providers/PrivateFileProvider.php:88` signs only the asset ID.

**Concrete case:** An entitled learner copies a paid video's signed URL to a logged-out browser or another account. The receiver can stream it until expiry. Revoking enrollment, archiving the lesson or unpublishing the course after issuance does not stop this endpoint. The expiry/tamper tests in `tests/Feature/Media/MediaProvidersTest.php` do not test forwarding/revocation and currently accept direct signed access. Resource/PDF delivery already rechecks user access through PrivateFileAccess, showing the reusable approach.

**Smallest fix:** Bind protected playback to its permitted viewer/context and recheck `EntitlementService::canAccessItem()` on use; preserve explicit public preview and author-preview behavior through the existing access paths. Add cross-account, unauthenticated, revoked and unpublished-item regression cases. Short TTL alone does not fulfill the stated per-playback check.

### 2. Replacing media bypasses the paid-T3 publication rule — BLOCKER

**Task / rule:** T-P3-05 A2; YG-GAP-VID-002. `app/Domain/Media/Actions/AttachLessonMedia.php:30` validates the provider, then replaces the asset at line 39 without applying LessonPublishRule to an already published item. `ManageLessons::update()` also permits changing `is_preview` without checking the resulting published state.

**Concrete case:** Publish a paid non-preview lesson with T1 media, then use Edit lesson to attach YouTube. The curriculum item stays published and the new T3 asset has no exception. Equally, publish a T3 preview and then clear its preview flag. The checks in SetCurriculumStatus and ManageSections only run if publication is requested again. `PaidT3PublishTest.php:108` checks exception reset followed by a new publish attempt; it misses the already-published state.

**Smallest fix:** Reject an invalid resulting published lesson, or explicitly return it to draft, in the shared mutation Actions. Test both replacement and preview removal through the current editor path.

### 3. Long generated slugs collide after the uniqueness check — BLOCKER

**Task / rule:** T-P3-02; PRD §9.2 valid save/resume. `app/Domain/Catalog/Actions/SaveCourseWizard.php:224` checks the unbounded generated slug for uniqueness, then truncates it to 120 characters at line 232.

**Concrete case:** Save two valid 130-character titles with the same first 120 characters and no supplied slug. Both full generated candidates appear unused; both truncate to the same database value, and the second draft fails with a unique-constraint exception. Repeating the same long title has the same failure because the stored truncated value never matches the full candidate in the loop.

**Smallest fix:** Bound the base before checking, reserve suffix space and check the exact final value being stored. Add one same-prefix long-title test. No new slug abstraction is needed.

### 4. Category images bypass required safe image processing — BLOCKER

**Task / rule:** T-P2-01 image upload; ED-031 / YG-GAP-SEC-004; AGENTS private upload rule. `app/Filament/Resources/Categories/CategoryResource.php:79` stores the received image directly on the public disk. Neither SaveCategory nor the resource invokes ImageReencoder.

**Concrete case:** A valid JPEG uploaded with camera GPS/EXIF metadata is retained and exposed as uploaded. The category test checks existence on `public`, not metadata stripping. This is a real policy gap even though category artwork is ultimately intended for public display.

**Smallest fix:** Reuse ImageReencoder before publishing the image and use the established random-name/private upload boundary. If public derivatives are desired, explicitly publish the sanitized derivative rather than the raw upload. Test retained pixels and removed metadata.

### 5. The course list is missing task-required columns and sorting — BLOCKER

**Task / rule:** T-P3-01 step “columns per §9.1”; PRD §9.1. `app/Filament/Resources/Courses/CourseResource.php:74` defines title/category/instructors/access/status only. It omits section/lesson counts, enrollment count and price; title/category/instructors have no sortable behavior. `ListCourses.php:23` exports only title/status/access.

**Concrete case:** Staff cannot assess a course's curriculum size, enrollment or price from the required management list. The current scoped-count tests prove summary counters, not those missing columns. Real curriculum/entitlement data now exists elsewhere in this HEAD, so this cannot be closed as a fully implemented §9.1 list. Price configuration still belongs to unstarted T-P3-03 and must remain explicitly pending rather than invented.

**Smallest fix:** Add real scoped counts and required native sort support; link unresolved price data to its owning dependency. Do not fabricate zero enrollment or price values. This is missing specified behavior, not a request to expand the wizard scope.

### 6. Instructor creation is denied by the shared course policy — BLOCKER / source conflict

**Task / rule:** T-P3-01/02 course ownership/builder; PRD §9.4 independent-instructor offering, UX J03 instructor course creation. `app/Policies/CoursePolicy.php:22` requires admin-panel access for all creation. `tests/Feature/Filament/CourseWizardPagesTest.php:39` explicitly expects academy instructor creation to be forbidden. There is no independent-instructor create flow in the current resource.

**Concrete case:** An independent instructor cannot start the course they are expected to submit for academy approval; an assigned instructor can only edit a precreated course. This should not be mislabeled a missing publish grant: publication must remain academy controlled. `SaveCourseWizard` creation itself checks the permission directly, while the page policy adds a different restriction.

**Smallest fix:** Reconcile the creation policy with the approved instructor flow, ensuring instructor-created drafts receive permitted ownership and assignment server-side. If the owner intends staff-only creation for this slice, record that as an explicit scope decision and leave the instructor journey pending. Do not silently treat the current restrictive test as PRD approval.

### 7. PDF structural inspection undercounts mixed PDFs and misses encoded active content — BLOCKER

**Task / rule:** T-P3-08 A3; AI §6.1 max pages; PRD §9.8 file validation; ED-031. `app/Domain/Media/PdfInspector.php:31` returns immediately when any uncompressed page object is found (line 33), so additional page objects inside compressed object streams are never counted. Active-content matching at line 24 scans only literal names in the original bytes.

**Concrete case:** A valid PDF containing one direct page object and 120 compressed page objects is reported as one page and bypasses the 120-page upload limit. A PDF name such as `/Java#53cript`, or active-content dictionaries inside compressed object streams, is not caught by the literal-name scan. The current tests exercise direct pages and all-compressed pages separately, with literal script names; they miss these cases. These cases are static code analysis here, not newly executed malicious-file fixtures.

**Smallest fix:** Use the existing available PDF parsing/tooling boundary to validate the decoded structure and authoritative page count, or reject structures it cannot validate. Add mixed-object and encoded-name fixtures. Do not claim this heuristic is a complete safe-PDF validator.

### 8. Rich course description is still plain text — BLOCKER

**Task / rule:** T-P3-02 Basic step; PRD §9.2 rich description. `app/Filament/Resources/Courses/Schemas/CourseWizardSteps.php:57` remains a Textarea; SaveCourseWizard only validates and stores strings. The original progress note deferred rich text until T-P3-15's sanitizer existed; RichContent and the restricted rich lesson implementation now exist, but the wizard has not adopted a suitable shared sanitized representation/rendering path.

**Concrete case:** An author cannot create the requested formatted course description through the Basic step. The wizard tests cover preservation of strings, not safe rich description. This is a missing specified field capability, not a proven XSS exploit in the currently escaped plain-text output.

**Smallest fix:** Reuse the established safe editor/sanitizer/rendering conventions, including downstream readers, or explicitly leave this task criterion incomplete until its documented dependency is integrated.

## Should fix / targeted follow-up

9. **Vimeo tier is asserted from URL syntax only.** `app/Domain/Media/Providers/VimeoProvider.php:30` labels every syntactically valid Vimeo ID T2/ready without checking ownership, private/domain-restriction settings or playback availability. A public or inaccessible Vimeo URL therefore satisfies the paid-media guard. PRD §9.6 requires those checks; YG-GAP-VID-001 defines T2 as private Vimeo with domain restriction. Provider integration is UNKNOWN here. Keep this security acceptance open until a verifiable restriction/ownership policy exists; tests need a fake provider-state response, not only a fake URL.

10. **PDF duplicate no-op is not guaranteed under concurrent uploads.** `UploadCurriculumDocument.php:63` checks hash before obtaining the lesson lock at line 78; two identical simultaneous uploads both pass, then the second hits the database uniqueness constraint rather than returning the existing version. Data is not duplicated and the catch cleans the new file, but a valid retry produces an error. Recheck the hash while holding the existing lesson lock and return the existing record. Requirement: T-P3-08 duplicate hash no-op / AI §6.1.

11. **Resource editing is not complete.** The current lesson editor supports adding files but has no existing-resource remove/metadata/preview/link controls (`ManageCurriculum.php:480`, `saveDocuments`). Domain `ManageLessonResources` supports links/remove/preview, but authors cannot reach them from this UI. PRD §9.5 materials action / §9.6 resources and T-P3-08 steps remain partial. Do not count domain tests as a completed author journey. PDF approval UI now exists under T-P3-09, so the old PROGRESS “approval UI missing” note should not be repeated as a current-code defect.

12. **Traceability entries lag source/tests.** `docs/TRACEABILITY_MATRIX.md:28`, `:31`–`:34`, `:154`–`:157` have blank evidence for requirements now covered by named tests. `docs/REUSE_REGISTRY.md:28` still labels private file delivery “planned” despite a built entry at line 79. Update the existing generated/registered evidence through the established process; do not infer G10 failure just from blanks without running the checker.

## Acceptance-to-evidence matrix

All entries below mean **source/test exists**, not “fresh test passed.” Each task also needs the current applicable gates and independent browser QA/root visual review before `done`.

| Task | Acceptance / existing evidence | AI line | PRD verdict and closure status |
|---|---|---|---|
| T-P2-01 | A1 tree/depth/order: `tests/Feature/Filament/CategoriesTest.php:36`; A2 locale uniqueness `:70`; A3 HasSearchText and Arabic search `:85`; seed preservation and forbidden finance role included. CategoryResource uses TranslatableTabs and ListCategories uses WorkspaceListRecords. Browser matrix: `CategoriesBrowserTest.php`, 8 locale/size/theme artifacts. | `none`: honored in taxonomy source. | **FAIL for full closure:** finding 4. A1–A3 have meaningful static evidence; fresh gates/QA/visual remain unverified. |
| T-P2-02 | A1 draft 404, editor-only signed preview, expiry/tamper/logout `tests/Feature/Pages/CmsPagesTest.php:24`; A2 visibility/order `:56`; draft/legal-placeholder seed guards `:82`. CmsController queries published/visible only; preview no-store/noindex; route enforces active/auth/session/2FA/editor permission. All nine block types exist; plain title/body block configuration is the implemented minimum. `CmsPagesBrowserTest.php` and task screenshots cover administration and public rendering. | `none`: honored in CMS source. | **Static acceptance supported; HOLD for closure.** No additional confirmed functional/security defect in A1/A2. Homepage visual design belongs to T-P2-03; do not block this task for missing future public-shell work. G7/current QA/root review still required. |
| T-P3-01 | A1 assignment-scoped list/direct URL and view-only mutation: `tests/Feature/Invariants/I02_CourseScopeTest.php:40`; A2 independent publication denial `:65`; A3 scoped real counters/global AI flag `:80`. CourseDirectory is reused by policies/resource; publish action authorizes policy. Both admin/instructor list/detail browser matrices exist. | Course `ai_enabled=true`, effective global flag via `Course::hasAiEnabled()`: honored; no provider call. | **FAIL for full closure:** findings 5 and 6. Assignment and approval boundaries are meaningfully covered. Current high-risk review/QA/visual are unverified. |
| T-P3-02 | A1 save draft/resume/step validation and ordered rows: `tests/Feature/Catalog/CourseWizardTest.php`, `tests/Feature/Filament/CourseWizardPagesTest.php:45`; A2 tampered owner/status/featured and view-only/unassigned cases: domain `:143`, page `:92`; A3 `CourseWizardBrowserTest.php` plus 8 screenshots. Commission/approval inputs are not accepted by SaveCourseWizard's selected attributes. | `none`: no new AI integration in wizard. | **FAIL:** findings 3, 6 and 8. Old visual record explicitly disclaims pixel matching; G7 unresolved. |
| T-P3-04 | A1 persistence/shared student query/stale payload/move keyboard: `tests/Feature/Curriculum/CurriculumManagerTest.php:73`, `:93`, `:107`; A2 archive retained/hidden `:164`; A3 unauthorized/wrong-course `:120`, `:137`; A4 draft/bulk audited publication `:63`, `:145`. Unified curriculum_items and no lesson sort column confirmed; ReorderCurriculum validates full payload and transaction/ownership. Browser drag/keyboard and AR/EN screenshots exist. | Item `ai_enabled=true` and readiness slot: honored. Readiness now filled by T-P3-09; do not report it absent. | **Static core acceptance supported; HOLD for closure.** Inherited media mutation invariant in finding 2 and visible G7 differences remain relevant to integrated curriculum. No fresh QA PASS. |
| T-P3-05 | A1 provider registry/types/playable descriptors, source validation, TTL/tamper tests: `tests/Feature/Media/MediaProvidersTest.php`; A2 `PaidT3PublishTest.php:47`–`:117`; A3 iframe host rejection and recheck present. Signed storage, Vimeo, YouTube, direct file, audio/document/image/rich text/allowlisted iframe drivers exist. | Media descriptors are not ingestion sources; no indexing of video: honored. | **FAIL:** findings 1 and 2; follow-up 9. O-005 real private streaming remains UNKNOWN. Local direct-file driver is adapter foundation, not proof of ED-007 production signed-HLS/transcoding acceptance. |
| T-P3-06 | A1 MIME/content/size/EXIF/wrong-role cases: `tests/Feature/Media/UploadLessonMediaTest.php:72`–`:145`; A2 attach/replacement cleanup `:147` and `CurriculumPageTest.php` editor tests; A3 `CurriculumBrowserTest.php:85`–`:102`, 4 editor screenshots. Uploads random/private and streamed; image reencoding reused. No chunking or auto-duration claimed; manual duration is allowed by task. | `none`: upload/editor change adds no AI calls. Downstream readiness UI is separately tasked. | **HOLD:** existing tests support core upload criteria; integrated editor reaches finding 2 and incomplete resource workflow 11. No fresh browser or complete theme matrix. |
| T-P3-08 | A1 account binding/expiry/tamper/revocation and entitlement checker: `tests/Feature/Media/PrivateFilesTest.php:199`–`:308`; A2 duplicate/version/swap `:107`–`:158`; A3 encrypted/size/page/type/active-content rejects `:78`–`:105`. SignedFileUrl, PrivateFileAccess, PrivateFileController reused; current entitlement binding replaces earlier fail-closed stub. Uploads versioned/random/private; approval activates READY and retires previous in one transaction. A2 badge/editor browser artifacts exist. | `core`: PDF version/storage/approval foundation exists; actual source is document data, not video. Extraction now belongs to T-P3-09. | **FAIL:** finding 7; follow-ups 10–11. Old happy-path fixtures do not validate decoded PDF structure. High-risk reviewer/QA/visual still required. |

## Visual evidence limits and observed mismatches

The assigned PNGs currently on disk are modified from HEAD. They must be treated as working artifacts with unknown capture provenance until root associates them with a build and fresh run. P2-01 has 8 task screenshots; P2-02 has 24; P3-01 has 32; P3-02 has 8; P3-04 has 4; P3-06 has 4; P3-08 has 8. The latter wizard/builder/editor matrices do not include task-specific dark captures. Shared-shell dark tests do not prove each long form/modal.

I opened the actual existing `T-P3-02/wizard-basic-ar-1440.png` and `T-P3-04/curriculum-ar-1440.png` alongside reference-pack `13-course-wizard-v2.png` and `03-curriculum-builder.png`. This is artifact inspection, not an equal-viewport final score: references are 1568-wide while actual captures are 1440-wide/full-page. It cannot satisfy the mandatory equal-viewport G7 requirement.

- Wizard: reference fields are arranged compactly with title/slug alongside each other; actual title/slug/descriptions stack vertically and push Basic to a much taller page. The reference's compact tab/stepper and progress shapes differ visibly. Deferring steps 3–7 and unapproved age bands is justified scope; those omissions do not explain the current spacing/layout differences.
- Builder: reference has a persistent lesson-settings side panel; actual curriculum spans the main width and edits via a modal. Current action row also expands to multiple buttons. This is recorded as a difference in `T-P3-04_VISUAL_MATCH.md`, but no owner decision approving that structural substitution was found. Keyboard accessibility does not itself require abandoning the reference layout.
- Neither record provides final root visual acceptance. `T-P3-02_VISUAL_MATCH.md` and `T-P3-04_VISUAL_MATCH.md` explicitly disclaim pixel-level equality and an independent score. Existing proof of axe/overflow checks is useful but cannot replace visual judgment under D-026.
- No blanket UX verdict for the remaining screens is issued here. Their screenshots, populated/empty/validation states, language and theme variants require root's fresh comparison. No screenshot was overwritten and no reference replaced.

## Verification outcome

PRD auditor: **FAIL** for full approval of T-P2-01, T-P3-01, T-P3-02, T-P3-05 and T-P3-08 for the concrete findings above. T-P2-02 and T-P3-04 have supported core static acceptance; T-P3-06 has supported upload acceptance but an integrated media-invariant hold. These are review conclusions, not edits to PROGRESS.

QA verifier: **NOT RUN** (browser/DB execution excluded from this brief). UX reviewer: **NOT PASS** (two visible artifact comparisons only; fresh equal-viewport complete matrix/root judgment missing). High-risk reviewer: **FAIL** for T-P3-05/T-P3-08; T-P3-01 remains incomplete. G1–G6/G10/G11 must come from root's current checks, not historic counts. No task can be marked `done` from this report.

Not checked: live providers, storage deployment, production performance, fresh tests or browser journeys. Risk: paid playback and paid-T3 mutations currently fail stated access/protection invariants; keep these tasks in review/rework.
