# Closure review: 25 tasks in `review` with "G10 PASS; approval withheld" (2026-11-18)

Four independent read-only reviewers, one per audit file. They reviewed the code on disk at HEAD `ebdb5eb` plus the uncommitted working tree (~874 changed files from the audit-fix work). Each ran scoped Pest only (in-memory SQLite). No browser suite, no full suite.

**Result:** 8 approvable, 17 rejected. **Nothing committed**: every approved fix exists only as uncommitted changes, and is mixed with other tasks' work in shared files (see Commit status).

Next pass: use `docs/review-prompts/REVIEW_AND_COMMIT.md`.

## Approvable (8)

| Task | Verdict | Evidence | Notes |
|---|---|---|---|
| T-P2-01 | APPROVE-WITH-NOTES | `Filament/CategoriesTest` 7/7; audit finding 4 fixed (`SaveCategory.php:44-63`, wiring `ListCategories.php:31-47` uncommitted) | G7 deferred by PO (`PROGRESS.md:223`), no O-id |
| T-P2-02 | APPROVE-WITH-NOTES | `Pages/CmsPagesTest` 3/3 | Legal pages are placeholder drafts awaiting owner copy; G7 deferred |
| T-P3-06 | APPROVE-WITH-NOTES | `UploadLessonMediaTest` pass; `CurriculumPageTest:160` pass | No chunked upload / auto duration (O-005); screenshots predate the `ManageCurriculum.php:496` editor change, so recapture |
| T-P3-10 | APPROVE-WITH-NOTES | `AssistantEligibilityTest` 23/23, `AssistantEvaluationTest` 10/10 | O-014 real provider (G8). Command is `ai:evaluate`, but `VERIFICATION_GATES.md:16` says `ai:eval` |
| T-P3-14 | APPROVE | `SubtitleTracksTest` 18/18; B09 and S01 fixed | No caption upload field in the editor (out of scope) |
| T-P4-04 | APPROVE-WITH-NOTES | ProgressTest + edge cases pass; audit reproducer `ProgressSessionRegressionTest` 2/2 (failed before the fix) | Completion defaults are O-020 |
| T-P4-08 | APPROVE-WITH-NOTES | `ParentDashboardTest` all pass; screenshots newer than the Blade | Covers, results and help slots wait on later phases |
| T-P5-04 | APPROVE-WITH-NOTES | `tests/Feature/Notifications` 22/22; ISSUE-049, ISSUE-054 fixed | If a phone driver throws after mail is sent, a retry re-sends the mail (`NotificationDispatcher.php:38-40`). Guard in T-P9-04 before a real driver (O-021) |

## Rejected (17): what closes each

| Task | Must change |
|---|---|
| T-P3-01 | CSV export (`ListCourses.php:20-24`) lacks category, instructor, section/lesson counts, enrollments and price (PRD §9.1); title, category and instructor columns not sortable (`CourseResource.php:98-106`) |
| T-P3-02 | Recapture AR/EN screenshots (older than `CourseWizardSteps.php`); pricing step here is scope creep into T-P3-03 |
| T-P3-04 | Owner decision on side panel vs modal (`T-P3-04_VISUAL_MATCH.md:8`); recapture screenshots |
| T-P3-05 | `CurriculumPageTest.php:150-158` red: test expects Vimeo T2, uncommitted `VimeoProvider.php:29` returns T3. Spec says T2, so the **owner decides**, then the code or the test changes |
| T-P3-08 | qpdf DLL fault blocks all PDF tests (`PrivateFilesTest` 20 fail). Fix qpdf, record it in `ENVIRONMENT_INVENTORY.md`, rerun |
| T-P3-09 | Same qpdf failure (1 test); G7 PDF pane empty (`T-P3-09_VISUAL_MATCH.md:12`); no test for an approver who doesn't manage the course |
| T-P3-11 | G7 only: no screenshots for assessment rows or the association picker |
| T-P3-12 | No orders blocker on delete (`order_items.product_id` has no FK, so orphan lines); race check before transaction (`DeleteCourse.php:55` vs `:61`); copy slug not trimmed to 120 chars (`DuplicateCourse.php:151-159`, same bug as ISSUE-015) |
| T-P3-13 | qpdf failure (1 test); G7 picker differs from reference (`T-P3-13_VISUAL_MATCH.md:5`) |
| T-P3-15 | G7: no screenshot or visual-match note for the custom-block editor |
| T-P4-01 | I01 invariant weakened with no decision: `ManualGrantResource.php:31,73` queries `Entitlement` outside the module; allowlist narrowed in `I01_EntitlementOnlyViaServiceTest.php:40-45` |
| T-P4-02 | Same I01 issue; `EnrollInFreeCourse` has no route, view or controller caller |
| T-P4-03 | Vimeo has no keyboard, speed or captions controls (`show.blade.php:103`, early return in `player.js`); screenshots predate `show.blade.php` |
| T-P4-05 | `LearnerDashboardTest.php:149` fails twice in a row: 20 queries > 17 on `/my/courses` |
| T-P4-07 | No T2 or fullscreen watermark screenshot; no `T-P4-07_VISUAL_MATCH.md` |
| T-P4-09 | No lesson unlock relative to cohort start (`EntitlementService.php:70,77`, no migration column); no visual-match note |
| T-P5-03 | Owner ruling on the live-card layout (`T-P5-03_VISUAL_MATCH.md`); recapture screenshots; dark variants (`UI_PAGE_INVENTORY.md:23,26`) |

## Cross-cutting findings

1. **Fixed in `2c3ef3c`:** HEAD `ebdb5eb` tagged `AssessmentHistory` (`AppServiceProvider.php:123`) but the class was untracked. On a clean worktree, `CourseLifecycleTest` went from 4 failed to 6/6 passed with the file added.
2. **qpdf install broken** on this machine (exit `0xC0000135`; `qpdf30.dll` needs `jpeg8.dll`, `libcrypto-3-x64.dll`, `zlib.dll`). Not recorded in the environment inventory or the decision register. Every PDF upload is rejected (fails closed).
3. `REVIEW_FIXES_2026-11-18.md`'s "939 passed, zero failures" does not describe the current tree.

## Commit status

Isolation check for T-P5-04: its 6 files copied onto a clean HEAD worktree gave 21 passed, 1 failed (`LiveNoticesTest.php:157`, phone reminder expects 1 message). The fix depends on other uncommitted work (T-P9-04 phone path), so it was **not committed**. The other 7 approved tasks share files with rejected or in-flight work (e.g. `EntitlementService.php`, `ManageCurriculum.php`, `PdfInspector.php`), so a per-task commit is not possible until the audit-fix work is committed as a whole.
