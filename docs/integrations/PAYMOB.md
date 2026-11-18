# Paymob integration (Egypt) — T-P8-02

Status: adapter written, inert. Credentials are not available (blocker B-07, O-006, O-023). **The notes below were written from the documented Intention API as recalled; the official docs could not be fetched in this session. Verify every endpoint, field and the HMAC field list against the current Paymob docs before the first staging test.**

## Flow
1. `StartCheckout` (payer, own pending order) reserves a `payment_attempts` row (reference `<ORDER-REF>-A<n>`), then `PaymentGateway::createCheckout`.
2. `PaymobGateway` calls `POST {base}/v1/intention/` with header `Authorization: Token <secret key>`. Body: `amount` (minor units), `currency` (EGP), `payment_methods` (integration ids), `items`, `billing_data`, `special_reference` (= attempt reference), `notification_url` (server callback), `redirection_url` (return page), `extras`.
3. Response carries `client_secret` and the intention/order ids. Checkout URL: `{base}/unifiedcheckout/?publicKey=<public key>&clientSecret=<client_secret>`; the payer is redirected there. The URL is stored encrypted on the attempt.
4. The browser returns to `/checkout/return` (status display only, never marks paid). Paymob posts the processed transaction to `/webhooks/paymob` (T-P8-03, `ProcessPaymentWebhook`): no session/CSRF/auth, signature (`hmac` query or `X-Payment-Signature`) verified before any write, one `payment_events` row per transaction id + state, amount/currency checked against the server order; anything unsafe is stored as `flagged` for finance (no entitlement). A failed attempt leaves the order pending so the payer can retry.

## Callback signature (verifyCallback, implemented)
HMAC-SHA512 (hex, key = HMAC secret) over the concatenation of these fields of `obj`, in this order, booleans as `true`/`false`: `amount_cents, created_at, currency, error_occured, has_parent_transaction, id, integration_id, is_3d_secure, is_auth, is_capture, is_refunded, is_standalone_payment, is_voided, order.id, owner, pending, source_data.pan, source_data.sub_type, source_data.type, success`. Compared with `hash_equals` to the `hmac` query value. The attempt is found by `special_reference` (attempt reference); T-P8-03 must also match amount and currency to the attempt/order.

## Transaction lookup (fetchTransaction)
`POST {base}/api/auth/tokens` with the API key to get a short token, then `GET {base}/api/acceptance/transactions/{id}` with `Bearer`. Used by reconciliation (T-P8-11).

## Configuration (`config/payments.php`, env only)
`PAYMENTS_GATEWAY` (auto|fake|null), `PAYMENTS_LIVE_ENABLED` (false), `PAYMOB_BASE_URL`, `PAYMOB_SECRET_KEY`, `PAYMOB_PUBLIC_KEY`, `PAYMOB_API_KEY`, `PAYMOB_HMAC_SECRET`, `PAYMOB_INTEGRATION_CARD`, `PAYMOB_INTEGRATION_WALLET`. Method list and order: `payments.methods` (cards, wallets first). A key containing `_live_` is ignored (Null gateway) until `PAYMENTS_LIVE_ENABLED=true`, which needs the owner gates in `docs/09_CODEX_START_HERE.md`.

## Resolution
Complete credentials: Paymob. None: `FakePaymentGateway` in local/testing, `NullPaymentGateway` (refuses everything, no network) elsewhere.

## Security rules
Secrets only from env, sent only in the Authorization header, never stored, logged or put in exception text (`SecretRedactor`); errors carry the HTTP status only. Amounts come from the locked server order. No card data is stored; callback payloads are reduced to non-sensitive fields.

## Test cards / wallets
UNKNOWN until sandbox credentials exist; copy from the Paymob test-credentials page into this file then.
