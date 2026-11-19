# Handoff prompt: continue Young Go LMS after P9

Paste the block below into a new session (Claude Code or Codex) opened at the repo root.

---

You are the orchestrator/PO for the Young Go LMS (Laravel 12, Pest, Filament, Livewire). Repo: `young-go-lms`, branch `feat/lms-build`. Follow `AGENTS.md` and `agents/ORCHESTRATOR.md`. The board is `agents/PROGRESS.md`. Reply to the owner in Arabic. Run PHP tests with `php -d memory_limit=2G vendor/bin/pest <path>` and run `npm run build` first (stale Vite manifest causes 500s).

## State (2026-11-19)
- P0-P9 code and acceptance tests are written and passing. G7 visual review: P8 and P9 accepted by the owner with known design-only defects (see `docs/reviews/GATE_READINESS_P8_P9.md`, `G7_P8_REVIEW4_*`, `G7_P9_REREVIEW_*`). Accepted backlog: D2, C1, H1, X6, X7, W1b, P1, R2.
- Decisions approved by the owner are in `docs/DECISIONS_2026-11-18.md` and `docs/07_DECISION_REGISTER.md` section D (commission 70/30, subscription split, OpenAI, Paymob KSA, Bunny Stream, SES, Google Meet, Sentry, brand accepted).
- Performance budget measured: `docs/reviews/PERF_BUDGET_P8_2026-11-19.md` (catalog LCP 1.76 s, CLS 0 after the font/image fixes). Course detail and checkout were not re-measured after the fixes.

## Do first
1. Run: `pest tests/Arch tests/Feature/Invariants`, then each Feature folder; `php artisan app:check-translations app:check-rtl app:check-brand-tokens`. Fix any regression before anything else.
2. Re-measure LCP/CLS for `/courses/{slug}` and the checkout order page (method in the perf doc), append results.
3. Close `T-P8-MG` and `T-P9-MG` only when every criterion in `agents/ORCHESTRATOR.md` section 8.3 is met; the owner-fact items below may keep them open. Never mark a gate done on assumption.
4. Update `PROJECT_STATE.md` and `HANDOFF.md` with the continuity record.
5. Tests: there are test prompts in `docs/test-prompts/` for most tasks; write missing tests with a lower model (Haiku), not the model that wrote the code. Uncovered items are listed at the end of each earlier test report (Filament payout views, StatementPdf, bundle coupon base, OrderResource columns, cohort late-payment and renewal paths).

## Blocked on the owner (do not invent these; ask once, record in the decision register)
- O-001 real legacy domain/DNS (`youngschool.com` is an unrelated US site).
- O-003 legacy source/DB/storage access (blocks T-P10-01..08).
- O-007 legal seller name, tax id, VAT; O-006 Paymob credentials (real gateway stays off until given).
- O-013 Saudi legal entity (T-P12-01); O-019 certificate issuer wording; O-022 dates/budget; O-023 ETA obligation; O-025 retention values; O-014 OpenAI key/region/terms (T-P7-07).
- Owner repo decisions: fast-forward `main` from `feat/lms-build`; delete or restore `docs/16_REVIEW_FINDINGS_AND_DECISIONS.md`; restore the codex `WatermarkBrowserTest` checks removed in a merge; move Telescope from `require` to `require-dev`.

## Next work in dependency order (after gates are satisfied)
P10 legacy import (T-P10-01 inventory is prepared in `docs/migration/INVENTORY.md`, awaiting source access), then P11 (regression and security review, performance, backups/restore, production config, training guides, cutover), then P12 (Saudi activation: `Market::isActive` currently hard-codes Saudi inactive; this task owns activation).
Known unimplemented items: cohort chooser at checkout, bundle/plan cohort seats, seat release on refund, course-level pass-mark admin UI in the wizard, O-025 retention values.

## Rules that matter
- Layered Domain under `app/Domain/*` (final Actions/Queries); the entitlements table is touched only through the Entitlements module (Invariant I01); Arch tests must pass.
- Brand colors and fonts live only in `resources/css/tokens.css` (mirrored by `config/brand.php`); `app:check-brand-tokens` guards it. See `docs/brand/CHANGING_THE_BRAND.md`.
- Stage commits with explicit paths. The working tree may hold other people's uncommitted work (Identity 2FA, Telescope, permissions rename, `phpstan.neon`, `tests/Browser`); do not sweep it into your commits.
- Use scratch SQLite databases for visual reviews; never touch `database/database.sqlite`.
- For parallel agents, give disjoint file ownership; re-read shared files (lang, CSS, StaffNavigation) before editing.
- G7 review method: independent agent, scratch DB, Playwright at 390/1440 AR+EN, six-dimension mean, bar 4.5 (per-class 4.3 without a reference, every dimension >= 4.0).
