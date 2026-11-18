# Review queue audit — 17 November 2026

The 43 tasks recorded as `review` at audit start have been reviewed against their task acceptance criteria, cited PRD requirements, latest decisions, implementation, callers and test evidence. No task receives final approval from this audit. Several have actual correctness/security defects; others have incomplete required verification. Status remains exclusively in `agents/PROGRESS.md`.

## Boundary and source of truth

- Reviewed HEAD: `aa903cd3aad82043f17b81b3141368a40f4532fb`, branch `feat/lms-build`, plus the existing dirty working tree. Product implementation was not changed by this audit. Separate AI work continued during verification; these results are not an immutable clean-commit certification.
- Source priority: AGENTS → latest approved Decision Register entries → PRD including §29 → detailed AI/architecture specifications → engineering/design/UX conventions → task specifications. Task/progress comments cannot approve policy changes or waive gates.
- Read the full master PRD and Decision Register, relevant detailed AI/architecture/engineering/UX/design sections, orchestrator/loop/review/gate rules, reuse/traceability records, task specifications and current implementation. Six independent reviewers covered the complete queue; a seventh ran selected browser QA. Root inspected the reports, core access/progress/session/quiz/completion/certificate code and critical reproducer output.
- Installed stack checked from lockfile: Filament 5.11.4, Livewire 4.4.8; PHP CLI 8.4.21. Local verification uses Windows/SQLite with synthetic fixtures. Existing local data, unrelated source changes and screenshots were preserved.
- Scope is review and evidence reconciliation. No new feature implementation, provider activation, deployment, commit/push, DNS or legacy-system action was performed.

## Highest priority findings

1. **Cross-course quiz prompt and correct-key disclosure, reproduced.** `app/Filament/Resources/Courses/Pages/ManageQuiz.php:38` exposes mutable `quizId`; `:202` loads it globally after mount-only authorization. The isolated Livewire test mounts an assigned course, changes the ID and receives the foreign prompt/key. Evidence: [P6 F1](REVIEW_QUEUE_P6_2026-11-17.md), `agents/logs/review-queue-2026-11-17/ManageQuizIsolationTest.php` and adjacent log. This blocks T-P6-01 A2.
2. **Protected video URLs do not reauthorize their viewer.** `routes/web.php:36` uses signature middleware only; `app/Http/Controllers/MediaPlaybackController.php:20` streams without authentication or EntitlementService. A copied URL remains redeemable anonymously or after revocation until expiry. P2/P3A finding 1 and P4 finding 1 describe the same defect, not two separate defects. Blocks T-P3-05 and integrated P4 access acceptance.
3. **Repeated heartbeat tolerance can fabricate completion.** `app/Domain/Progress/Actions/RecordProgress.php:44` adds the configured allowance on each request. Three same-time end-position requests can complete a 100-second video. Downstream sequence/completion/certificates trust this progress. P4 finding 2; the existing one-jump test misses repeated requests.
4. **Idle session reactivation bypasses the active-session cap.** `app/Domain/Identity/SessionLimiter.php:48` returns before enforcing the limit. An old idle session resumes alongside two fresh active sessions; the existing test accepts that behavior. P4 finding 3; T-P4-07 A1.
5. **Completion and certificate identity violate their stated contracts.** `app/Domain/Completion/CompletionReport.php:95` counts any graded assignment as accepted by default, including zero marks. `app/Domain/Certificates/Actions/IssueCertificate.php:51` uses display name instead of bilingual certificate names and never locks name edits. P6 F4/F5; T-P6-05/06.
6. **Other consequential integrity gaps remain.** Published paid lessons can be changed to T3 without rechecking publication rules; PDF approval can overwrite a concurrent correction and omits Action-level course authorization; subtitle rollback can restore rows pointing at deleted files; notification retries duplicate already-delivered inbox messages after mail failure. Exact callers, impact and smallest fixes are in the phase reports.

