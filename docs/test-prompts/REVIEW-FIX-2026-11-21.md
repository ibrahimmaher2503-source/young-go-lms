# REVIEW-FIX-2026-11-21: tests for the code-review fixes on top of SEC-7

Write Pest tests only. Do not change production code; if a test exposes a bug, stop and report it.
Read `docs/test-prompts/SEC-7.md` first: its 5 cases are still required and still expected to pass unchanged.

## Changed files
- `app/Domain/Commerce/Actions/ProcessPaymentWebhook.php`: `decide()` now handles `refunded` / `voided` / `pending` before the provider-order binding checks; `resolveAttempt()` orders by newest id.
- `database/migrations/2026_11_30_200000_add_performance_query_indexes.php`: adds `payment_attempts_gateway_provider_order_idx` (gateway, provider_order_id); no longer adds `orders_payer_id_idx`.
- `app/Filament/Pages/LearnerThreeSixtyPage.php`: the cached record is keyed by learner id.
- `app/Domain/Reports/Queries/PlatformOverviewReport.php`: user/role counts use `config('permission.table_names.*')` and bound role names; memo removed; `totals()` builds the row once.

## Cases (put commerce cases in `tests/Feature/Commerce/`)
- A1 Signed Paymob **refund** callback on an attempt with `provider_order_id = null` → `Flagged`, reason `refund_or_void_received` (not `missing_provider_order`); no state change.
- A2 Same for **voided**, and for an attempt whose `provider_order_id` differs from `obj.order.id` (reason still `refund_or_void_received`).
- A3 Signed **pending** callback on a mismatched / null-provider-order attempt → `Ignored`, reason `pending`.
- A4 Signed **failed** callback where `obj.order.id` differs from the attempt's `provider_order_id` → `Flagged` `provider_order_mismatch`; attempt stays open (binding stays strict for failures).
- A5 Signed **failed** callback with a matching provider order id and reference → `AttemptFailed`, attempt `Failed`, `active_order_id` null.
- A6 Two paymob attempts sharing a `provider_order_id` → the webhook resolves the one with the highest id.
- A7 Migration: after `migrate`, `payment_attempts` has an index named `payment_attempts_gateway_provider_order_idx`, and `orders` has no `orders_payer_id_idx` (use `Schema::getIndexes`).
- A8 Platform overview report: the users / students / parents / instructors counts are right for a user holding several roles (counted once in total, once per role bucket), for a user with no role, and for a role on another guard (not counted). `totals()` equals the single row.
- A9 Learner 360 page: mount for learner A, then render data for learner B in the same component instance (set `learnerId` and call `getViewData` through reflection, or via Livewire test) → record belongs to B, not A.

## Notes
- Paymob HMAC: build callbacks the way `PaymobWebhookFulfillmentTest` does.
- SQLite test DB: `Schema::getIndexes` works; don't rely on InnoDB locking.
- Run with `php -d memory_limit=2G vendor/bin/pest` (128M default fails on the Demo seeder tests).

## J05 grounded fake provider (2026-11-21)

Write Pest unit tests for `App\Domain\AI\Providers\FakeAiProvider` with `grounded: true`, run through `AssistantEngine` + `OutputGuard`:
- A question sharing words with a page sentence returns `answered`, cites that page, and the quote passes `OutputGuard` (quoteVerified true).
- An off-topic question returns `not_in_source`.
- A queued `respondWith()` answer still wins over the grounded answer.
- `new FakeAiProvider()` (default) still returns `DEFAULT_ANSWER`; `ProviderRegistry::make('fake')` in the testing env is not grounded, and `config(['ai.fake_grounded' => true])` makes it grounded.
