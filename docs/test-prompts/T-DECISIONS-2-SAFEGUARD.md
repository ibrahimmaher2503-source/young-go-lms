# Tests prompt: T-DECISIONS-2 (O-020 pass mark, O-028 safeguarding routing, O-015 guardian OTP)

You write the tests for a finished task. Do **not** change production code. Pest, SQLite, `RefreshDatabase`; run with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`, one folder at a time. Headers `// T-DECISIONS-2 | O-020 O-028 O-015 | A1`. Unique helper names. Commit with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## O-020
- `QuizPassMark::default()` is 60; `config(['assessment.default_pass_mark_percent' => 75])` changes it; invalid config (0, 101, null) falls back to 60. `effective(null)` = default, `effective(80)` = 80.
- A quiz with `pass_mark_percent = null`: `QuizSnapshot::settings` freezes 60; a 60% score passes, 59.99% fails; an explicit 80 beats the default. `ConfigureQuiz` accepts `progression = require_pass` with a null mark.
- Completion: a course without saved rules and with no required quiz completes when all lessons are done; with a required quiz it needs the quiz passed at 60. A saved rule with `require_quizzes = true` and no required quiz is still not met.
- ManageQuiz settings form: placeholder `60` and helper text mention the default (AR and EN); the manage-quiz page shows `60%` with the platform default note for a null mark.

## O-028
- `SafeguardingRecipients::all()`: active users with role admin or support; inactive ones excluded; `config(['safeguarding.recipient_roles' => ['admin']])` drops support; unknown role names ignored; `AiSettings::safeguarding_extra_recipients = "12, a@b.test"` adds that user id and email; no duplicates.
- AI flag: a non-adult learner sends a self-harm message (guard Safeguarding): flag row created, each recipient gets one database notice with event `safeguarding.alert`, a mail copy, url under `/admin/help-requests`; the notice contains none of the learner text (assert absent in the notification `data`). A known adult raises no flag and no notice. A second dispatch for the same flag sends nothing (dedupe).
- Minor help request: not_understood feedback by a minor notifies the group with url `/admin/help-requests/{id}`; an adult does not (instructor notice only).
- Mandatory: `NotificationMatrix::find('safeguarding.alert')->mandatory` is true; `NotificationPreferences::allows` is true for any recipient; AR and EN texts exist for title and body, for `notification_events.events.safeguarding_alert` and for group `safeguarding`.
- Audit: event `safeguarding.notified` with `source`, `case_id`, `recipients`, `sent_now`, no text; AR/EN labels exist in `audit-events`. A failing channel is reported and does not undo the flag.

## O-015
- `SendGuardianOtp`: non-parent or inactive refused; unverified email refused (`guardian.otp.verify_email_first`); a code is mailed (Notification fake, channel mail only, not database), 6 digits, stored only as a hash, expires in 10 minutes, attempts reset; a second request within 60 s fails (`wait`); more than 5 per hour fails; audit `guardian.otp_sent` has no code.
- `ConfirmGuardianOtp`: a correct code sets `verified_at`, clears the hash, audits `guardian.verified`; a wrong code increments attempts and audits `guardian.otp_failed` (the counter persists after the exception); the 6th try even with the right code is refused (`locked`) until a new code is sent; expired and never-requested codes are refused; non-digit input is refused.
- `GuardianVerified::forGuardian` needs active + verified email + `verified_at`; `forChild` needs a verified link to such a guardian (a pending link does not count); `counts()` returns verified and pending.
- `/family` page (parent, EN and AR with RTL): shows the not verified state with the send button, after sending shows the code form, after confirming shows the verified badge; POST `/guardian/verification/code` and `/guardian/verification` need auth and are throttled; JSON errors are keyed `code`.
- Gate: with `config(['guardians.require_verification_for_ai' => true])` (phpunit.xml sets it false) the existing minor happy path (consent + AiMinors flag) returns `UnavailableReason::GuardianUnverified` until the guardian is verified, then available; a minor with the flag off is still `MinorsOff`; an adult is unaffected. Confirm no code path enables `PlatformFlag::AiMinors` by default.
- Arch: new actions are final with strict types; the I01 invariant still passes.
