# Test Prompt: `SEC-01` — Telescope Telemetry Exclusion & Redaction Outside Local

Write automated Pest Feature tests (do **not** modify production code) verifying the Telescope exception/request telemetry hardening in `config/telescope.php` and `app/Providers/TelescopeServiceProvider.php` (`T-P11-01`).

## Target files & classes
- `config/telescope.php` (`enabled`, `ignore_paths`)
- `app/Providers/TelescopeServiceProvider.php` (`register()`, `hideSensitiveRequestDetails()`)

## Required attack / regression scenarios
1. **Default disabled outside local:**
   - Verify `config/telescope.php` sets `'enabled'` default to `false` when `APP_ENV` is `'production'` or `'staging'` (and `TELESCOPE_ENABLED` is unset).
2. **Sensitive payment & identity paths in `ignore_paths`:**
   - Extend/verify `Request::create($path)->is(config('telescope.ignore_paths'))` returns `true` for:
     - `/my/orders/12/payment-proof`
     - `/my/payments`
     - `/webhooks/paymob`
     - `/checkout/orders/6`
     - `/fake-gateway/FG-123`
     - `/login`, `/register`, `/forgot-password`, `/reset-password/tok`, `/guardian/children`
3. **Non-local `Telescope::filter()` drops entries for ignored URIs even when `isFailedRequest()` / `isReportableException()` is true:**
   - Simulate `app()->instance('env', 'production')` and register `TelescopeServiceProvider`.
   - Build an `IncomingEntry` of type `EntryType::REQUEST` (or `EXCEPTION`) with `content['uri'] = '/my/orders/12/payment-proof'` or `'/webhooks/paymob'` and `response_status = 500`.
   - Assert Telescope's registered filter callback returns `false`.
4. **Sensitive parameters & headers are masked outside local:**
   - Assert `Telescope::$hiddenRequestParameters` includes `child_password`, `code`, `recovery_code`, `recovery_codes`, `sender_account`, `sender_name`, `transfer_reference`, `payout_details`, `instapay_address`, `hmac`, `signature`, `obj`, `email`, `phone`.
   - Assert `Telescope::$hiddenRequestHeaders` includes `authorization`, `x-paymob-hmac`, `x-payment-signature`, `cookie`.

## Acceptance / Requirement IDs
- `T-P11-01`, `T-P1-11`, `T-P9-08`, `YG-GAP-SEC-003`

## Setup hints
- Use `RefreshDatabase` if booting full app; restore `app()->instance('env', 'testing')` in `afterEach`.
- **Rule:** Test writer must **not** modify production code.
