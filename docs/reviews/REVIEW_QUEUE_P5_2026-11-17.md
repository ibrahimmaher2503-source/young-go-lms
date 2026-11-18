# P5 independent PRD and security review — 2026-11-17

What this change does: P5 adds cohort seats and waitlists, live scheduling and protected joins, localized notices, attendance and replay lesson creation. This review traces those flows through the current source and tests; it does not certify browser behavior or current test results.

## Review boundary and snapshot

- HEAD: `aa903cd3aad82043f17b81b3141368a40f4532fb`.
- Task commits inspected: `a6be0a6` (01), `fc5c214` (02), `1f18c04` (03), `701c50d` (04), `16220bc` (05); later completion integration also changes attendance callers.
- Relevant dirty snapshot at review start: all eight `tests/Browser/screenshots/T-P5-03/{dashboard,live-card}-{ar,en}-{1440,390}.png` modified. Untracked `tests/Feature/Cohorts/CohortEdgeCasesTest.php`, `tests/Feature/Live/{AttendanceTest,JoinWindowTest,LiveReplayTest,RecordJoinTest}.php`, and `tests/Feature/Notifications/NotificationRoutingTest.php` were included as source evidence, not established passing results. No tracked P5 domain source changes were present. Other unrelated AI, UI, package and continuity edits were left untouched.
- Read: AGENTS, review protocol, verification gates, brief template, `agents/tasks/P05.md`, relevant PRD sections 11/20/29.3, decision register (including R-008/R-013), architecture 10.1/access reason contract, ED-005/013/017/032, traceability rows and reuse registry, owning domain Actions/services, controllers, curriculum access queries, Filament callers and associated tests. Visual policy, reference README/common component mappings and existing T-P5-03 match report were read for evidence requirements only.
- Assumption: multiple workers can reserve seats and deliver notices. Launch traffic remains unspecified; no numerical load target is assumed.
- Only this report is written. No source fixes, commits, database commands, browser sessions or shared checks were run. Root owns check execution and actual visual judgment.

## Must fix

### 1. BLOCKER — Other cohorts' live content is exposed in the learner player

**Tasks / rule:** T-P5-01 and T-P5-03; YG-GAP-COH-001 (only cohort members see targeted sessions), ED-005 and R2/R5B (one access decision).

**Location:** `app/Domain/Entitlements/EntitlementService.php:57`–64, with actual callers `app/Http/Controllers/LearnController.php:60`–65 and `:106`; `app/Domain/Curriculum/Queries/EntitledCurriculumQuery.php:33`–49.

The shared decision checks entitlement, drip and sequence but never cohort membership. An active learner entitled to the course, without a seat in the session's cohort, therefore gets an allowed decision and can GET its live card and see its title/time in the curriculum. `GetJoinLink.php:31` separately stops the POST, so the meeting URL stays protected, but this does not prevent the information exposure or satisfy the single-service rule. The existing cohort test in `JoinLinkTest.php:74` checks only the POST; the page 404 asserted at lines 65–71 covers a learner without entitlement, not an entitled learner from another cohort. The progress claim that both non-member page and POST are hidden is broader than this test.

**Smallest fix:** Add the membership decision and `not_in_cohort` reason to the shared service, and apply the required hiding to learner outlines. Prove page, outline, dashboard and join behavior for seated/waitlisted/removed/other-cohort learners. Do not weaken the endpoint protection.

**If skipped:** Learners see sessions intended for another cohort, and access behavior differs between page and join endpoint.

### 2. BLOCKER — A partial notification failure duplicates delivered inbox messages

**Task / rule:** T-P5-04 A1; PRD §20 deduplication; R2 notification idempotency.

**Location:** `app/Domain/Notifications/NotificationDispatcher.php:32`–36; channel order in `app/Domain/Notifications/LocalizedNotification.php:33`. Confirmed framework behavior: `vendor/laravel/framework/src/Illuminate/Notifications/NotificationSender.php:119`–121 sends channels in sequence with a new notification UUID per invocation.