These findings mix executed reproductions and source-proven control-flow defects. A static race analysis is not represented as an executed concurrency test. Missing verification is distinguished from a proven product defect throughout the detailed reports.

## Independent reports

| Review session | Complete assigned task coverage | Report |
|---|---|---|
| `/root/review_p1` | P0-MG; P1-03/04/07/10/11/MG (7) | [P0/P1](REVIEW_QUEUE_P1_2026-11-17.md) |
| `/root/review_p2_p3a` | P2-01/02; P3-01/02/04/05/06/08 (8) | [P2/P3A](REVIEW_QUEUE_P2_P3A_2026-11-17.md) |
| `/root/review_p3b` | P3-09/10/11/12/13/14/15 (7) | [P3B](REVIEW_QUEUE_P3B_2026-11-17.md) |
| `/root/review_p4` | P4-01/02/03/04/05/07/08/09/10/MG (10) | [P4](REVIEW_QUEUE_P4_2026-11-17.md) |
| `/root/review_p5` | P5-01/02/03/04/05 (5) | [P5](REVIEW_QUEUE_P5_2026-11-17.md) |
| `/root/review_p6` | P6-01/02/03/04/05/06 (6) | [P6](REVIEW_QUEUE_P6_2026-11-17.md) |
| `/root/qa_browser` | Ten selected family, 2FA and player browser tests | [Browser QA](REVIEW_QUEUE_BROWSER_2026-11-17.md) |

Every phase report maps each task acceptance item and exact `ai:` boundary to code/tests and documents what is still unverified. Static functional acceptance supported in a report is not final task approval or a QA/UX PASS.

## Current gate evidence

All outputs are under `agents/logs/review-queue-2026-11-17/` (ignored local evidence). Reports are the retained review artifacts.

| Gate/check | Actual result | Evidence / limit |
|---|---|---|
| G1 Pint | FAIL at initial run | `pint.log`: uncommitted `app/Domain/AI/Actions/AskLessonAssistant.php` formatting. Concurrent P7 work, not attributed to committed P1–P6 changes. |
| G2 Larastan | PASS with isolated cache and 1 GiB | `phpstan-isolated.log`, exit 0. Initial default run exhausted 128 MiB; retry with shared cache hit access denial; isolated config includes the unchanged project config. No baseline or rule relaxation. |
| G3 non-browser suite | RUNNING at report preparation | `pest.log`; final result must be read before claiming a pass. Coverage thresholds/parallel coverage are not established by this plain suite command. |
| G4 architecture | PASS | `arch.log`: 6 tests, 34 assertions, exit 0. |
| G5 invariants | FAIL acceptance despite process exit 0 | `invariants.log`: 8 passed, 1 TODO, 35 assertions. Gate requires none skipped. Separately, P4 documents I01's unauthorized whitelist weakening. |
| G6 translation / RTL | PASS | `translations.log`, `rtl.log`, exit 0 each. These check parity/utilities, not complete translation quality or visual fidelity. |
| G6 dependency audit / asset build | PASS | `composer-audit.json`: no advisories/abandoned packages; `build.log`: npm build exit 0. |
| G6 migrate/seed | PASS in a new isolated SQLite file | `migrate-seed-isolated.log`, exit 0. Fresh database under this log directory; original local database untouched. MySQL/Redis integration is not established. |
| G7 selected browser QA | FAIL for complete gate | 10 distinct tests: 8 passed, 2 failed. Verification reaches `/my`, but test expects `/profile`; challenge screenshot shows learner dashboard while assertion waits for Profile. Native player keyboard/speed/caption test passes only its synthetic fixture scope. No full visual matrix approval. |
| G8 AI evaluation | NOT RUN; required gate still open | T-P3-09/10 explicitly require it. Deferral in PROGRESS has no governing exception; `ai:eval` is absent from the current Artisan command list. Live provider remains UNKNOWN/O-014. |
| G9 payments | N/A to this review-only change | No Commerce/Subscriptions/Ledger implementation changed. This does not certify future payment behavior. |
| G10 | 31 PASS / 12 FAIL | All 43 queued tasks checked; CSV plus per-task logs. Lookup/tag success does not prove semantic acceptance. Failures listed below. |
| G11 doctor | FAIL | `doctor.log`, exit 1: scheduler heartbeat and migrations. `migration-status.log` shows seven pending local P6 migrations; no migration was applied to the existing database during this review. |

