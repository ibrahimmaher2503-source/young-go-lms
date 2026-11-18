# P6 review queue — 17 November 2026

What this change does: P6 implements quiz authoring/runtime, private assignment submissions, instructor grading, event-driven completion and certificate issuance/verification. This review reads the implementation and its callers against the PRD and task requirements; it does not approve the implementation from progress-board statements.

## Snapshot and review limits

- HEAD: `aa903cd3aad82043f17b81b3141368a40f4532fb`.
- The working tree is dirty. At inspection, there were no staged/unstaged changes or untracked files in `app/Domain/Assessment`, `Assignments`, `Completion`, or `Certificates`. Existing unrelated UI screenshots, package files, AI implementation and documentation changes were preserved. Other agents were adding P5/edge-case tests; they are not P6 acceptance evidence.
- Installed lockfile versions: Filament `v5.11.4`, Livewire `v4.4.8`. Inspected installed Filament record hydration: the resource record is locked, but the custom `ManageQuiz::$quizId` is not.
- Sources: AGENTS.md; REVIEW_PROTOCOL; SUBAGENT_BRIEF_TEMPLATE; VERIFICATION_GATES; P06 task specifications; PRD §§3.2, 12–14, 23, 29.1; Decision Register D-011/D-026, R-012/R-030 and actual O-* meanings; engineering decisions ED-002/004/005/017/020/023/025/028/029/031; architecture assessment/certificate models and events; Roadmap M6.1–M6.4; Checklist §8; reuse registry; UX certificate journey; visual acceptance/reference-pack contracts. AI hook checked against AI spec §8.3.
- Static source/test review plus one root-authorized isolated Livewire reproducer. `php -d extension=sockets vendor/bin/pest agents/logs/review-queue-2026-11-17/ManageQuizIsolationTest.php --colors=never` ran against SQLite `:memory:` (asserted by the test), failing as expected: **1 failed, 5 assertions, 1.90s**. No shared DB/browser/full-suite gates or PDF rendering were run. Remaining race findings are static execution-path findings, not claims of executed concurrent exploits.
- Test inventory: `rg --files tests` plus `rg -n 'T-P6-|StartQuizAttempt|SaveQuizAnswers|DecideSubmission|CourseCompletionEvaluator|IssueCertificate|GradeEssayAnswer|AssignmentFiles|ConfigureQuiz' tests` returned no P6 test implementation or tag matches at inspection. `tests/Feature/Curriculum/AssessmentItemsTest.php` covers T-P3-11 shell placement/authorization, not P6 runtime. `tests/Feature/ProfileTest.php:41–50` manually sets the name lock; it does not prove issuance sets it.
- No `tests/Browser/screenshots/T-P6-*` artifacts were found. References are design inputs, not execution evidence. No QA or UX PASS is asserted.

## Must fix

### F1 — A changed Livewire quiz ID exposes another course's answer keys (BLOCKER, T-P6-01)

**Where:** `app/Filament/Resources/Courses/Pages/ManageQuiz.php:38`, `:42`, `:135`, `:202`; `resources/views/filament/courses/manage-quiz.blade.php:53`.

**Problem:** `quizId` is a client-updatable public integer. Course/permission checks occur only in `mount()`. A legitimate instructor can mount an assigned course, update `quizId` to a quiz from another course in a later Livewire request, and trigger rendering. `quiz()` globally finds that ID and `getViewData()` loads its questions/options. The Blade view marks correct options and prints short-answer keys. Filament's automatic record scoping protects the original course record, not this second ID. The domain write Actions authorize their target, so this finding is specifically a read disclosure.

**Rule / impact:** T-P6-01 A2; PRD role scoping and §12 answer secrecy; REVIEW_PROTOCOL R2. Unassigned course assessments and keys become visible to an instructor.

**Smallest fix / proof:** Lock the identifier and resolve/authorize the quiz through the current course on every read. Add a Livewire property-tampering test and a wrong-course authorization test that assert no prompt/key appears.