For a recipient with an email, delivery writes the database notification first, then attempts mail. If SMTP fails, the dispatcher deletes its dedupe row even though the inbox write succeeded. Retrying the same key writes a second inbox row. The test `LiveNoticesTest.php:150` fails while building the first channel's title, before any delivery, so it does not exercise this real partial-success case.

**Smallest fix:** Preserve delivery state per channel or use a stable idempotent database-notification identity while retrying mail independently. Add a test where the database channel succeeds and mail throws, then retry and assert one inbox entry and one successful mail delivery.

**If skipped:** A mail outage causes duplicate reminders/notices in the app; merely retaining the whole-send claim would instead silently lose failed mail.

### 3. BLOCKER — Opening a session bypasses its configured join window

**Task / rule:** T-P5-03 A2 and goal; PRD §11 exposes links only in the allowed window; R1/R5A PRD lock.

**Location:** `app/Domain/Live/JoinWindow.php:33`; caller `app/Domain/Live/Actions/GetJoinLink.php:34`. `OpenLiveSession.php:18`–24 permits opening without a time guard.

If staff opens an 18:00 session at 10:00 with a 15-minute window, the endpoint immediately returns its meeting URL eight hours before the start. `JoinLinkTest.php:100` and untracked `JoinWindowTest.php:43` encode this bypass. `agents/PROGRESS.md:534` labels it a decision, but no corresponding owner approval or exception was found in the higher-priority decision register or PRD. A report entry cannot redefine A2.

**Smallest fix:** Keep the lower time bound even for OPEN, or obtain and record an explicit owner decision changing the window contract and update the task/tests accordingly.

**If skipped:** The configured access window does not mean what the task promises.

### 4. BLOCKER — The cited cohort enrollment period cannot be represented

**Task / rule:** T-P5-01; YG-GAP-COH-001, architecture §10.1; R1 missing cited requirements.

**Location:** `database/migrations/2026_11_17_290000_create_cohorts_tables.php:17`–22; `app/Domain/Cohorts/Actions/ManageCohorts.php:87`–96; `app/Filament/Resources/Courses/Pages/ManageCourseCohorts.php:128`–131.

The cited requirement includes enrollment opening/closing dates, but the schema, validation and form expose only course dates and a manual open/closed status. Staff cannot configure a future enrollment period, and the seat/waitlist hooks cannot distinguish it. Checkout wiring is correctly deferred to T-P8-12, but the cohort fields themselves are part of the cited P5 requirement. The architecture also specifies a translatable cohort name; the current scalar `name` field cannot store separate AR/EN names.

**Smallest fix:** Complete the cited cohort fields and corresponding Action/form/tests, or explicitly resolve a narrower scope in the governing requirements. Keep checkout integration deferred.

**If skipped:** Later checkout has no authoritative enrollment dates to enforce, and cohort names cannot follow the cited bilingual data contract.

### 5. BLOCKER — Replay acceptance lacks its required integration proof

**Task / rule:** T-P5-05 A2 and AI hook; R1 criterion-to-test evidence, R5A.

**Location:** `tests/Feature/Live/LiveReplayTest.php:66`–142 (entire current replay test set); production integration `app/Domain/Live/Actions/PublishLiveReplay.php:49`, `app/Http/Controllers/LearnController.php:115`–122, `app/Domain/AI/AssistantEligibility.php:61`–67, `app/Models/Lesson.php:61`–63.

The new untracked replay tests check draft creation, lifecycle/author authorization, duplicate replay refusal and outline hiding. They never publish a replay and verify entitled versus non-entitled learner access, nor verify AI unavailable before its own PDF is READY and eligible only after its own approval. The source reuses the correct ordinary lesson and eligibility paths; this is missing acceptance evidence, not a proven AI bypass. Generic eligibility tests use a manually created unrelated lesson and a mocked access checker, so they do not prove the requested replay flow.

**Smallest fix:** Add one replay integration scenario covering publication, recorded access, absent/processing/READY replay PDF and a different lesson's READY PDF. Keep global flags/consent/course/item eligibility prerequisites intact.

