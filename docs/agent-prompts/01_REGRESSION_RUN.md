# Prompt: full regression run and fixes (2026-11-19)

> Read `docs/agent-prompts/00_COMMON.md` first. Paste the block below into a new agent session opened at the repo root.

---

You are the test-and-fix agent for Young Go LMS (Laravel 13, Pest, Filament, Livewire).

**Repo:** `C:\projects\young-go-lms-pack-v3.2\young-go-lms-pack-v3.2`, branch `feat/lms-build`.
Do NOT use the copy under `C:\Users\N\Downloads\...`. That copy holds specs only and has a stale board.
Read `AGENTS.md`, `agents/ORCHESTRATOR.md`, `agents/VERIFICATION_GATES.md` and `agents/PROGRESS.md` first. Write the final report in Arabic.

## Goal
Run the whole test suite and the quality checks. Find every failure, fix the root cause and leave the suite green. Then update the task board so it states the real status.

## Rules
- **Fix the production code, not the test.** Change a test only when you can prove the test itself is wrong, for example an old assumption that a later approved decision in `docs/07_DECISION_REGISTER.md` or `docs/DECISIONS_2026-11-18.md` replaced. Write the reason in the report.
- Never delete a test, mark it `skip`/`todo` or weaken an assertion to make it pass.
- Do not write new test files. When you find a gap with no coverage, add it to the gaps list in the report.
- Follow the architecture. Use the layered domain under `app/Domain/*` with final Actions and Queries. Touch the entitlements table only through the Entitlements module (I01). Keep brand colors and fonts in `resources/css/tokens.css` only.
- Never touch `database/database.sqlite`. Use the test database or a scratch SQLite database.
- When a fix needs a product decision (a requirement, a price, a policy), do not guess. Stop on that point, record it as `NEEDS-OWNER` and move on to the next one.
- Commits: one commit per logical fix, as `fix(<area>): <description> [T-xx-yy]`. Stage explicit paths only (`git add <path>`), never `git add -A` or `git add .`. Do not push.
- Run long commands in the background and do not let them stop on a timeout.

## Steps

### 0. Setup
```
composer install
npm ci
npm run build          # a stale Vite manifest causes 500 errors in the tests
php artisan optimize:clear
```

### 1. Fast foundation suites (run these first, they gate the rest)
```
php -d memory_limit=2G vendor/bin/pest tests/Arch
php -d memory_limit=2G vendor/bin/pest tests/Feature/Invariants
php -d memory_limit=2G vendor/bin/pest tests/Unit
```
Fix any failure here before you continue.

### 2. Feature suites, one folder at a time
Run each folder separately so the failures stay clear and memory does not run out:
```
php -d memory_limit=2G vendor/bin/pest tests/Feature/<Folder>
```
Folders: AI, Assessment, Assignments, Audit, Brand, Catalog, Cohorts, Commerce, Content, Curriculum, Decisions, Demo, Engagement, Entitlements, Filament, Identity, InstructorFinance, Instructors, Learning, Library, Live, Media, Milestone, Notifications, Pages, Privacy, Reports, Seo, Settings, Shared, Subscriptions, Support.

Then run the root files in `tests/Feature/*.php`.
Start with the high-risk ones: Commerce, Subscriptions, InstructorFinance, Entitlements, Privacy, Reports.

### 3. Quality checks
```
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan app:check-translations
php artisan app:check-rtl
php artisan app:check-brand-tokens
```
Also run `composer qa` at the end as one combined check.

### 4. Full run
After the fixes, run the full suite once (`php -d memory_limit=2G vendor/bin/pest --exclude-group=browser` or the method in `AGENTS.md`). Confirm that nothing else broke.

### 5. Browser tests (optional, after everything above is green)
Run `tests/Browser` only if the environment allows it. Record any failure in the report. Do not fix visual design issues here. The owner has accepted those issues (D2, C1, H1, X6, X7, W1b, P1, R2).

### 6. Check stale board notes
- Notes for **T-P8-01 to T-P8-05** still say **BUG-007** is a critical blocker. Check `docs/reviews/BUGS_FOUND.md` and the tests (`CouponsTest`, `CommerceEdgeCasesTest`, `OrderEngineTest`, `PaymobWebhookFulfillmentTest`, `ManualPaymentProofsTest`). If the bug is fixed and the tests pass with no `todo`, update the notes in `agents/PROGRESS.md` and `docs/reviews/BUGS_FOUND.md` with the evidence (commit and test count).
- Check every `->todo()` and `->skip()` in `tests/`. List the reason for each one. Any whose reason no longer applies goes in the report as a candidate for activation. Do not activate it yourself unless it is a direct `todo` waiting on a bug you fixed.

### 7. Update the board
- Do NOT move any task to `done` and do not close any gate (`T-P8-MG`, `T-P9-MG`). That is the PO's decision.
- For each task you touched, add a short note in `agents/PROGRESS.md`: what you fixed, the commit and the test result.

## Required final output (in Arabic)
Save it to `docs/reviews/REGRESSION_RUN_2026-11-19.md` and show it in your reply:
1. A table per suite: folder | tests | assertions | passed | failed | skip/todo | time.
2. A table per fix: the problem | root cause | file and line | the fix | commit | related task.
3. A `NEEDS-OWNER` list: problems you stopped on because they need a decision.
4. The status of BUG-007 with evidence.
5. A list of every skip/todo and its reason.
6. Coverage gaps you noticed but did not write tests for.
7. The result of the quality checks (pint, phpstan, translations, rtl, brand-tokens).
8. Final status: is the whole suite green? If it is not, what failures are left and why.
