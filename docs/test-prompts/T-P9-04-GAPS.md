# Tests prompt: T-P9-04 Phone Reminder Opt-In Audit & Delivery Isolation

You are closing the remaining test gaps for `T-P9-04` (`SetPhoneReminderOptIn` re-consent audit & active-account guard, `SendPhoneReminder` inactive skip / key fallback / per-channel isolation, and `NotificationDispatcher` secondary phone isolation). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first.

## Files and classes
- `App\Domain\Notifications\Actions\SetPhoneReminderOptIn`
- `App\Domain\Notifications\Actions\SendPhoneReminder`
- `App\Domain\Notifications\NotificationDispatcher`

## Behaviours and edge cases to test

### 1. Re-consent & account status in `SetPhoneReminderOptIn` (`tests/Feature/Notifications/PhoneChannelTest.php` / `PhoneChannelAcceptanceTest.php`)
- **Re-consent after phone number change:**
  1. Opt an active adult learner into `'whatsapp'` with phone `+201000000001` -> assert `'whatsapp' => '+201000000001'` and initial `notifications.phone_opt_in_changed` audit (`enabled: false -> true`, `reconsented: false`).
  2. Update learner's `phone` to `+201000000002` and call `SetPhoneReminderOptIn` with `$enabled = true` again -> assert `phone_reminder_opt_ins['whatsapp'] === '+201000000002'` AND a **second** `notifications.phone_opt_in_changed` audit is recorded (`reconsented: true`, **no** phone numbers in audit properties).
  3. Calling `SetPhoneReminderOptIn(..., 'whatsapp', true)` a third time with the **same** phone number remains a no-op for audit count.
- **Inactive account guard:**
  - Suspending/deactivating an account (`status = 'suspended'`) causes `SetPhoneReminderOptIn($user, 'whatsapp', true)` to throw `InvalidArgumentException`, while withdrawing consent (`$enabled = false`) still clears the opt-in and audits it.

### 2. Delivery guards & per-channel isolation in `SendPhoneReminder`
- **Inactive recipient skip:** An opted-in learner with `status = 'suspended'` logs `'phone_reminder.skipped'` (`reason: 'inactive'`) and sends zero phone messages.
- **Notice key fallback:** Calling `SendPhoneReminder` directly with a `LocalizedNotification` whose `$notice->id` is empty/null uses `$notice->dedupeKey()` as the idempotency prefix (`"{dedupeKey}:{channel}"`), never leading with `":"`.
- **Per-channel fault isolation:** When both `'whatsapp'` and `'sms'` are opted in and enabled, and the WhatsApp driver throws an exception, `sms` STILL sends and the exception is reported (`report()`) without aborting the loop.

### 3. `NotificationDispatcher` secondary phone isolation
- Configure `NotificationDispatcher::send($recipient, $notice)` where mail/database routing succeeds and `SendPhoneReminder` throws (or its phone driver throws):
  - Assert the `NotificationDispatch` row is **retained** (`NotificationDispatch::count() === 1`), email + database notice succeed once, and retrying `NotificationDispatcher::send($recipient, $notice)` returns `false` without sending a duplicate email.

## Verification commands
```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/Notifications/PhoneChannelTest.php tests/Feature/Notifications/PhoneChannelAcceptanceTest.php tests/Feature/Notifications/NotificationRoutingTest.php tests/Feature/Notifications/LiveNoticesTest.php tests/Arch
php vendor/bin/pint --test app/Domain/Notifications
```