**If skipped:** A key replay permission and AI boundary remains unverified despite the task's explicit test requirement.

## Should fix

### 6. SHOULD — A closed cohort can still seat a waitlisted learner

**Task / rule:** T-P5-01 state consistency; the migration states CLOSED takes no new seats or waitlist.

**Location:** `app/Domain/Cohorts/Actions/PromoteFromWaitlist.php:26`–36, compared with the status guards in `ReserveCohortSeat.php:30` and `JoinWaitlist.php:25`.

After a learner joins the waitlist, staff closes the cohort and frees a seat; promote still turns that learner into SEATED because it never checks the locked cohort's status. The untracked edge case at `CohortEdgeCasesTest.php:168` is explicitly TODO, so it supplies no regression protection. If staff promotion is intentionally allowed after enrollment closes, document that exception and change the misleading closed-state contract; otherwise reuse the closed guard here.

### 7. SHOULD — Lifecycle notices can reach learners whose account or course is closed

**Task / rule:** T-P5-04 recipient scoping; R2/R5B and shared access contract.

**Location:** `app/Domain/Live/LiveSessionNotices.php:49`–51; `app/Domain/Entitlements/CourseLearners.php:26`–31 computes `access_status` from entitlement rows alone.

The audience filters active entitlements and cohort seats but never checks the learner's `status` or whether the course is currently accessible. An inactive learner or learner of a suspended course with an active entitlement still receives reminder titles/times. `EntitlementService` rejects those users/course states. Use the shared appropriate access decision instead of equating active entitlement with current access; add those forbidden cases. Course-level announcements may intentionally include future drip/sequence items, so preserve any explicit scheduling-notice requirement rather than blindly suppressing every future event.

### 8. SHOULD — Scheduling UI cannot enter the session description

**Task / rule:** T-P5-02 scheduling fields; PRD §11.

**Location:** `app/Filament/Resources/Courses/Pages/Concerns/ManagesLiveSessions.php:36`–49.

The Action/model accept translated descriptions but the actual schedule form has only title, times, provider/link, cohort and instructor. Staff cannot enter the PRD description through this flow. Add the existing native translated field pattern and verify persistence through the mounted action.

## Acceptance and AI map

`SOURCE` means implementation/test source was inspected, not executed. Overall task verdicts are request-changes because this review does not replace mandatory QA/visual gates.

