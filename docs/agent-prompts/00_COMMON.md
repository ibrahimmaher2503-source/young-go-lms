# Common rules for every agent prompt in this folder

Read this file before starting any prompt in `docs/agent-prompts/`.

## Repo and context
- Repo: `C:\projects\young-go-lms-pack-v3.2\young-go-lms-pack-v3.2`, branch `feat/lms-build`.
  The copy under `C:\Users\N\Downloads\...` holds specs only. Do not read the board from it and do not work in it.
- Read these first: `AGENTS.md`, `agents/ORCHESTRATOR.md`, `agents/VERIFICATION_GATES.md`, `agents/PROGRESS.md`, `docs/HANDOFF_NEXT_PROMPT.md`, `docs/reviews/GATE_READINESS_P8_P9.md`.
- Approved decisions: `docs/07_DECISION_REGISTER.md` (section D) and `docs/DECISIONS_2026-11-18.md`. Do not decide anything that is still `OPEN` there.
- Write the final report to the owner in Arabic. Code, commits and comments stay in English.

## Running
- `npm run build` before any test run. A stale Vite manifest causes 500 errors.
- Tests: `php -d memory_limit=2G vendor/bin/pest <path>`, one folder at a time.
- Quality: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --no-progress`, `php artisan app:check-translations`, `php artisan app:check-rtl`, `php artisan app:check-brand-tokens`.
- Never touch `database/database.sqlite`. Use a scratch SQLite database for any browser or visual review.
- Run long commands in the background.

## Code
- Use the layered domain under `app/Domain/*` with final Actions and Queries. Touch the entitlements table only through the Entitlements module (I01). The Arch tests must stay green.
- Keep brand colors and fonts in `resources/css/tokens.css` only, mirrored by `config/brand.php`.
- Every user-facing string needs AR and EN translations, and the layout must work in RTL. Use logical classes only (`ms-`, `pe-`, `start-`...).
- When a point needs a product decision, stop on it and record it as `NEEDS-OWNER`. Do not guess.

## Tests (owner rule)
- Implementation agents **do not write new test files**. After finishing the code, save a tests prompt to `docs/test-prompts/<TASK-ID>.md`. It must list the files and classes, the behaviours and edge cases, the acceptance IDs, the requirement IDs, setup hints, the SQLite limits, and the rule that the test writer does not change production code.
- Do not delete tests or weaken them. Do not add `skip` or `todo` to make the suite pass.
- Mark implemented tasks `review` with the note "tests: pending (other model)".

## Git
- One commit per logical change: `type(area): description [T-xx-yy]`.
- Stage files by explicit path (`git add <path>`). Never `git add -A` or `git add .`. The tree may hold other people's work.
- No push and no history rewriting.
- When running in parallel with other agents, edit only the files you own. Re-read shared files before editing them: `lang/*`, `resources/css/*`, `StaffNavigation`, `agents/PROGRESS.md`.

## Board
- Do not move any task to `done` and do not close any gate (`*-MG`). That is the PO's decision.
- For each task you touched, add a short note with evidence (commit, test count, report path).
