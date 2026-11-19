# T-PASSMARK-RECIPIENTS: tests prompt

Write Pest tests (no production code changes). Follow existing style in `tests/Feature/Assessment/DecisionsQuizPassMarkTest.php` and `tests/Feature/AI/`.

## A. Course-level quiz pass mark (O-020)

Code: `QuizPassMark` (`effective(?int $explicit, ?int $course = null)`, `inherited(Quiz)`, `forQuiz(Quiz)`), migration `2026_11_26_100000_add_quiz_pass_mark_to_courses` (`courses.quiz_pass_mark_percent`, nullable tinyint), `SaveCompletionRules` (optional key `quiz_pass_mark_percent`, audit `course.quiz_pass_mark_saved`), `ManageCourseCompletion` editRules modal.

Cases:
- `effective(null)` = 60; `effective(null, 75)` = 75; `effective(80, 75)` = 80; out-of-range course values (0, 101, -5) fall back to the platform default; out-of-range explicit falls to course then default.
- `forQuiz`: quiz in a course with `quiz_pass_mark_percent = 70` and quiz mark null gives 70; quiz mark 90 gives 90; course null gives platform default (change `config assessment.default_pass_mark_percent`); quiz with no curriculum item gives default.
- `QuizSnapshot::settings` freezes the course mark into `pass_mark_percent`; changing the course mark after an attempt starts does not change that attempt; a new attempt picks the new mark. Submit an attempt scoring 65% with course mark 70 gives `passed = false`, with course mark 60 gives true (via `QuizScoring::apply`).
- `SaveCompletionRules`: saves `quiz_pass_mark_percent` 70; '' clears to null; 0 and 101 and 'abc' raise ValidationException; key absent leaves the value untouched; unauthorized user gets AuthorizationException; audit `course.quiz_pass_mark_saved` has before/after only when changed; the existing `course.completion_rules_saved` audit payload is unchanged (no new key).
- Livewire: `ManageCourseCompletion` editRules action shows the field filled with the saved value and saves it.
- ManageQuiz form placeholder shows the course mark when set, else the platform default.
- Existing tests for the 60 default and explicit quiz marks still pass.

## B. Safeguarding extra recipients form (O-028)

Code: `SaveSafeguardingRecipients` (`__invoke(User, array|string|null)`, `emails()`, `MAX_EMAILS = 20`), page `SafeguardingRecipientsSettings` (slug `safeguarding-recipients`, permission `system.settings.manage`), hub entry `safeguarding`, setting `ai.safeguarding_extra_recipients`, resolver `SafeguardingRecipients`.

Cases:
- Action: admin and super_admin can save; support, instructor, learner and inactive admin get AuthorizationException.
- Emails are trimmed, lowercased and deduplicated (`A@x.test, a@x.test` stores one); free text separated by commas, semicolons, spaces and newlines is accepted; invalid email raises ValidationException naming it; 21 addresses raise ValidationException, 20 pass; empty list stores null.
- Numeric user ids already in the setting (`12, old@x.test`) are preserved when emails change; `emails()` lists only emails.
- No change (same list) writes no audit and does not save.
- Audit `settings.safeguarding_recipients_saved` has before/after email lists and the actor id; failing audit rolls back the setting.
- Resolver: after saving `new@x.test`, `SafeguardingRecipients::all()` includes the active user with that email, excludes an inactive one, and has no duplicates when that user also holds the admin role.
- Page: admin sees it in `SettingsHub::entries()` with key `safeguarding`; support does not; direct GET returns 403 for support; Livewire `edit` action fills the current emails and saves; invalid input shows a danger notification and the action halts.
- Navigation: after the `StaffNavigation::GROUPS['settings']` line is added, the page appears under Settings in the admin panel and not under "More".
- AR and EN: `settings.safeguarding.*`, `settings.hub.entries.safeguarding`, `completion.quiz_pass_mark*` exist in both locales (`php artisan app:check-translations`).
