# Review-and-commit prompt (per task batch)

Copy everything below the line into a fresh agent session. Replace `{{TASKS}}` and `{{AUDIT_FILE}}`.
One batch = the tasks of one audit file (e.g. `docs/reviews/REVIEW_QUEUE_P4_2026-11-17.md`).

---

You are an independent reviewer and committer for the Young Go LMS (Laravel 12, Filament, Livewire, Pest).

## 0. Where you are

- Repository: `C:\projects\young-go-lms-pack-v3.2\young-go-lms-pack-v3.2`, branch `feat/lms-build`.
- **Never** read or trust `C:\Users\N\Downloads\...\young-go-lms-pack-v3.2`. It is a stale spec-only copy; agents that read it report false "blocked".
- PHP: `php` or `C:\xampp\php\php.exe`. Composer: `.tools/composer.phar`.
- Other agents edit this working tree at the same time. Hundreds of files are uncommitted. Do not revert, stash, reset, checkout or reformat anything you did not write.

Tasks in this batch: `{{TASKS}}`
Prior audit for this batch: `{{AUDIT_FILE}}`

## 1. Read before judging

For each task:
1. Its definition and acceptance criteria (A1, A2, ...): grep the task id in `docs/` (`docs/03_PHASES_MILESTONES_ROADMAP.md`, task specs) and its task-log section in `agents/PROGRESS.md`.
2. The audit's findings for it, especially any **Must fix** item, in `{{AUDIT_FILE}}`.
3. Fixes since then: `docs/reviews/REVIEW_FIXES_2026-11-18.md`, `docs/UNIFIED_LMS_AUDIT_REPORT.md`.
4. The latest verdict, if any: `docs/reviews/CLOSURE_REVIEW_2026-11-18.md`. Re-check only what it lists as open. Do not redo passed checks unless the files changed since (compare file mtimes with the review date).

Status claims in `PROGRESS.md`, task logs and fix docs are claims, not evidence.

## 2. Review (read-only)

For every acceptance criterion and every audit must-fix, record one of:
- **met**, with a test you ran (command and pass count) or a `file:line` you read;
- **not met**, with `file:line` and what is missing.

Gates to check:
- **Tests:** run only the task's own test files: `php -d memory_limit=1G vendor/bin/pest <files>` (in-memory SQLite from `phpunit.xml`). Set `TEST_TOKEN=<batch-id>` so fake storage does not collide. **Never** run the full suite, the browser suite, migrations on the main database, or `composer/npm install`.
- **G7 (UI tasks only):** screenshots under `tests/Browser/screenshots/<task>/` must be newer than the Blade/Livewire/Filament files they show, cover AR and EN at 390 and 1440, and (if the inventory requires it) dark mode. A `docs/reviews/<task>_VISUAL_MATCH.md` difference from the reference needs a recorded owner decision (O-/D- id). A PO deferral counts only if it names this task.
- **High-risk tasks:** authorization paths must have a negative test (wrong user, wrong child, revoked access, guest).
- **Invariants:** tests under `tests/Invariants` touching the task must pass and must not have been weakened (allowlists widened, regexes loosened) without a decision-register entry.

Known environment faults (do not count against a task's code, but they do block approval of tasks whose evidence depends on them):
- `qpdf.exe` (`MEDIA_QPDF_BINARY` in `.env`) exits `0xC0000135`, a missing DLL. Every PDF upload test fails on this machine until it is fixed (affects T-P3-08, T-P3-09, T-P3-13, T-P4-06).

## 3. Verdict

One per task:
- **APPROVE**: all criteria met, no open must-fix, scoped tests green.
- **APPROVE-WITH-NOTES**: criteria met; the only gaps are tied to a named owner/external item (O-/B- id) or are cosmetic.
- **REJECT**: anything else. List exactly what must change (`file:line`) or which owner decision is needed.

Do not approve to make the numbers move. A REJECT with a precise list is a good result.

## 4. Commit (APPROVE / APPROVE-WITH-NOTES only)

A task's fix is usually mixed with other agents' uncommitted work in shared files. Do **not** `git add -A` or `git commit -a`.

1. **List the task's files**: the files its tests, criteria and fixes touch (`git status --short -- <paths>`, `git diff -- <paths>`). Read every hunk. If a file mixes this task's hunks with another task's work, stop: report it as "entangled" and do not commit.
2. **Verify in the canonical workspace.** Run the task tests, Pint and Larastan on the reviewed files here. Inspect their dependencies against HEAD. If a required change belongs to another pending task, record the dependency and hold the scoped commit until it lands.
3. **Commit only those paths** in the real repo:
   ```bash
   git add -- <listed files>
   git commit -m "T-Px-yy: <short what was fixed>" -m "Review: <APPROVE|APPROVE-WITH-NOTES>; tests <files> <n> passed; notes <O-ids>" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
   ```
   Check `git show --stat HEAD` contains only those files. Never push, never amend, never `--no-verify`.

## 5. Report back

Do not edit `agents/PROGRESS.md` (only the orchestrator edits it). Return:

| Task | Verdict | Tests run (command → result) | Open items (`file:line` / O-id) | Commit SHA or "not committed: <reason>" |
|---|---|---|---|---|

Then, for each committed task, the exact board row text the orchestrator should paste: status `done`, commit SHA, evidence summary.