**Executed reproducer:** `agents/logs/review-queue-2026-11-17/ManageQuizIsolationTest.php:45–50` mounts an instructor's assigned quiz, verifies the foreign prompt is initially absent, sets `quizId` to an unassigned course's quiz, then fails the secrecy assertion. The adjacent `.log:228` contains `FOREIGN-PRIVATE-PROMPT-6839` and `:232` contains `FOREIGN-CORRECT-KEY-6839` in the rendered response. This confirms F1 on the current installed stack without production data or a browser run. The reproducer/log are review evidence outside the normal test tree, not passing acceptance coverage.

### F2 — Autosave can rewrite an already graded attempt (BLOCKER, T-P6-02)

**Where:** `app/Domain/Assessment/Actions/SaveQuizAnswers.php:29–39`; `app/Domain/Assessment/QuizAnswerWriter.php:35–47`; `app/Domain/Assessment/Actions/SubmitQuizAttempt.php:49`.

**Problem:** Autosave refreshes and checks the attempt outside a row lock, then calls a writer that only locks its own answer writes transactionally. Submission locks the attempt, grades it and commits. An autosave that passed the earlier check can then overwrite/delete submitted answers, including deleting rows containing awarded points. The attempt's score remains the score computed before the mutation. A save stalled across expiry can likewise write after its deadline check.

**Rule / impact:** PRD §12 immutable attempt history/server enforcement; task runtime goal/A1; architecture immutable responses. A simultaneous autosave/submit produces answers inconsistent with the recorded grade and defeats finality.

**Smallest fix / proof:** Lock and recheck the attempt inside the same transaction as all autosave writes, sharing the submission lock order. Add a meaningful concurrent save/submit and deadline regression test.

### F3 — “After submission” unlocks the next lesson before any submission (BLOCKER, T-P6-02)

**Where:** `app/Domain/Entitlements/EntitlementService.php:180–184`; `app/Domain/Assessment/Actions/SubmitQuizAttempt.php:102–108`.

**Problem:** Sequence checking includes quizzes only when `progression === 'require_pass'`. A required quiz with `after_submission` never blocks subsequent items, even with zero attempts. Recording its completion at submit does not help because the access decision never consults that completion.

**Rule / impact:** PRD §12: allow next lesson *after submission*, versus requiring a pass; Checklist §8; ED-025 previous required item completion. Learners can skip the required quiz entirely.

**Smallest fix / proof:** Required quizzes should gate subsequent content using the recorded completion for either progression mode; the submit/review Actions already distinguish when completion is earned. Test access before submit, after failing submit in submission mode, and before/after passing in pass mode.

### F4 — A zero grade counts as assignment acceptance and can issue a certificate (BLOCKER, T-P6-05)

**Where:** `app/Domain/Completion/CompletionReport.php:95–99`; `config/learning.php:22–25`; `app/Domain/Assignments/Actions/DecideSubmission.php:89–92`.

**Problem:** The default `graded_counts_as_accepted=true` includes every `GRADED` submission in the required-assignment acceptance count. Grading a required assignment 0/100 therefore satisfies acceptance, even though the reviewer did not accept it. The decision triggers course evaluation immediately; completion is never undone and can issue a certificate before a later rejection.

**Rule / impact:** PRD §14 assignment *acceptance*; T-P6-05 required assignments accepted; Roadmap M6.4 certificate only after configured gates; PRD lock. No cited decision authorizes equating any grade with acceptance.

**Smallest fix / proof:** Count explicit accepted work by default. Any different completion policy needs a real governing decision. Add grade-zero, graded-but-unaccepted, accepted, and later-rejection tests.

### F5 — Certificates ignore bilingual certificate names and do not lock them (BLOCKER, T-P6-06)

