# P4 independent review — 2026-11-17

What this change does: P4 adds unified course access, staff grants, learner and parent portals, playback, progress, session limits and instructor previews. This review inspected the committed implementation and current callers/tests against the task and governing requirements. It changes no application code or task status.

## Snapshot and review limits

- HEAD: `aa903cd3aad82043f17b81b3141368a40f4532fb` (`feat(certificates): idempotent issuance, branded PDF, verification page, revoke and reissue [T-P6-06]`). Review includes P5/P6 callers already present at this HEAD where they affect P4 promises.
- Relevant dirty state at inspection: modified AI PromptRenderer/config, acceptance checklist, HANDOFF/PROJECT_STATE, package manifests, many existing browser screenshots (including P4); untracked AI Actions/Assistant/models, entitlement/progress edge-case tests, and UI evidence harness/inventory/reports/matrices. These are concurrent work, not this review's changes. `docs/16_REVIEW_FINDINGS_AND_DECISIONS.md` was deleted before this review. Nothing was staged, reverted or committed.
- Read: AGENTS, REVIEW_PROTOCOL, SUBAGENT_BRIEF_TEMPLATE, P04 task specifications, Decision Register/defaults/open items, PRD §§10/17/18/23/29, architecture §§5.2/8/11.2, ED-004/005/024/025, relevant AI eligibility/player rules, conventions, reuse registry, visual acceptance and reference-pack README/manifest/component/implementation mappings.
- Static PRD/security review only. No DB/browser tests were run, per the root's shared-check instruction. Existing tests and evidence were read, not independently passed. No QA-verifier or UX-reviewer PASS is claimed. Root must establish current runtime and equal-viewport visual results.
- Expected load: multiple concurrent learner sessions/requests; owner launch traffic remains O-030. The defects below occur with one learner and a few requests, without a scale assumption.

## Must fix

1. **T1 playback bypasses access checks after the page issues its URL.** `routes/web.php:36`, `app/Http/Controllers/MediaPlaybackController.php:18`, `app/Domain/Media/Providers/PrivateFileProvider.php:88`.
   - The route has `signed` only. Its signature binds the asset and expiry, with no account/acting learner. The controller checks the ready asset and file existence, then streams it without EntitlementService.
   - An enrolled user can forward the URL to an anonymous browser; it also continues working after revocation, suspension or an item being withdrawn until TTL. This violates PRD §17.2 and architecture §8's explicit media entitlement recheck, R2 and T-P4-01's authoritative access goal. A valid signature proves issuance, not current authorization.
   - Smallest fix: bind issuance to the authorized viewer/context and recheck the shared item access decision on redemption, preserving authorized staff preview. Add anonymous, copied-account and post-revoke regression tests. **BLOCKER: T-P4-01/03; also affects the immediate-revoke guarantee of T-P4-02.**

2. **Repeated heartbeats complete a video without elapsed playback.** `app/Domain/Progress/Actions/RecordProgress.php:44` (also `:46`), `config/learning.php:18`.
   - Every heartbeat adds a fresh 30-second allowance to the already credited furthest position. For a known 100-second video, three immediate heartbeats with position `100` credit 30, 60 and 90 seconds and complete it at the default threshold. The default 30 requests/minute throttle permits this.
   - `ProgressTest.php:52` tests one forged jump, not repeated jumps. The dirty ProgressEdgeCasesTest likewise does not close this hole. Course completion/certificates and sequence unlocks consume the resulting state.
   - Smallest fix: make tolerance a bounded total allowance over trusted elapsed playback, rather than a grant per request; test repeated same-time heartbeats. **BLOCKER: T-P4-04 A1, PRD §10.3, consequential T-P4-09 sequence behavior.**

