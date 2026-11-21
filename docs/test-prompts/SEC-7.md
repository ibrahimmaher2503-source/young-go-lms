# SEC-7: Bind Verified Paymob Transactions to Checkout Attempt Provider Order ID

## Context
- **Finding:** High before Paymob activation (`SECURITY_REVIEW_2026-11-20.md`) — Related tasks `T-P8-02`, `T-P8-03`, `T-P11-01`.
- **Fixed files:**
  - `app/Domain/Commerce/Payments/PaymobGateway.php` (`createCheckout`)
  - `app/Domain/Commerce/Actions/ProcessPaymentWebhook.php` (`resolveAttempt`, `decide`, `success`)

## Reproduction / Attack Scenario
1. Paymob's processed-transaction HMAC signs `order.id` (`providerOrderId`), while `special_reference` / `order.merchant_order_id` (`attemptReference`) is unsigned callback metadata.
2. Previously, `ProcessPaymentWebhook` looked up the `PaymentAttempt` solely by unsigned `attemptReference` without checking `provider_order_id`, and `success()` backfilled `$attempt->provider_order_id ?? $verified->providerOrderId`. A signature-valid callback for transaction A (`order.id = 9001`) with tampered `special_reference` pointing to victim attempt B (whose `provider_order_id` was `9002` or `null`, for the same amount/currency) could fulfill victim order B.
3. Also, `PaymobGateway::createCheckout()` previously allowed creating a `CheckoutSession` even when `intention_order_id` was missing from Paymob's `/v1/intention/` response.

## Regression Test Requirements (write in `tests/Feature/Commerce/`)
1. **Checkout creation fails closed without `intention_order_id`:** Mock Paymob `/v1/intention/` returning `id` and `client_secret` without `intention_order_id`, call `PaymobGateway::createCheckout()`, and assert `PaymentGatewayException` (`bad_response`).
2. **Signed `order.id` wins over tampered `special_reference` (cross-attempt binding attack):**
   - Create two pending orders/attempts with the same amount and currency (`$attemptA` with `gateway = 'paymob'`, `provider_order_id = '9001'`; `$attemptB` with `gateway = 'paymob'`, `provider_order_id = '9002'`).
   - Send a valid Paymob-HMAC-signed webhook where `obj.order.id = 9001` (matching `$attemptA`), but tamper unsigned `obj.special_reference` to `$attemptB->reference`.
   - Assert `PaymentEvent` outcome is `PaymentEventOutcome::Flagged` (`reference_mismatch`), `$attemptB` and `$orderB` remain `Pending`, and zero entitlements are granted.
3. **Attempt with different `provider_order_id` rejects forged reference:**
   - Send a valid Paymob-HMAC-signed webhook where `obj.order.id = 9999` (unknown provider order) and unsigned `obj.special_reference` = `$attemptB->reference` (`provider_order_id = '9002'`).
   - Assert `PaymentEventOutcome::Flagged` (`provider_order_mismatch`), order stays `Pending`, zero entitlements granted.
4. **Attempt with missing `provider_order_id` fails closed on `paymob`:**
   - Create a `paymob` `PaymentAttempt` with `provider_order_id = null` and send a signed Paymob webhook with `obj.order.id = 8888` and `obj.special_reference = $attempt->reference`.
   - Assert `PaymentEventOutcome::Flagged` (`missing_provider_order`), `provider_order_id` is NOT backfilled, order stays `Pending`, zero entitlements granted.
5. **Matching `paymob` `order.id` + `special_reference` still fulfills cleanly.**