**Where:** `app/Domain/Certificates/Actions/IssueCertificate.php:51–61`; `app/Domain/Certificates/CertificatePdf.php:46`; `app/Domain/Identity/Actions/UpdateCertificateNames.php:19–25`.

**Problem:** Issuance snapshots `display_name`/`name` into a single `learner_name`. Arabic and English PDF templates both use this same value. Neither issuance nor its listener sets `certificate_names_locked_at`; the only application occurrence enforcing that column is the profile update Action. A learner's configured Arabic/English certificate names are ignored and remain editable after issuance.

**Rule / impact:** YG-GAP-ID-001, R-012, T-P6-06 name lock step. An English display name prints on the Arabic certificate despite a correct Arabic certificate name being configured; first-issuance locking is ineffective.

**Smallest fix / proof:** Snapshot the approved certificate names per language and set the user lock atomically on first issuance using the same user lock as name editing. Prove language selection and edit-vs-issuance concurrency; add the required rendered Arabic PDF snapshot.

### F6 — Required certificate-template storage is absent (BLOCKER, T-P6-06)

**Where:** `agents/tasks/P06.md:117`; `app/Domain/Certificates/CertificatePdf.php:32`; `database/migrations/2026_11_17_380000_create_certificates_table.php:20`.

**Problem:** The task explicitly requires `certificate_templates` with language/layout/issuer placeholder. There is no model or migration for that table (`rg 'certificate_templates|PdfRenderer' app database` had no matches). The implementation always renders one hardcoded Blade template and reads issuer text from settings. Chromium via the installed PDF library is present, so a missing class named `PdfRenderer` alone is not a defect; missing template persistence is the unmet step.

**Rule / impact:** Task R1 and architecture data model. Template language/layout configuration cannot be represented as specified. Final legal text remains correctly OPEN; that does not remove the structural requirement.

**Smallest fix / proof:** Implement the specified template representation or obtain an explicit scope decision approving the settings/Blade substitute, then test template selection. Do not invent final issuer wording/assets.

### F7 — P6 acceptance tests are missing from the actual test tree (BLOCKER, all six tasks)

**Where:** Acceptance test requirements in `agents/tasks/P06.md:19–20`, `:39–42`, `:62–64`, `:84–85`, `:102–103`, `:124–126`; actual source inventory described above.

**Problem:** No P6-tagged tests or tests naming the central P6 Actions were found. Earlier curriculum shell tests and a manually set profile-lock fixture do not establish these criteria. Test-prompt documents and the progress board's “tests pending other model” entries are plans, not tests.

**Rule / impact:** AGENTS rules 8/12/14; ED-020/028/029; REVIEW_PROTOCOL R1/R5/R5A. Type validation, scoped grading, timer/limits, snapshots, private files, late rules, notification once, completion datasets, issuance idempotency and safe verification currently lack the requested task-level proof.

**Smallest fix / proof:** Write behavior tests for every acceptance row below, including forbidden role/wrong-owner cases, then run the applicable root gates. A green unrelated suite cannot close this finding.

### F8 — UI closure evidence is absent; quiz authoring also bypasses tokens (BLOCKER, UI tasks)

**Where:** `resources/views/filament/courses/manage-quiz.blade.php:2`, `:16`, `:39`; G7 rows in P06; `docs/design-system/VISUAL_ACCEPTANCE.md`.

**Problem:** Quiz authoring uses independent `bg-white`, `bg-gray-*`, `border-gray-*`, `primary-600` styling instead of semantic tokens/native surface components. No P6 screenshot directories were found and no actual per-task reference comparison was available to this reviewer. Technical gate claims do not establish visual fidelity. T-P6-05 lists G1–G6 but adds a user-facing completion management page, so the global UI acceptance requirement also applies to that page.

**Rule / impact:** AGENTS rule 6; D-026 owner correction; REVIEW_PROTOCOL R4; ED-023. UI remains unaccepted in both languages, sizes and themes. This is a confirmed source/token gap and a missing-evidence finding, not a fabricated visual score.

