# P0 reproducible demo and closure evidence

Scope: T-P0-MG foundation demo only. Local Windows profile uses SQLite/file cache/synchronous queue. Linux MySQL/Redis/Horizon acceptance is separate hosted CI evidence. No legacy or production changes.

## Reproduce

1. In a fresh clone, follow README setup: install locked Composer/npm dependencies, copy env only if absent, generate app key, publish Filament assets, create SQLite only if missing, migrate/seed, and build. Never run migrate:fresh on a populated checkout.
2. Enable the documented opt-in token preview in local/staging. Install Playwright Chromium. Run `php vendor/bin/pest tests/Browser/DesignTokenPreviewTest.php` to exercise preview, AR/EN, both themes, both viewports, axe and overflow assertions.
3. Run `composer qa`, translation/RTL checks and `php artisan app:req-coverage --phase=P0`.
4. Run `php artisan schedule:run` followed immediately by `php artisan app:doctor`. This is a one-time scheduler smoke check, not evidence of an installed persistent scheduler service.
5. Start `php artisan serve`; visit `/up` and confirm HTTP200. The current audit also exercised this health route through the booted Laravel HTTP kernel, as recorded below.
6. Inspect non-sensitive seeded settings/role counts; do not expose values or secrets. Inspect flag output and confirm AI minors, Saudi checkout and live payments are off. The role rows reflect the existing P1 seed and are not P0 functionality claims.

## Execution record

Executed against the actual project checkout during the 2026-11-17 closure audit. Initial setup found no local SQLite file; a missing-only create and ordinary `migrate --seed` completed without resetting data. Production mail/legal/provider configuration was not supplied or invented.

| Step | Actual result | Evidence |
|---|---|---|
| HTTP kernel `/up` request | 200 | agents/logs/closure-health-route.txt |
| Locked QA | Pint/Larastan PASS, 166 tests/643 assertions, one downstream I02 TODO | agents/logs/closure-qa-after-repairs.txt |
| Preview/browser suite | Entire existing suite 26 tests/152 assertions PASS; P0 preview runs in this suite | agents/logs/closure-browser-current.txt; tests/Browser/DesignTokenPreviewTest.php |
| Visual matrix | 8 P0 screenshots exist; AR/EN ×390/1440×light/dark | tests/Browser/screenshots/T-P0-04/ |
| Build | Vite PASS; 34 contrast pairs PASS | command observed in closure audit; npm run build reproduces |
| G10 phase scope | PASS | agents/logs/closure-p0-g10.txt |
| Seed snapshot | 16 settings; 10 named role rows; 64 permissions; 0 age bands | agents/logs/closure-seed-summary.txt |
| Scheduler/doctor | Heartbeat ran; DB/migrations/storage/flags/mail-config applicable checks PASS | agents/logs/closure-scheduler.txt; agents/logs/closure-doctor.txt |
| Linux integration | Verified successful at a14fae2, run43967044917 | https://github.com/ibrahimmaher2503-source/young-go-lms/actions/runs/43967044917 |

## Limits

This demo substantiates the foundation and its provisional preview, not all product invariants or deployment readiness. I14/I15 are executable; I01–I13 await their owning features, I02 is explicit scaffolding, and I16 requires release/cutover evidence. Official branding remains O-027. Redis is intentionally skipped locally; delivering mail, production hosting, live payment, minor AI and cutover remain unverified/gated. Local closure repairs need exact-head hosted CI before integration. Long raw logs are local ignored artifacts; concise results and test/source paths above are durable recorded evidence.