PHP sockets was enabled per command (`-d extension=sockets`) after the first ordinary Pest invocation failed at browser-plugin bootstrap. The host PHP configuration was not edited.

### Exact G10 failures

| Task | Missing identifiers reported by the checker |
|---|---|
| T-P4-10 | YG-GAP-INS-004; YG-GAP-LRN-005; ED-024 |
| T-P4-MG | A1 |
| T-P5-01 | YG-GAP-COH-001; ED-013 |
| T-P5-02 | ED-032 |
| T-P5-05 | A1; A2 |
| T-P6-01 | A1; A2 |
| T-P6-02 | A1; A2; A3; A4 |
| T-P6-03 | A1; A2; A3 |
| T-P6-04 | A1; A2 |
| T-P6-05 | A1; A2 |
| T-P6-06 | A1; A2; A3; YG-GAP-ID-001 |

## Review disposition for all 43 queued tasks

This is a review disposition, not a separate status board. The PROGRESS row remains authoritative.

| Task | G10 | Principal remaining work; phase report contains full acceptance map |
|---|---|---|
| T-P0-MG | PASS | Current milestone/demo/reference evidence consolidation. |
| T-P1-03 | PASS | Required audited super-admin bypass is absent. |
| T-P1-04 | PASS | Functional criteria supported by source; current QA/G7 still required. |
| T-P1-07 | PASS | O-015 age defaults remain unapproved; do not invent seed ranges. |
| T-P1-10 | PASS | Red registration journey assertions; full visual/UX acceptance. |
| T-P1-11 | PASS | Non-atomic sensitive-state audit; red challenge assertions; visual matrix. |
| T-P1-MG | PASS | Dependencies, no-TODO invariant gate, current demo/performance/visual acceptance. |
| T-P2-01 | PASS | Category raw-image upload bypasses reencoding. |
| T-P2-02 | PASS | Core source acceptance supported; fresh complete QA/G7. |
| T-P3-01 | PASS | Missing list fields/sorting and instructor creation policy conflict. |
| T-P3-02 | PASS | Truncated-slug collision; rich description/instructor flow; visual layout. |
| T-P3-04 | PASS | Current visual/QA acceptance; integrated published-media mutation boundary. |
| T-P3-05 | PASS | Playback redemption access and paid-T3 mutation bypasses. |
| T-P3-06 | PASS | Integrated media replacement invariant and incomplete author resource workflow. |
| T-P3-08 | PASS | PDF decoded-structure validation; concurrent no-op/resource workflow. |
| T-P3-10 | PASS | Required G8 not available; no scoped eligibility/fake-provider blocker found. |
| T-P3-09 | PASS | OCR quality fallback, text revision history, approval race/object scope, G8. |
| T-P3-11 | PASS | Course/lesson associations incomplete beyond section ordering; UI evidence. |
| T-P3-12 | PASS | Duplicate rich-image resource remap and P6 safe-delete/history integration. |
| T-P3-13 | PASS | Static functional criteria supported; N+1 reference counts and actual QA/G7. |
| T-P3-14 | PASS | Destructive file cleanup before transaction commit; malformed mixed VTT. |
| T-P3-15 | PASS | Required block authoring and library-only image contract incomplete. |
| T-P4-01 | PASS | Protected playback reauthorization; I01 weakened without decision. |
| T-P4-02 | PASS | Enrollment product caller/date-range fields; inherited access/invariant gaps. |
| T-P4-03 | PASS | Playback authorization and supported T2 player/progress/hook integration. |
| T-P4-04 | PASS | Repeated heartbeats fabricate progress; embed path has no progress bridge. |
| T-P4-05 | PASS | Current full QA/G7 and upstream progress correctness. |
| T-P4-07 | PASS | Idle session reactivation; missing T2 watermark/fullscreen proof. |
| T-P4-08 | PASS | Links entering child context and full visual/QA acceptance. |
| T-P4-09 | PASS | Access-order conflict, cohort layer and cohort-relative unlocking. |
| T-P4-10 | FAIL | Shared table composition, real insight fields, preview AI state and traceability. |
| T-P4-MG | FAIL | Blocked dependencies and required demo/checklist evidence. |
| T-P5-01 | FAIL | Cohort visibility/enrollment fields; true capacity-contention evidence. |
| T-P5-02 | FAIL | Schedule description field, ED-032 traceability and current form visual/QA. |
| T-P5-03 | PASS | Cohort metadata visibility and OPEN join-window bypass; visual mismatch. |
| T-P5-04 | PASS | Partial-channel retry duplicates inbox notices; recipient-scope follow-up. |
| T-P5-05 | FAIL | Replay entitlement/own-PDF eligibility integration proof; attendance/replay UI. |
| T-P6-01 | FAIL | Reproduced foreign quiz-key disclosure; acceptance tests and UI tokens/evidence. |
| T-P6-02 | FAIL | Autosave finality race; after-submission sequence bypass; tests/UI evidence. |
| T-P6-03 | FAIL | Acceptance tests absent; submission/review state contract reconciliation; UI. |
| T-P6-04 | FAIL | Acceptance tests absent; retry/audit failure paths and UI evidence. |
| T-P6-05 | FAIL | Grading is counted as acceptance; rule/idempotency tests missing. |
| T-P6-06 | FAIL | Certificate names/locking/templates; tests and actual Arabic PDF rendering. |