**Smallest fix / proof:** Reuse shared components/tokens, capture actual AR/EN desktop/mobile light/dark screens and interaction states, and obtain root comparison against the task references. Keep unexplained differences open.

## Should fix / unresolved specification gaps

### F9 — Invented OPEN references corrupt policy traceability (SHOULD; blocks policy acceptance until reconciled)

**Where:** `app/Domain/Assessment/Actions/ConfigureQuiz.php:18`; `app/Domain/Assessment/QuizGrader.php:10`; `app/Domain/Assignments/Actions/ConfigureAssignment.php:20`.

These comments assign quiz visibility/pass-mark units to O-021/O-022, partial credit to O-023, and revision/late policy to O-024. The actual register defines those as integration providers, project staffing/dates, Egyptian e-receipts, and saved-card auto-renew. They are not assessment decisions. `O-020` legitimately leaves completion defaults configurable, but does not approve unrelated grading policies. Record real Spec questions/defaults under the governing hierarchy instead of reusing unrelated IDs. This reviewer does not treat those comments or the board's repeated labels as owner authorization.

### F10 — A hidden result has no release action (SHOULD / incomplete result lifecycle)

**Where:** `app/Domain/Assessment/Queries/QuizAttemptView.php:62`, `:73`; `app/Filament/Resources/EssayReview/Pages/ReviewEssayAttempt.php:59`.

Hidden visibility waits for `released_at`, but repository search finds no Action writing it. The grading page only grades essays. A hidden quiz result remains inaccessible indefinitely through normal application controls. Resolve the intended visibility policy with the actual source of truth, then expose an audited authorized release action if results are meant to be releasable. Do not silently change visibility snapshots to work around this.

### F11 — Review decision retries create another decision and another notification (SHOULD)

**Where:** `app/Domain/Assignments/Actions/DecideSubmission.php:57–74`; `app/Domain/Assignments/SubmissionReview.php:20–24`; `app/Domain/Assignments/Notifications/SubmissionDecisionNotice.php:24`.

Accepted/graded/rejected work remains decidable, and every call creates a new decision ID. The notification key deduplicates only that new ID. Retrying the same successful grade after a network timeout makes another decision and sends another notice. A2's narrow “once per decision” is implemented by the dispatcher key; it does not protect request replay. Distinguish intentional regrading from a retried request with an operation key, and test duplicate delivery and intentional changed decisions separately. Do not claim notification idempotency solely from the dispatcher class.

### F12 — Partial audit failures leave unaudited edits (SHOULD)

**Where:** `app/Domain/Assessment/Actions/ConfigureQuiz.php:45–51`; `app/Domain/Assignments/Actions/ConfigureAssignment.php:57–64`; `app/Domain/Assignments/Actions/AddSubmissionFeedback.php:32–36`.

These write first and then throw if the audit record fails, without a transaction enclosing both. The caller sees failure but the setting/comment stays changed and can lack its required audit history. Other P6 Actions already wrap write + audit together. Reuse that transaction pattern and prove rollback when audit storage fails. This is a concrete failure path, not a claim that the audit system failed during review.

## Per-task acceptance, implementation and AI map

`Present` below means inspected code exists, not executed PASS. All verdicts include the missing test/evidence blockers above.

