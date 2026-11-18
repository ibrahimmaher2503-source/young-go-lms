# Test prompt: PO decisions of 2026-11-18

Write Pest tests (no production code changes) for the acceptance checks below. Use `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest`. Never make a real HTTP call: use `Http::fake()` / `Http::preventStrayRequests()`.
Note: `phpunit.xml` relaxes four-eyes and the minimum payout for legacy tests (`SETTLEMENT_FOUR_EYES=false`, `SETTLEMENT_MIN_PAYOUT_*_MINOR=0`). New settlement tests must set `config(['commissions.settlement.four_eyes' => true, 'commissions.settlement.minimum_payout_minor' => ['EGP' => 50000, 'SAR' => 10000]])` themselves.

## O-008 / O-009 / O-010 / O-011 commission (tests/Feature/InstructorFinance)
1. With no rule, an individual course sale of 10000 minor (no discount) by an independent instructor writes an earning of 7000, `rule_source=config_default`, `rate_bps=7000`.
2. An academy-owned course (no `owner_instructor_id`) writes no ledger entry.
3. A per-instructor or per-course rule still overrides the 70% default (precedence unchanged).
4. A bundle line is allocated proportionally to member list prices; each course entry is 70% of its allocated share; the sum of shares equals the line net.
5. A plan (subscription) period priced 9000 minor with 3 courses writes 3 entries, `sale_type=subscription`, each base 3000 and amount 2100 (total 6300 = 70%). With a remainder (10000 over 3 courses) shares sum to exactly 10000 and entries total round-half-up per share. Entry keys `subscription_period:<id>:course:<id>`; replaying the order adds nothing.
6. A fixed-amount subscription rule still wins over the percentage default. `commissions.default_subscription_fixed_minor > 0` also replaces the rate.
7. Subscription refund: a full refund of a plan order reverses each plan earning to exactly 0 net (entries `refund:<id>:entry:<earning>`); a 50% partial refund reverses about half of each (round half up, cumulative). Plan base uses `allocated_minor` when set (order-level coupon) else `unit_price - discount`; gross/discount/base per course sum to the line totals even with a remainder.
8. Discounted line: base is net of discount; `gross_minor`, `discount_minor`, `base_minor` stored. A partial refund reverses the entry proportionally.

## O-012 settlements
1. Four-eyes: finance user A creates a batch; A approving raises `ValidationException` with key `settlement` and the `settlements.four_eyes_refused` text; the batch stays `draft`, entries stay `pending`, no audit row. Finance user B approves successfully. A `super_admin` who created the batch may approve. With `commissions.settlement.four_eyes=false` the creator may approve.
2. Re-approving an approved batch is still idempotent (returns it).
3. Minimum payout: an instructor whose net is below 50000 (EGP) is not settled and appears in `carried_forward` with reason `below_minimum`; at exactly 50000 it is included. SAR threshold 10000. If every instructor is below the minimum the call fails with `nothing_to_settle`. The below-minimum entries stay `accrued`.
4. `settlements.carry_below_minimum` and `settlements.four_eyes_refused` exist in AR and EN; the batch details view renders the `below_minimum` reason.

## O-014 OpenAI adapter (tests/Feature/AI)
1. `ProviderRegistry::make('openai')` returns an `OpenAiProvider`; `keys()` includes `openai`; with `ai.provider=openai` and no key `activeKey()` is not `openai`; with a key it is `openai` (outside local/testing the default stays `unconfigured` without a key).
2. Without a key `complete`, `stream`, `embed`, `ocrPage` throw `AiProviderNotConfigured` and make no HTTP request; `health()` is unhealthy.
3. With a key and `Http::fake()` returning a chat-completions body, `complete()` returns content, usage (prompt/cached/completion tokens), request id, finish reason, model; the request has a Bearer header, `user` = the student hash, `max_completion_tokens`, and the system prompt equals `cacheablePrefix`. Base URL and model come from config; an AiSettings model choice wins.
4. HTTP 500 throws `RuntimeException` whose message contains the status only (no body); wrapped in `ResilientProvider`, repeated failures open the circuit and `AiProviderNotConfigured` does not count.
5. `embed()` returns one vector per text and throws on a count mismatch.
6. Minors: with the OpenAI provider active and `ai.openai.allow_minors=false`, a non-adult (even with verified guardian consent and the minors flag on) gets `UnavailableReason::MinorsOff`; an adult (18+) is available. `ai.adult_age` defaults to 18.
7. No test performs a real network call (`Http::preventStrayRequests()`).

## Other providers and config
1. Bunny Stream: `MediaProviders::for('bunny')->validate(['url' => 'https://iframe.mediadelivery.net/embed/12345/<uuid>'])` returns provider `bunny`, external_id the lowercased uuid, metadata library_id, tier T3; `/play/` works; HLS `https://vz-abc.b-cdn.net/<uuid>/playlist.m3u8` stores the url; http, other hosts, bad uuid, extra path are refused with `media.invalid_bunny`. Descriptor of an embed asset is `embed` with the iframe URL and the sandbox. The curriculum page offers `bunny` (lang key `curriculum.sources.bunny`, AR/EN).
2. Live: `ManualMeetingProvider` accepts `https://meet.google.com/abc-defg-hij` for provider `meet`, still refuses http and credentials in the URL. (Behaviour already generic; this is a regression check.)
3. Paymob KSA: `config('markets.SA.gateway')` and `config('payments.gateway_by_currency.SAR')` are `paymob`; `config('markets.SA.active')` is false and `Market::isActive` is unchanged (Saudi still closed).
4. Mail: `config('mail.mailers.ses.transport') === 'ses'`; the default mailer in testing remains `array`.
5. Sentry: `config('observability.error_reporter')` is `sentry`; with an empty DSN `app(ErrorReporter::class)` is a `LogErrorReporter`; with a DSN set it is a `SentryErrorReporter` that logs locally and does not throw when the SDK is absent.

## Docs
- `docs/07_DECISION_REGISTER.md` has section D with all rows; every decided O-xxx row in section C is marked `DECIDED 2026-11-18, see D`; O-001, O-003, O-007, O-019, O-022, O-026, O-027 are not marked; O-006 and O-013 say credentials/entity still OPEN.

## Gates to run
Arch, tests/Feature/Invariants, InstructorFinance, Commerce, AI, Media, Live; `php artisan app:check-translations`; `php artisan app:check-rtl`; phpstan `--memory-limit=2G`; pint on changed files.