## Root visual observations and limits

Root opened the approved `03-curriculum-builder.png` and existing dirty `tests/Browser/screenshots/T-P3-04/curriculum-ar-1440.png`. The reference shows a main curriculum plus persistent lesson-settings side panel and a compact two-action header; the existing screenshot shows a full-width curriculum, no settings panel and six header actions. Typography/section surface proportions also differ. Data counts and permission-dependent navigation must remain real; they are not a reason to copy illustrative rows or add unauthorized controls.

The two artifacts have different viewport/state and the screenshot's exact capture revision is unestablished. This is a discrepancy triage, not an equal-viewport G7 comparison, a new UX score or approval. Do not replace the reference with current output. No all-page AR/EN desktop/mobile light/dark acceptance is claimed.

## Repair order and required re-review

1. Fix cross-course quiz reads, media redemption, paid-media mutation and PDF mutation authorization first, with forbidden-user/object regressions.
2. Fix progress/session enforcement and transaction/file/retry integrity; exercise failure and race boundaries rather than only happy paths.
3. Resolve completion/certificate contracts and implement the missing P6 acceptance tests. Keep O-015/O-019/O-020/provider inputs explicit; comments misusing O-021..O-024 are not approvals.
4. Complete missing task-specific behavior and genuine traceability; restore the intended current browser routes' assertions and rerun full journeys.
5. Capture comparable current visual matrices against the task references, obtain independent UX/QA/high-risk verdicts and rerun affected gates. Milestone closure follows dependencies; it cannot precede them.

Not checked: production/hosted CI, real SMTP/video/OCR/AI providers, MySQL/Redis concurrency, coverage thresholds, complete browser/visual matrix or Arabic certificate PDF output.
Risk: reviewed code is not ready for blanket approval; changing source during verification requires affected checks/reviews to be rerun on the eventual stable revision.