| Task / criterion | Implementation / coverage assessment |
|---|---|
| T-P6-01 A1 per-type validation | Present in `QuestionRules::clean/assertShape`: supported types, correct-option counts, true/false answer, required prompt, points. No matching P6 test found. Short-answer grading is deterministic in QuizGrader. |
| T-P6-01 A2 assigned instructor | Domain writes use QuizAccess -> assessment permission + CoursePolicy update/assignment. Read path fails under mutable quizId (F1). No forbidden-role/wrong-course P6 tests found. |
| T-P6-01 steps | Questions/options, active flag/version, settings and native action forms/preview present. Total marks are derived from active question points; no separately configurable total field. The task/PRD distinction should be made explicit before signing Checklist §8. |
| T-P6-01 ai hook | `QuizGuardQuestions::openFor()` returns active short-answer/essay IDs/type/prompt only, no options/keys/explanations. Matches the text-only hook boundary. No guard projection test found; full AI enforcement is P7, not claimed here. |
| T-P6-01 G7 | OPEN, no P6 artifacts; token violations F8. |
| T-P6-02 A1 late submit | Submit locks attempt and uses saved answers when beyond deadline+configured five-second grace; expiry command is scheduled every minute. Autosave race F2 remains. No task/deadline test found; grace is a working code choice, not an approved new O-* decision. |
| T-P6-02 A2 attempt limit | Start locks quiz, resumes open attempts, counts attempts and refuses at max; database unique learner/quiz attempt number present. No limit/concurrent-start test found. |
| T-P6-02 A3 snapshots | QuizSnapshot freezes question/options/key/version/settings at start; grading reads the snapshot. Snapshot design is present; unanswered save-vs-submit mutation F2 and no mutation-history test remain. |
| T-P6-02 A4 client key secrecy | Open HTTP page uses QuizAttemptView::questions(), which strips correctness/explanations/short-answer options; result projection honors frozen full/score_only/hidden settings. QuizAttempt hides snapshot/settings in serialization. No actual response/HTML payload secrecy test found. This does not excuse staff read disclosure F1. |
| T-P6-02 steps | Start/save/submit, timer, autosave, score/pass/review, result view, frozen history, quiz question/timer components present. Submission progression fails F3; hidden release missing F10. |
| T-P6-02 ai hook | QuizGuard::isActive checks an in-progress attempt within deadline+grace. The hook exists; no active/completed/expired learner-isolation test found. Later P7 integration must cover all active questions without keys; no claim of live model acceptance. |
| T-P6-02 G7 | OPEN, no screenshots/comparison or browser proof. |
| T-P6-03 A1 MIME/size | AssignmentFiles detects MIME from content, checks size/kind allowlist, re-encodes images, inspects PDF, randomizes names and stores on private media disk. No submission upload/MIME/size test found. |
| T-P6-03 A2 other learner file access | Download checks active viewer, exact owner, linked parent or assigned assessment staff; private path is not emitted in SubmissionView. Attachment access reuses EntitlementService. No cross-owner/request replay test found. |
| T-P6-03 A3 late policy | SubmitAssignment enforces due date against server clock; refuses prohibited late work or snapshots lateness/penalty. Reviewer applies penalty. No on-time/late/due-boundary or penalty test found. |
| T-P6-03 steps | Instructor config/attachments in ManageCurriculum, learner draft uploader, private revisions, resubmission count and status paths present. PRD §13 `Under Review` state and review-method configuration are not represented; states use `returned` as Needs Revision and add graded. These broader PRD gaps need reconciliation rather than silent checklist closure. |
| T-P6-03 ai none | No AI grading/feedback dependency found in assignment implementation. Source complies with the no-AI boundary. |
| T-P6-03 G7 | OPEN, no task artifacts/comparison. |
| T-P6-04 A1 assigned grading | ReviewAccess authorizes active actor + assessment.manage + course update gate; queue queries restrict course IDs. GradeEssayAnswer/DecideSubmission recheck server-side. No forbidden-role/wrong-assignment test found. |
| T-P6-04 A2 notify once | SubmissionDecisionNotice dedupe key is decision ID; EssayReviewedNotice sent when needs_review first clears. Shared NotificationDispatcher has unique dispatch protection. No task notification assertion; request replay F11 and failure/retry behavior are unproven. |
| T-P6-04 steps | Assignment filters course/status/overdue, native WorkspaceListRecords queue, feedback, grade/accept/reject/revision, essay score/recompute and audit present. Essay queue has course/state filters; under-review lifecycle not present. |
| T-P6-04 ai none | Human grading only; no AI grading/feedback code found in this task's Actions. |
| T-P6-04 G7 | OPEN; native workspace reuse is present, actual visual match is unverified. |
| T-P6-05 A1 rule combinations | CompletionReport measures required lesson %, required quiz passes (or reviewed no-pass-mark quiz), assignment states, cohort-relevant completed-session attendance; manual approval refuses auto-completion. Assignment predicate fails F4. No dataset for any rule combination found. |
| T-P6-05 A2 duplicate events | Unique learner/course completion key and UniqueConstraintViolation handler present; event emitted only on create; pre-existing completion returns unchanged. No duplicate/concurrent-event test found. |
| T-P6-05 steps | Progress, quiz submit/essay grade, assignment decision and attendance actions call CompletionTrigger; listener evaluates; manual override requires course update, enrolled learner, reason and transactional audit. Completion UI also exists, bringing global G7 obligation. |
| T-P6-05 ai none | No AI implementation added here. |
| T-P6-06 A1 one certificate | Ordinary IssueCertificate checks any existing certificate, catches unique violation; completion+active unique key prevents two live rows. Revoked remains and repeated completion does not silently reissue. Explicit audited reissue creates a new historical row intentionally. No repeated-event/concurrency test found. |
| T-P6-06 A2 Arabic PDF | Chromium-based spatie/laravel-pdf, Arabic embedded font, landscape view and QR present. Names are wrong by design F5. No real rendered PDF page/snapshot found; font embedding alone is not proof of shaping/layout. |
| T-P6-06 A3 safe verification | Verification view prints name/course/date/number/status, not phone/email/revoke reason. Verify route is throttled and noindex; download scoped to owner/linked parent. No endpoint privacy response test found. |
| T-P6-06 steps | Issue/revoke audited, token/number unique, invalid public revoked state, owner/guardian downloads present. Template model F6 and bilingual names/lock F5 are missing. Final issuer text/assets remain OPEN, correctly represented as placeholder. |
| T-P6-06 ai none | No AI issuance/verification code found; decisions are deterministic and human overrides explicit. |
| T-P6-06 G7 | OPEN; no Arabic PDF rendering or AR/EN verification/download visual comparison. |

