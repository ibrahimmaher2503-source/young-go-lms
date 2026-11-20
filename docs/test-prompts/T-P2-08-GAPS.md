# Tests prompt: T-P2-08 Remaining Test Gaps (Admin Filament Actions + Browser/Screenshots)

You are closing the remaining test gaps for `T-P2-08` (Contact enquiries and newsletter). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## What already passes
- `tests/Feature/Pages/ContactAndNewsletterTest.php` covers `A1` and `A2` at the HTTP/domain level (contact submission, honeypot, rate limits, Egypt phone normalization, zero PII in audit/logs, newsletter double-submit idempotency, signed login-free unsubscribe).

## Missing evidence to add

### 1. Livewire table/action coverage (`tests/Feature/Pages/ContactAndNewsletterTest.php`)
Tag tests with `->group('T-P2-08', 'A1', 'A2', 'YG-WEB-007', 'YG-WEB-008')`:
- **`ListContactEnquiries` (`App\Filament\Resources\ContactEnquiries\Pages\ListContactEnquiries`):**
  - Users with `marketing.lead.view` (`marketing` role or `super_admin`) can mount `ListContactEnquiries`, search by name/email/message, and filter by `status` and `assigned_to_id`.
  - Users without `marketing.lead.view` (`admin`, `instructor_academy`, `student`) receive 403 when mounting `ListContactEnquiries` or calling actions.
  - `callTableAction('view', $enquiry)` displays formatted details and escapes `<script>` payloads in `message`.
  - `callTableAction('assign', $enquiry, data: ['assignee' => $handler->id])` assigns the enquiry, writes audit `contact.enquiry_assigned`, and rejects an inactive or unauthorized user without mutating `assigned_to_id`.
  - `callTableAction('status', $enquiry, data: ['status' => 'resolved'])` updates `status`, sets `handled_at`, writes `contact.enquiry_status_changed`, and clears `handled_at` when moved back to `in_progress`.
  - Header CSV export (`callAction('export')`) downloads UTF-8 BOM CSV with localized headings, prefixes leading `=`, `+`, `-`, `@` formula characters with `'`, respects table filters, and records one `lead.exported` audit entry (with no email/phone/message PII in `activity_log`).
- **`ListNewsletterSubscribers` (`App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers`):**
  - Users with `marketing.lead.view` can mount `ListNewsletterSubscribers` and filter by `state` (`subscribed`/`unsubscribed`) and `source`.
  - `callTableAction('unsubscribe', $subscriber)` is visible only when `isSubscribed()` is true, sets `unsubscribed_at`, and records `newsletter.unsubscribed` with `by = staff`.
  - Header CSV export downloads sanitized CSV and records `lead.exported`.

### 2. Browser + accessibility + screenshots (`tests/Browser/ContactAndNewsletterBrowserTest.php`)
Tag tests with `->group('T-P2-08', 'A1', 'A2', 'YG-WEB-007', 'YG-WEB-008', 'browser')`:
- Visit and capture full-page screenshots in `tests/Browser/screenshots/T-P2-08/` across `['ar', 'en'] × ['light', 'dark'] × [[390, 844], [1440, 900]]`:
  1. `GET /contact` (submitting via UI / asserting `[data-contact-form]` and honeypot non-focusability).
  2. `GET /newsletter` (asserting `[data-newsletter-card]` and consent checkbox).
  3. `GET $subscriber->unsubscribeUrl()` (asserting `[data-unsubscribed]`).
  4. `/admin/contact-enquiries` (populated and empty state).
  5. `/admin/newsletter-subscribers` (populated and empty state).
- Assert on every capture: `dir` matches `ar => rtl` / `en => ltr`, `scrollWidth <= innerWidth`, `assertNoJavaScriptErrors()`, and `assertNoAccessibilityIssues()` (including `aria-hidden-focus` clean on the honeypot).

## Verification commands
```bash
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Feature/Pages/ContactAndNewsletterTest.php
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Browser/ContactAndNewsletterBrowserTest.php
php artisan app:check-translations
php artisan app:check-rtl
php artisan app:req-coverage --task=T-P2-08
```