| Task | Acceptance / AI | Evidence and assessment |
|---|---|---|
| T-P5-01 | A1 concurrent capacity | SOURCE: reservation locks the cohort row before count/write; unique cohort/learner index. `CohortsTest.php:47` loops sequentially. No parallel run or lock assertion is present in that test; true contention evidence remains OPEN. |
| T-P5-01 | A2 waitlist order | SOURCE: queue timestamp then id in Promote; `CohortsTest.php:79`. Normal ordering is implemented; closed-state case is finding 6. |
| T-P5-01 | A3 recorded without cohorts | SOURCE: `CohortsTest.php:150`; ManageCohorts refuses recorded-only creation; entitlement works without cohort. |
| T-P5-01 | Steps / AI none | Tables, reserve/waitlist/promote/remove hooks and course member UI exist; no AI additions in this task. Missing cohort fields and shared visibility are findings 1/4. Instructor assignment Action exists, but the current cohort form has no assignment control. |
| T-P5-01 | G7 | OPEN: progress itself records no cohort-page screenshots/comparison. Root visual review required. |
| T-P5-02 | A1 course scoping | SOURCE: LiveAccess + CurriculumAccess; `LiveSessionsTest.php:55` forbidden instructor case, `:85` foreign cohort/instructor checks. |
| T-P5-02 | A2 UTC/display | SOURCE: utcRange and LiveSessionTime; `LiveSessionsTest.php:67` Cairo/UTC/Riyadh/New York assertions. |
| T-P5-02 | Steps / AI hook | Manual provider validation, encrypted/hidden meeting URL, common curriculum ordering and audited locked lifecycle exist. Schedule writes ai_enabled=false; test at `LiveSessionsTest.php:111`. Description form omission is finding 8. |
| T-P5-02 | G7 | OPEN: scheduling form screenshots and equal-viewport reference comparison not established; progress explicitly leaves them open. |
| T-P5-03 | A1 protected join | SOURCE: POST validates entitlement, cohort and window; pages never embed raw meeting URL; `JoinLinkTest.php:56`, `:65`, `:74`. Meeting link protection is present, while cohort page/outline visibility fails finding 1. |
| T-P5-03 | A2 window | FAIL: scheduled boundary tests exist (`:85`); OPEN bypass is finding 3. |
| T-P5-03 | A3 / AI hook | SOURCE: live page has no lesson-based AI slot and live items are false; `JoinLinkTest.php:118`. |
| T-P5-03 | G7 | OPEN: eight dirty screenshot files exist for AR/EN 390/1440. Existing match report explicitly leaves live-card composition unresolved. Dark variants/current root visual acceptance are not established; files existing is not approval. |
| T-P5-04 | A1 retry dedupe | FAIL: unique key prevents simultaneous fresh claims; partial-channel failure breaks retry behavior (finding 2). |
| T-P5-04 | A2 locale | SOURCE: HasLocalePreference/LocalizedNotification; LiveNoticesTest AR/EN and timezone assertions `:59`; router sends guardian email in guardian locale. |
| T-P5-04 | Steps / AI hook | Base supports arbitrary dedupe key, title/body/url and database/mail; registered reusable service is available for later help-created/replied/overdue notices. No AI provider calls added; actual help events correctly remain T-P7-04 scope. Config offsets and five-minute scheduler exist. Audience issue is finding 7. |
| T-P5-04 | G7 / delivery | No new screen is required by this task. Existing inbox appearance needs root review only if changed. Tests use local mail configuration; real SMTP delivery is UNKNOWN, not a source correctness verdict. |
| T-P5-05 | A1 audited correction | SOURCE: CorrectAttendance locks, requires reason, captures previous mark and actor in audit transaction; untracked `AttendanceTest.php:178` asserts before/after and actor. No current run claimed. |
| T-P5-05 | A2 / AI hook | SOURCE: replay reuses ManageLessons, draft/publish rule and normal access/PDF eligibility. Mandatory replay integration proof missing (finding 5). |
| T-P5-05 | Steps / G7 | Attendance unique learner/session row, automatic join mark, staff mark/correction UI and replay link exist. Attendance modal/replay UI screenshot comparison remains OPEN. Untracked tests do not justify changing status without the root checks. |

## Per-task verdict

| Task | Independent PRD/security verdict | Required before closure |
|---|---|---|
| T-P5-01 | REQUEST CHANGES | Findings 1/4; resolve 6; actual capacity contention or lock assertion evidence; cohort UI visual gate. |
| T-P5-02 | SOURCE CORE REVIEW ACCEPTABLE; TASK NOT APPROVED | Resolve description form omission; actual QA and schedule UI visual gate. No direct authorization/encryption defect identified in the scheduling Action. |
| T-P5-03 | REQUEST CHANGES | Findings 1/3; root AR/EN desktop/mobile light/dark visual review with unresolved card composition handled. |
| T-P5-04 | REQUEST CHANGES | Finding 2; resolve 7; full current QA. |
| T-P5-05 | REQUEST CHANGES | Finding 5, execution of pending/untracked tests, attendance/replay UI visual gate. |

All G1–G6/G10/G11 results are delegated to the root and are not asserted here. No task receives a final APPROVE or done status from this report. No payment collection, provider configuration, production deployment or legacy-system action was performed.

Not checked: live browser journeys, screenshot fidelity, current test execution, real database lock contention, real SMTP delivery and production deployment.
Risk: the meeting URL itself remains guarded for other-cohort users, but cohort metadata visibility, window enforcement and notification retry behavior need correction before acceptance.