## Independent verdicts

| Task | PRD auditor | High-risk reviewer | QA verifier | UX reviewer |
|---|---|---|---|---|
| T-P6-01 | FAIL — F1/F7/F8; policy traceability F9 | FAIL (security read finding; task nominally normal risk) | NOT RUN | NOT RUN |
| T-P6-02 | FAIL — F2/F3/F7/F8 | FAIL — finality race and progression bypass | NOT RUN | NOT RUN |
| T-P6-03 | FAIL — acceptance tests/G7 absent; PRD lifecycle/review-method gaps | NOT APPROVED — no upload/ownership/late-policy behavioral evidence | NOT RUN | NOT RUN |
| T-P6-04 | FAIL — acceptance tests/G7 absent | NOT APPROVED — scoped Actions present; decision replay and audit failure unproven | NOT RUN | NOT RUN |
| T-P6-05 | FAIL — F4/F7; added UI unreviewed | FAIL — grading can cause premature completion/certificate | NOT RUN | NOT RUN |
| T-P6-06 | FAIL — F5/F6/F7/F8 | FAIL — bilingual identity/lock requirements missing | NOT RUN | NOT RUN |

Verdict: Do not mark any P6 task done. Fix the source defects and missing requirements, add task-linked acceptance tests, then run the root gates and independent browser/visual review. P6 milestone acceptance remains unavailable.

Not checked: Full runtime gates, race reproduction, live browser accessibility, real PDF shaping, performance under load, provider delivery or deployment. The isolated F1 Livewire reproduction was run. Assumed ordinary concurrent LMS requests; no speculative high-volume redesign proposed.
Risk: Existing static defects affect assessment secrecy, grade finality, required progression and certificate eligibility/identity.