3. **Idle sessions can resume past the concurrent limit.** `app/Domain/Identity/SessionLimiter.php:48`.
   - Existing sessions are touched and returned as OK before the limit is enforced. Start session A, leave it idle beyond 120 minutes, then start B and C. Resume A: all three become active without any sign-out. Further requests stay on the same bypass branch.
   - `tests/Feature/Identity/SessionLimitsTest.php:63` explicitly accepts that resumed session. The test needs to assert the limit after reactivation, not merely ignore idle rows at new login.
   - Smallest fix: serialize reactivation and enforce the same active-session limit when an idle existing session becomes active. **BLOCKER: T-P4-07 A1 / YG-GAP-SEC-001.**

4. **Vimeo T2 playback never receives a watermark.** `resources/views/learn/show.blade.php:87`, `app/Domain/Media/Providers/VimeoProvider.php:56`.
   - Vimeo returns a T2 `EMBED` descriptor. The template only adds the watermark for `video`/`audio`, so the existing T2 provider is excluded before `Watermark::applies()` can help. Current feature/browser fixtures cover T1 and T3, not this T2 branch.
   - The iframe's own fullscreen also bypasses an HTML overlay outside that iframe. Simply adding the overlay to embed markup does not establish fullscreen acceptance.
   - Smallest fix: include the supported restricted provider in the protected player stage, expose a stage fullscreen path, and verify actual T2 desktop/mobile/fullscreen output. **BLOCKER: T-P4-07 A3 / YG-GAP-VID-003.**

5. **The implemented T2 player has no app playback/progress/timestamp integration.** `resources/js/player.js:23`, `resources/js/player.js:166`, `resources/views/components/learning/lesson-media.blade.php:17`, `resources/views/learn/show.blade.php:94`.
   - `initPlayer()` exits without `[data-player-media]`, and the progress reporter also handles only that native element. Vimeo's iframe has no such element or message/provider bridge. A known-duration Vimeo lesson therefore records no progress or resume position through the app and cannot complete automatically; app speed/caption/keyboard controls and `window.ygPlayer.currentPosition()` are absent too.
   - This is observable with the existing supported Vimeo provider, not a speculative new integration. PRD §10.3 permits persistence where the provider permits it; Vimeo has no implemented reporting path here at all. The task additionally promises a provider-agnostic player and timestamp hook. `window.ygPlayer` for native media exposes currentPosition only, with no pause API for the P4 hook.
   - Smallest fix: complete the existing provider adapter's player/control/progress interface and add an embed fixture exercising the real event boundary; expose the pause/timestamp hook required by the task. **BLOCKER: T-P4-03 steps/AI line and T-P4-04 goal.**

6. **I01 was weakened to permit direct Filament entitlement queries without a recorded decision.** `tests/Feature/Invariants/I01_EntitlementOnlyViaServiceTest.php:15`, `app/Filament/Resources/ManualGrants/ManualGrantResource.php:78`, `app/Filament/Resources/ManualGrants/Pages/ListManualGrants.php:94`.
   - `git show 7df6c04 -- tests/Feature/Invariants/I01_EntitlementOnlyViaServiceTest.php` confirms the added whole-directory allowlist. The moved resource directly exposes the Entitlement model/query; the test now exempts it. The comments saying it lives inside the Entitlements module are stale.
   - No corresponding Decision Register entry was found. AGENTS §4.8 prohibits weakening invariants without one; T-P4-01 A3 explicitly limits entitlement queries to the module. Passing the weakened invariant cannot prove that acceptance item.
   - Smallest fix: keep native Filament UI but move entitlement query/mutation ownership behind the module's public query/actions, or obtain and record a deliberate owner decision before changing I01. **BLOCKER: T-P4-01 A3 / T-P4-02 architecture.**

