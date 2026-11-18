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

## 2026-11-18 clean-clone recheck

- **Project and milestone** Young Go LMS, T-P0-MG. The board lists T-P0-01 through T-P0-07 as `done` (A1). Status for this gate is tracked in [PROGRESS](../../../agents/PROGRESS.md).
- **Repository and revision** [young-go-lms](https://github.com/ibrahimmaher2503-source/young-go-lms), `feat/lms-build` at `1c471d4abe6f6945cbb30cd50c463f5c658e4940`. The demo used a fresh clone from the local repository at that commit. GitHub Actions returned zero runs for the exact revision.
- **Environment and demo** Windows, PHP 8.4.21, Node 24.15.0, SQLite, file cache, synchronous queue. Locked Composer and npm installs, application key generation, Filament asset publishing, and the Vite build passed. The booted Laravel HTTP kernel returned 200 for `/up`.
- **Database and scheduler** `migrate:fresh --seed` ran in the clone's new database. The seed produced 28 settings, 10 roles, 64 permissions and zero age bands. `schedule:run` followed by `app:doctor` exited 0 and reported AI for minors, Saudi checkout and live Paymob collection off. The local mail check verifies transport configuration. Persistent scheduler and worker services require separate integration evidence.
- **Deployment, domain and recovery** Local demo only. Staging and production hosts, deployed revisions, the actual legacy hostname, DNS/cutover state, backup and rollback evidence remain UNKNOWN. `youngschool.com` is the owner-stated hostname and remains unconfirmed under O-001. The preview uses provisional colors and identity under O-027.
- **Next milestone** P1 remains in acceptance review. See the P1 milestone record for its dependency, security and visual gates.

### Demo and visual evidence

The focused foundation run passed 23 tests and 40 assertions across the health route, token preview, shared primitives and phone normalization. The standalone Windows PHP server threw a Symfony Finder bootstrap error. The Laravel kernel and Pest browser transport supplied the health and preview evidence. The setup-to-`artisan serve` path still needs a clean run.

The existing `DesignTokenPreviewTest` passed eight browser cases and 32 assertions. A capture-only copy of that test in the isolated clone produced eight [full-page captures](../../../agents/logs/p0-mg-2026-11-18/preview-matrix/) for AR/EN × 390×844 and 1440×900 × light/dark. Root inspected all eight. Headings and controls mirror between locales, the swatch grid contracts from four to two columns, and state pills wrap without clipping at 390px. The build measured WCAG AA contrast for all 34 configured pairs.

The [shared component board](../../design-system/reference-pack-2026-11-17/18-shared-component-library-v2.png) and [approved Users reference](../../design-system/reference-pack-2026-11-17/00-owner-approved-users.png) show comparable teal, light surfaces, dark type and green/red status semantics. The token diagnostic uses large borderless swatches and iconless pills. Its processing pill is beige and its review pill peach, where the board shows a blue processing pill and an amber review pill with icons. The references show different screens and supply no like-for-like AR/EN light/dark diagnostic layout. G7 visual fidelity remains open.

Raw logs and images are local artifacts under `agents/logs/p0-mg-2026-11-18/`.

### Gate results

| Gate | Result on `1c471d4` |
|---|---|
| G1 Format | FAIL. `composer qa` exited at Pint with 10 files flagged. |
| G2 Static analysis | PASS. Larastan exited 0. |
| G3 Tests and coverage | FAIL. Pest parallel with PCOV reported 732 passed, 40 failed, 2 TODO and 4,654 assertions. Entitlements 91.7%, Commerce 0.0%, AI 83.4%. InstructorFinance has no implementation to measure. |
| G4 Architecture | PASS. Six tests and 42 assertions with a 1 GiB PHP memory limit. |
| G5 Invariants | FAIL. Eight passed with 35 assertions. I02 remains TODO, so exit 0 does not close the gate. |
| G6 Hygiene | Required commands passed for translations, RTL, locked Composer audit, clone migration/seed and build. The separate npm audit reported six high-severity entries among development dependencies. |
| G7 Visual | OPEN. Eight browser cases and captures passed. Component comparison identified the differences above. |
| G8 AI, G9 payments | N/A to this P0 acceptance iteration. |
| G10 Traceability | PASS for `--task=T-P0-MG` and `--phase=P0`. |
| G11 Doctor | PASS after the isolated migration/seed and scheduler heartbeat. |

The independent PRD source check (`/root/prd_auditor`, `1c471d4`) confirmed the task selection and A1. Root recorded A2 in this section and linked it from both continuity documents. The independent auditor did not sign off on the final record. No independent visual or browser QA verdict was recorded.
