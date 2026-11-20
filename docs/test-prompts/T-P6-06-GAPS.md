# Tests prompt: T-P6-06 Remaining Test Gaps (`A2` Rendered Arabic & English Certificate PDF Visual Check)

You are closing the remaining test gap for `T-P6-06` (Certificates and verification). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## What already passes
- `tests/Feature/Learning/CertificateIssuanceTest.php` and `CertificateVerifyAndPdfTest.php` verify issuance, name snapshot locking, revocation/reissue, `CertificatePdf::data()`, QR SVG generation, and `/verify/{token}`.
- `tests/Browser/CertificateVerificationBrowserTest.php` captures `verify-{locale}-{theme}-{width}` and `certificate-templates-*` screenshots in `tests/Browser/screenshots/T-P6-06/`.

## Missing evidence to add (`A2` Rendered PDF Layout & Arabic Shaping Check)

### Extend `tests/Browser/CertificateVerificationBrowserTest.php`
Tag with `->group('T-P6-06', 'A2', 'REQ-CP-003', 'REQ-CP-004', 'YG-GAP-CRT-001', 'browser')`:
- For both `['ar', 'en']`:
  1. Create a completed learner (`certificate_name_ar: 'نور محمد سعيد'`, `certificate_name_en: 'Nour Mohamed Said'`) and issue a certificate via `IssueCertificate`.
  2. Render the exact standalone HTML passed to Chromium (`view($template->view(), app(CertificatePdf::class)->data($certificate, $locale, $template))->render()`).
  3. Serve that rendered HTML on a temporary testing route (or load in Playwright Chromium via `page()->setContent($html)` / test-only signed route if registered cleanly in test bootstrap, resized to A4 landscape `1123×794`).
  4. Wait for `document.fonts.ready` (confirming embedded `Noto Sans Arabic` is loaded with non-zero bounding width and no missing glyphs / presentation-form disconnects).
  5. Assert the certificate name in `$locale`, course title, completion date, verification code `$certificate->number`, inline QR `<svg>`, and `document.documentElement.dir` (`rtl` for `ar`, `ltr` for `en`) are visible with zero horizontal/vertical overflow past the A4 landscape sheet and `assertNoJavaScriptErrors()`.
  6. Save visual snapshots in `tests/Browser/screenshots/T-P6-06/pdf-ar-landscape.png` and `tests/Browser/screenshots/T-P6-06/pdf-en-landscape.png` for visual inspection.

## Verification commands
```bash
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Feature/Learning/CertificateIssuanceTest.php tests/Feature/Learning/CertificateVerifyAndPdfTest.php
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Browser/CertificateVerificationBrowserTest.php
php artisan app:req-coverage --task=T-P6-06
```