7. **The layered-access implementation and tests contradict ED-005 and omit cohort-relative unlocking.** `app/Domain/Entitlements/EntitlementService.php:53`, `:62`, `:140`; `tests/Feature/Entitlements/AccessLayersTest.php:96`.
   - ED-005 says course → item → entitlement → cohort → sequence → drip → preview override. Implementation returns preview before entitlement and evaluates drip before sequence; the test deliberately expects “drip comes before sequence.” Thus an item with both a missing predecessor and future date reports the drip reason rather than the required sequence reason.
   - Only `none`/`date`/`days` from entitlement start are implemented. No curriculum cohort target/start basis or `not_in_cohort` branch/copy exists. P5 cohorts are now in this HEAD, but their existence has not supplied the T-P4-09 relative-cohort contract. A1 is not an every-layer/reason dataset.
   - Smallest fix: resolve the documented order conflict against the governing decision, implement the existing required cohort basis through the shared access service, then extend the dataset and localized reason map. Do not silently bless a different order. **BLOCKER: T-P4-09 A1/steps, ED-005, ED-025, YG-GAP-LRN-002.**

8. **Instructor insights bypass the shared staff table system and omit promised fields.** `resources/views/filament/courses/learners.blade.php:2`, `:8`, `app/Filament/Resources/Courses/Pages/CourseLearnersPage.php:55`, `app/Domain/Progress/Queries/LearnerCourseDetail.php:24`.
   - This is a copied HTML table with its own gray/white palette, rather than a resource-native Filament table in the shared workspace. AGENTS D-026, R4 and COMMON_COMPONENTS C07 require the shared system for every table; nominal tokens/RTL checks do not establish that.
   - The list offers access/enrolled date/percent only. Required last activity and completed-items values are absent; detail filters to Lessons, and quiz/assignment status columns remain absent although P6 now exists at HEAD. The task's “when P6 lands” condition is now applicable.
   - Smallest fix: use the existing native table/workspace composition and scoped domain data, add the promised real fields, and verify instructor scope through the real page. **BLOCKER: T-P4-10 steps / YG-GAP-INS-004 / D-026.**

9. **Staff preview always hides the AI state instead of previewing it.** `app/Http/Controllers/PreviewController.php:71`, `resources/views/learn/show.blade.php:177`.
   - `aiAvailable` is hardcoded false, so an otherwise eligible synthetic learner's AI button/state cannot appear. This safely sends no requests but only satisfies half of the exact `ai:` line.
   - Smallest fix: expose the synthetic learner availability as a disabled/read-only preview state through the existing eligibility contract, with no provider request or learner progress. **BLOCKER: T-P4-10 AI line / R5A.**

## Other requirement and evidence gaps

- **T-P4-02 free enrollment has no product caller.** `app/Domain/Entitlements/Actions/EnrollInFreeCourse.php:20` is referenced by tests only (`rg EnrollInFreeCourse app routes resources`). A parent/self action is tested, but learners cannot actually invoke enrollment from a route/component. This is an incomplete task goal/step; it must remain open rather than be counted as the enrolled learning path. The manual date-range form exposes only an end date (`ListManualGrants.php:65`); there is no start-date input.
- **T-P4-08 child-context action links are absent.** `resources/views/parent/dashboard.blade.php:12` switches the summary only. The other links go to add-child; existing child courses have no enter-child-context action. A1/A2 can pass without satisfying the task's explicit links-into-context step. Reuse `EnterChildContext`, do not introduce another identity mechanism.
- **G7 is open across UI tasks.** Task-owned P4 screenshots and visual-match documents exist for grants/player/dashboard/parent/locks/watermark, mainly AR/EN at 390/1440 in light mode. Dirty `UI_BASELINE/matrix` additionally contains light/dark preview/learner/staff captures; it is incorrect to claim all those screenshots are absent. However the dirty UI report itself leaves root equal-viewport scoring and several after-matrix checks pending. These files are supplemental evidence, not this review's UX PASS or a guarantee that they capture this HEAD/assets.
- **T-P4-07 fullscreen is still an explicit evidence gap.** `tests/Browser/WatermarkBrowserTest.php:42` tests stage containment and saves ordinary screenshots; it never requests fullscreen. A3 requires an actual fullscreen screenshot, including mobile/provider behavior.
- **T-P4-MG lacks its required completed acceptance artifact.** `docs/04_DELIVERY_ACCEPTANCE_CHECKLIST.md:115` onward remains unchecked for §6. `agents/PROGRESS.md:526` records historical aggregate counts and explicitly retains G7/review gaps; it is not fresh proof of the complete manual-grant → child learning → resume → PDF → progress → parent demo. T-P4-06 is still blocked, so the dependency gate cannot close.

