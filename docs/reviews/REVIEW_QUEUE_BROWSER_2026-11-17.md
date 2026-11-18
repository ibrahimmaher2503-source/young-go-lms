# Review queue: independent browser QA — 2026-11-17

Scope: selected user-visible browser flows only. This is **not** full task approval or visual acceptance.

## Snapshot and environment

- Repository: `C:\projects\young-go-lms-pack-v3.2\young-go-lms-pack-v3.2`.
- HEAD: `aa903cd`; tested the existing dirty working tree, not a clean checkout or commit alone. Status and numstat captured in `agents/logs/review-queue-2026-11-17/browser/snapshot-status.txt` and `snapshot-numstat.txt`. Concurrent review documents can appear during this wave.
- Date/time: Windows local 2026-11-17, approximately 22:25–22:28 +02:00 (Africa/Cairo).
- PHP CLI 8.4.21, ZTS Visual C++ 2022 x64. Commands explicitly enable `sockets`. Browser harness starts local `127.0.0.1` test servers. `phpunit.xml` specifies testing environment and SQLite `:memory:`; these test files use `RefreshDatabase` and fixture users/roles.
- Read `AGENTS.md`, `agents/VERIFICATION_GATES.md`, `agents/REVIEW_PROTOCOL.md`, and each executed browser test before running it. No dependency installation, source/test/config/env edits, commits, deployment, or external-provider calls were performed by this reviewer.

## Exact commands and results

All logs below are under `agents/logs/review-queue-2026-11-17/browser/`. Every command output was read after completion.

| Command | Exit | Fresh result | Full output |
|---|---:|---|---|
| `php -d extension=sockets vendor/bin/pest tests/Browser/FamilyFlowsTest.php --filter='registers, verifies and signs in through the browser forms' --compact` | 1 | 1 failed, 4 assertions, 10.31s | `family-registration.log` |
| `php -d extension=sockets vendor/bin/pest tests/Browser/TwoFactorScreensTest.php --filter='enrolls in two-factor authentication\|completes the two-step challenge\|lets a parent reset' --compact` | 1 | 1 failed, 2 passed, 9 assertions, 15.25s | `two-factor-flows.log` |
| `php -d extension=sockets vendor/bin/pest tests/Browser/CoursePlayerBrowserTest.php --filter='answers keyboard shortcuts and remembers the playback speed' --compact` | 0 | 1 passed, 10 assertions, 8.09s | `course-player-controls.log` |
| `php -d extension=sockets vendor/bin/pest tests/Browser/TwoFactorScreensTest.php --filter='completes the two-step challenge with a code' --compact` | 1 | Reproduced challenge failure, 0 assertions, 10.68s | `two-factor-challenge-isolated.log` |
| `php -d extension=sockets vendor/bin/pest tests/Browser/FamilyFlowsTest.php --filter='shows field errors\|switches into a child context\|links an existing learner\|shows live password strength\|offers back and retry' --compact` | 0 | 5 passed, 24 assertions, 9.96s | `family-targeted-flows.log` |

The `\|` shown in the table is a Markdown table escape: the executed PowerShell arguments used plain `|` inside the quoted regular expressions. There were 10 distinct tests: 8 passed and 2 failed; the challenge failure was additionally repeated in isolation.

## Observations and task verdicts

### T-P1-10 — FAIL for tested registration/verification journey; five other tested flows PASS

**BLOCKER for browser gate:** `tests/Browser/FamilyFlowsTest.php:43` expects `/profile` after visiting the signed email-verification link. Fresh browser result explicitly reports actual `/my`. Registration had reached `/email/verify` and the created user had the student role before that failure. The test stops there: the subsequent verified-email DB assertion, logout, and fresh sign-in are **not verified** by this run.

Source corroboration, not additional browser evidence: `app/Http/Controllers/AuthenticationController.php:142` sends HTML verification to `/home`; `app/Http/Controllers/PageController.php:72` sends students to `/my`. This supports a stale browser expectation rather than proving a broken verification feature. Resolve the intended contract against the authoritative requirement, then restore a green journey; do not simply claim the skipped logout/login steps passed.

The five separate selected tests passed: invalid registration email displays field errors; child-context password rejection then switch/exit; learner confirmation of a parent-link code; live password-strength/copy-login UI (copied detail excludes password); back/retry actions on the configured 429 page. The password-strength and 429 tests include accessibility checks; this does not constitute an accessibility audit of all screens.

### T-P1-11 — FAIL for tested challenge journey; enrollment and child-reset test PASS

**BLOCKER for browser gate:** `tests/Browser/TwoFactorScreensTest.php:57` waits for `Profile` after valid challenge submission; it never appears. The following `/profile` assertion is not reached. The isolated repeat shows the same failure. The captured failure screenshot was copied to `agents/logs/review-queue-2026-11-17/browser/it_completes_the_two_step_challenge_with_a_code.png` before another run could remove Pest's temporary failure image.

I inspected that fresh image: it shows the signed-in learner dashboard, with “My learning”, “Welcome”, “Continue learning”, and the empty enrolled-courses state, rather than the challenge form. The image has no browser URL bar, so it does **not** independently establish the final path. The failure message's `/two-factor-challenge` URL describes the initially visited URL, not proof that navigation remained there. Source corroboration: `resources/views/pages/two-factor-challenge.blade.php:5` sets AJAX success to `/home`, and `PageController.php:72` routes the student to `/my`; `/my` is therefore the source-supported destination inference.

Enrollment passed QR presence, generated secret, submission, recovery screen, eight recovery-code list items, and JavaScript-error assertion. The test title says codes are shown “once”, but no repeat-visit check is present; do not infer once-only behavior from this browser result.

Parent child-reset passed the reset modal submission/success UI, JavaScript-error assertion, and rejection of the old password via a JSON login request. Despite the test title, it **does not** sign in with the new password; new-password login remains unverified here (`TwoFactorScreensTest.php:60–80`).

### T-P4-03 — PASS only for selected player control behavior

`tests/Browser/CoursePlayerBrowserTest.php:129` onward passed keyboard speed increase/decrease, caption toggle and `aria-pressed`, setting speed to 2, preservation after refresh, numeric current-position API, and no JavaScript errors. The fixture's video bytes are synthetic (`'v'`), so this test does not prove actual streaming, decoding, seeking, completion, or provider delivery. No overall T-P4-03 or G7 PASS is given.

## Untested / outstanding

No screenshot loops were run: tracked reference and task-matrix captures were not intentionally regenerated. No AR/EN × desktop/mobile × light/dark visual comparison, full browser suite, live provider test, protected PDF/attachment tab test, production acceptance, or all-task QA approval is provided. The fresh challenge screenshot is evidence of the failure state, not a replacement reference. Root retains visual review and task-status decisions.

Skipped: broad visual matrix and unrelated browser suites to preserve existing captures and stay within the requested focused review.
Risk: two currently red browser journeys block closure; passing narrow controls and form tests cannot establish complete task acceptance.

