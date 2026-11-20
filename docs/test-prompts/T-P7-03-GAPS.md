# Tests prompt: T-P7-03 Remaining Test Gaps (`A2` All Assistant Panel States Across AR/EN × Mobile/Desktop)

You are closing the remaining test gap for `T-P7-03` (Student assistant panel UI). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## What already passes
- `tests/Feature/AI/AssistantPanelTest.php` covers HTTP/JSON endpoints (`assistant.show`, `assistant.ask`), citation/key-term responses, `BUG-008` opener visibility `< 2xl`, and PHPStan cleanliness.
- `tests/Browser/AssistantPanelBrowserTest.php` currently captures only the initial idle state (`assistant-panel-{ar,en}-{390,1440}.png` in `tests/Browser/screenshots/T-P7-03/`).

## Missing evidence to add (`A2` Every Panel State in AR/EN × Mobile/Desktop)

### Extend `tests/Browser/AssistantPanelBrowserTest.php`
Tag with `->group('T-P7-03', 'A1', 'A2', 'D-017', 'ED-009', 'browser')`:
- Across `['ar', 'en'] × [[390, 844], [1440, 900]]`, exercise and screenshot every panel state in `tests/Browser/screenshots/T-P7-03/`:
  1. **`answered`** (`assistant-state-answered-{locale}-{width}.png`): submit a question matched by `FakeLessonAiProvider` (e.g., matching page 1 text `'Primary colors'`); wait for `[data-ai-panel][data-state="answered"]`, `[data-ai-pages]` page citation chip (`[data-page]` / link), and the 3 action buttons (`[data-ai-action="understood"]`, `[data-ai-action="simpler"]`, `[data-ai-action="still"]`).
  2. **`not_in_source`** (`assistant-state-not-in-source-{locale}-{width}.png`): submit an off-PDF question triggering `not_in_source`; assert refusal notice inside `[data-ai-notice]` and visible human-help action (`[data-ai-request-help]`).
  3. **`out_of_scope`** (`assistant-state-out-of-scope-{locale}-{width}.png`): submit an injection / out-of-scope / quiz-active question triggering `out_of_scope`; assert refusal notice.
  4. **`quota`** (`assistant-state-quota-{locale}-{width}.png`): pre-exhaust daily/monthly learner quota (`ai.quota.*` / `AiUsage` or `QuotaReservation`) so `POST assistant.ask` returns `429` / reset notice; assert quota exhausted notice renders visibly with reset time.
  5. **`unavailable`** (`assistant-state-unavailable-{locale}-{width}.png`): trip circuit breaker / disable lesson or feature mid-session before asking; assert unavailable notice with `[data-ai-retry]` or contact link renders cleanly.
- On every state and viewport, assert:
  - `document.documentElement.dir === ($locale === 'ar' ? 'rtl' : 'ltr')`
  - `document.documentElement.scrollWidth <= innerWidth` (no horizontal overflow)
  - `assertNoJavaScriptErrors()` and `assertNoAccessibilityIssues()`.

## Verification commands
```bash
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Feature/AI/AssistantPanelTest.php
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Browser/AssistantPanelBrowserTest.php
php artisan app:check-translations
php artisan app:check-rtl
php artisan app:req-coverage --task=T-P7-03
```