## Should fix

10. **Future grants are counted as currently active.** `app/Domain/Entitlements/CourseAccessSummary.php:28`, `app/Filament/Resources/ManualGrants/ManualGrantResource.php:92` and `:144` do not test `starts_at <= now()`, unlike `Entitlement::isCurrent()`. A scheduled future active row inflates the current-active summary/filter while actual access is refused. Reuse the same current-time predicate; add one future-start case.

11. **The reuse registry still calls built P4 pieces planned at wrong paths.** `docs/REUSE_REGISTRY.md:58` names `Domain/Learning/Actions` for RecordProgress, while implementation is `Domain/Progress/Actions`; AssistantEligibility is also marked planned at `:61`. Update existing registry rows instead of leaving a second implementer to recreate them. R3 registry requirement; documentation only.

## Task acceptance / AI / evidence map

“Static supported” below means code and relevant assertions were read, not that tests ran in this review. No row is a runtime QA or visual approval.

| Task | Criteria and inspected evidence | Exact AI line assessment | PRD/security verdict |
|---|---|---|---|
| T-P4-01 | A1 GrantEntitlement unique-key/firstOrCreate and EntitlementServiceTest:60; A2 expired/revoked lessons/files/AI checks at :129 and cache/sweep coverage; A3 I01 test exists but was weakened (6). Media redemption violates one service (1). | Hook connected: AssistantEligibility calls LessonAccessChecker; provider binds EntitlementService. | **CHANGES REQUIRED**: 1, 6. A1/static A2 service logic supported, end-to-end media revocation not supported. |
| T-P4-02 | A1 manual grant/no order and A2 revoke/audit tests in EnrollmentAndGrantsTest:96; A3 linked-parent/free action :57; summary/filters/formula-safe CSV tests :73/:120/:136. UI caller/date-range gaps above; immediate media revoke fails 1. | No new AI feature in enrollment/grants inspected. | **CHANGES REQUIRED**: 1, 6 and incomplete product enrollment/date-range steps; summary issue 10. |
| T-P4-03 | A1 404 scope tests CoursePlayerTest:64/:79/:186; A2 reasons/preview/neighbours :100/:114/:178; A3 CoursePlayerBrowserTest:54 and captures; A4 :129 exercises native fixture controls only. Provider branch fails 5; signed media fails 1. | Native timestamp exists; no pause hook; Vimeo has neither integration. Live branches have no AI slot. | **CHANGES REQUIRED**: 1, 5; G7 pending. |
| T-P4-04 | A1 ProgressTest:52/:63/:94/:107 covers single-jump/manual/auth/throttle but misses repeated slack (2); A2 :124 resume; A3 :141 archive/new/optional required lessons. RecordProgress, RecalculateCourseProgress and ContinueLearning traced to controllers/player/parent/staff callers. | No AI addition in progress implementation. | **CHANGES REQUIRED**: 2, plus provider gap 5. A2/A3 statically supported for existing native lesson path. |
| T-P4-05 | A1 LearnerDashboardTest:51/:69/:84/:116 scopes learner/child/course filters/notifications; A2 dashboard/courses/empty browser tests and screenshots exist. LearnerCourseRows/ActingLearner inspected. | Dashboard does not render AI conversations/content. | **Static PRD/security A1 supported; acceptance pending** runtime/G7 and upstream progress correctness. No independent task-specific data leak found here. |
| T-P4-06 (context) | Private-file work and player resources exist; task status blocked for visual closure. Not independently re-adjudicated in this assignment. | No added AI inference is required. | **BLOCKED context retained**, not a new approval. |
| T-P4-07 | A1 SessionLimitsTest:41/:56/:117; reactivation violates limit (3); A2 ResetUserDevices and tests :86/:104 support audited reset/forbidden cases; A3 T1 browser captures and T1/T3 feature case :160, missing T2/fullscreen (4). | Web middleware denies ended sessions on subsequent authenticated requests; preview sends no AI request. No live-provider activation observed. | **CHANGES REQUIRED**: 3, 4 and actual fullscreen evidence; do not report A3 PASS from containment. |
| T-P4-08 | A1 ParentDashboardTest:50/:67/:88 verifies linked/active children, stranger/pending/forbidden cases; A2 :96 verifies no conversation text; A3 AR/EN populated/empty captures plus supplemental matrix. Parent controller and shared rows inspected. Context-action step absent. | Status placeholder only; no child AI content rendered. | **Static security A1/A2 supported; PRD step incomplete**, G7 pending. |
| T-P4-09 | A1 AccessLayersTest ordered cases, file/AI parity and cache flush cover supported subset only; order contradicts ED-005 and cohort basis/reasons absent (7); A2 locked page suppresses body/files/progress; A3 LockedLessonBrowserTest captures AR/EN. | AssistantEligibility consumes same service-based lesson-file decision. | **CHANGES REQUIRED**: 7; sequence inherits forged completion (2); G7 pending. |
| T-P4-10 | Course policy/preview scope and no-progress assertions in InstructorInsightsTest:43/:60; scoped query/detail :70; page test only proves learner forbidden and instructor permission, not a rendered authorized full page with all fields. A3 supplemental captures exist, but root visual judgment is pending. Task acceptance labels were remapped in the test comments versus current P04, so use behavior rather than A-number alone. | Hardcoded unavailable fails required visible synthetic button state (9); no AI requests sent. | **CHANGES REQUIRED**: 8, 9; current P6 quiz/assignment columns and full page acceptance absent. |
| T-P4-MG | Checklist §6 unticked; demo not independently replayed; upstream blockers and T-P4-06 blocked; historical count at PROGRESS:526 explicitly leaves visual/review gaps. | No milestone AI feature required. | **DO NOT APPROVE**. Required dependencies/demo/checklist/current gates remain open. |

