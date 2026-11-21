# SEC-11: Default Telescope Off Outside Local & Exclude/Mask Payment/PII Routes

## Context
- **Finding:** Medium (`SECURITY_REVIEW_2026-11-20.md`) — Related tasks `T-P1-11`, `T-P9-08`, `T-P11-01`.
- **Fixed files:**
  - `config/telescope.php` (`enabled` default + `ignore_paths`)
  - `app/Providers/TelescopeServiceProvider.php` (`hideSensitiveRequestDetails`)

## Reproduction / Attack Scenario
Previously, `config/telescope.php` defaulted `'enabled' => env('TELESCOPE_ENABLED', true)`, and `ignore_paths` / `hideSensitiveRequestDetails()` omitted payment callback routes (`/webhooks/paymob`), manual payment proof routes, and payment/PII headers & fields (`hmac`, `signature`, `obj`, `billing_data`, `sender_reference`, `payer_phone`, `x-payment-signature`, etc.). Under default staging/production settings without `TELESCOPE_ENABLED=false`, failed requests on those routes could retain sensitive payer/provider payloads in `telescope_entries`.

## Regression Test Requirements (write in `tests/Feature/Identity/`)
1. Verify `config/telescope.php` defaults `enabled` to `false` when `TELESCOPE_ENABLED` is unset and `APP_ENV` is `'production'` or `'staging'`, and `true` when `APP_ENV` is `'local'`.
2. Verify `Request::create($path)->is(config('telescope.ignore_paths'))` is `true` for `/webhooks/paymob`, `/orders/1/manual-proof`, `/payments/manual-proofs/1`, `/checkout/1`, and `/payments/return`.
3. Verify `Telescope::$hiddenRequestParameters` and `Telescope::$hiddenRequestHeaders` include payment/PII fields (`hmac`, `signature`, `obj`, `billing_data`, `sender_reference`, `payer_name`, `payer_phone`, `x-payment-signature`, `x-paymob-hmac`, `authorization`) when `TelescopeServiceProvider` registers in non-local environments.