Verdict: fix the concrete access, progress, session, provider and invariant blockers before P4 approval. Keep the remaining product steps and visual gates open with their actual evidence.

Not checked: DB/browser runtime, actual fullscreen/provider behavior, root reference scoring, production or deployment. No code/status/commit changes were made; all concurrent work was preserved.

## Authorized evidence-only regression run

After the static review, root authorized two narrowly scoped regression reproductions outside the ordinary test tree. This is an explicit exception to the earlier no-DB-test limitation; it does not establish a full QA PASS.

- Fixture: `agents/logs/review-queue-2026-11-17/ProgressSessionRegressionTest.php`; one `Tests\\TestCase` class using RefreshDatabase, with pre-refresh assertions requiring SQLite `:memory:`. No development/production database was used.
- Command: `php -d extension=sockets vendor/bin/pest agents/logs/review-queue-2026-11-17/ProgressSessionRegressionTest.php --colors=never`.
- Log: `agents/logs/review-queue-2026-11-17/ProgressSessionRegressionTest.log`.
- Result: **2 expected failures, 10 assertions, 1.63 seconds**. Frozen time plus three position-100 heartbeats on a known 100-second video yielded **90 seconds / 90% / completed**, confirming finding 2. Reactivating the idle session after two fresh sessions left **3 active sessions**, confirming finding 3.
- Neither failure was an environment/setup error. No implementation was fixed; no ordinary test files, PROGRESS or commits were changed. Browser/provider/fullscreen/root visual review remain unperformed by this reviewer.
