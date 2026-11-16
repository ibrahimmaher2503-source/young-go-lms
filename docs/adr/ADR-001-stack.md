# ADR-001 — Application stack and locked versions

- **Status:** Accepted (orchestrator, T-P0-02) · DB choice **reversible** until the host and legacy DB are known
- **Date:** 2026-11-16
- **Refs:** DMC §5, Conventions §2, R-001, R-002, R-004, R-026, O-004, ED-011, ED-021, AI spec §6.3 / §17 / §18
- **Evidence:** Packagist metadata fetched 2026-11-16 (`https://repo.packagist.org/p2/<vendor>/<package>.json`),
  npm registry (`https://registry.npmjs.org/<pkg>/latest`), Laravel source tag `v13.42.0`.
  Raw files and summaries: `agents/logs/T-P0-02/` (`packagist_summary.txt`, `filament_support.txt`,
  `npm_summary.txt`, `sse_evidence.txt`, `local_binaries.txt`, `raw/*.json`).

## 1. Context

Conventions §2 lists a recommended stack with "Laravel 13 (PHP 8.3+)" and asks T-P0-02 to lock versions
and check every package against the chosen Laravel/Filament versions and the target runtime.
T-P0-01 facts: the target host is **UNKNOWN** (B-05), the legacy DB engine is **UNKNOWN** (B-06), and the
dev machine has PHP 8.4.21 and Node 24.15.0 (`docs/ENVIRONMENT_INVENTORY.md` §2).

## 2. Decision — runtime

| Item | Locked | Reason / evidence |
|---|---|---|
| PHP | **8.4** (minimum `^8.4`) | Laravel 13 needs `^8.3`, but Pest 5, pest-plugin-laravel 5, pest-plugin-browser 5, spatie/laravel-activitylog 5, spatie/laravel-translatable 6.15, spatie/laravel-sitemap 8 and spatie/pdf-to-text 1.55 all need `^8.4` (`packagist_summary.txt`). **Change from Conventions §2 (8.3+).** Local PHP 8.4.21 matches. |
| Required PHP extensions | bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, tokenizer, xml, zip; `redis` (phpredis) and `pcntl` on Linux servers | Local machine has all but `redis`/`pcntl` (inventory §2). |
| Node.js | **≥ 22.12** (CI on 24 LTS line) | Vite 8.3.8 and laravel-vite-plugin 3.2.1 engines `^20.19.0 \|\| >=22.12.0`; local Node 24.15.0. |
| Server OS for staging/prod | Linux **recommended** (not locked) | Horizon needs `pcntl`, which is unavailable on Windows. Host itself UNKNOWN (B-05). |

## 3. Decision — Composer packages

Constraints are caret on the verified version; `composer.lock` pins exact builds (T-P0-03).

| Concern | Package | Constraint | Verified latest stable (date) | Compatibility evidence (from its `require`) |
|---|---|---|---|---|
| Framework | laravel/framework | `^13.42` | v13.42.0 (2026-11-10) | php ^8.3 |
| Skeleton | laravel/laravel | `v13.11.0` template | v13.11.0 (2026-09-30) | laravel/framework ^13.17 |
| Panels | filament/filament | `^5.11` | v5.11.4 (2026-11-15) | filament/support: illuminate ^11.28\|^12\|^13, livewire ^4.4.8 |
| Reactive UI | livewire/livewire | `^4.4.8` | v4.4.8 (2026-10-21) | illuminate ^10–^13; **v4 required by Filament 5** |
| Queues | laravel/horizon | `^5.51` | v5.51.0 (2026-11-02) | illuminate …\|^13 |
| App metrics | laravel/pulse | `^1.10` | v1.10.0 (2026-11-10) | illuminate …\|^13; livewire ^3.6.4\|^4.0 |
| API auth | laravel/sanctum | `^4.4` | v4.4.0 (2026-11-02) | illuminate ^11\|^12\|^13 |
| 2FA backend | laravel/fortify | `^1.42` | v1.42.0 (2026-10-21) | illuminate ^11\|^12\|^13 (Fortify vs Filament MFA decided in T-P1-11, ED-016) |
| Feature flags | laravel/pennant | `^1.27` | v1.27.0 (2026-10-29) | illuminate ^10–^13 |
| Permissions | spatie/laravel-permission | `^8.5` | 8.5.0 (2026-11-06) | php ^8.3; illuminate ^12\|^13 |
| Permissions UI (optional) | bezhansalleh/filament-shield | `^4.3` | 4.3.1 (2026-07-25) | filament ^4\|^5; illuminate …\|^13 — adopt-or-custom decided in T-P1-03 |
| Audit | spatie/laravel-activitylog | `^5.1` | 5.1.5 (2026-11-13) | php ^8.4; illuminate ^12\|^13 |
| Translatable fields | spatie/laravel-translatable | `^6.15` | 6.15.0 (2026-10-19) | php ^8.4; illuminate ^12\|^13 |
| Media | spatie/laravel-medialibrary | `^11.23` | 11.23.13 (2026-11-13) | illuminate ^12\|^13 |
| Money | brick/money | `^0.15` | 0.15.2 (2026-09-29) | brick/math ~0.15…^1.0; overlaps Laravel 13's brick/math range |
| HTML→PDF (Arabic) | spatie/laravel-pdf | `^2.14` | 2.14.0 (2026-10-05) | illuminate/contracts ^11–^13 (R-030) |
| Chromium driver | spatie/browsershot | `^5.5` | 5.5.0 (2026-11-06) | symfony/process ^6\|^7\|^8 — needs Node + Chromium on the server |
| Sitemap | spatie/laravel-sitemap | `^8.2` | 8.2.0 (2026-06-24) | php ^8.4; illuminate ^12\|^13 |
| Typed settings | spatie/laravel-settings | `^3.10` | 3.10.2 (2026-11-13) | illuminate/database ^12\|^13 |
| Phone numbers | propaganistas/laravel-phone | `^6.1` | 6.1.0 (2026-10-02) | illuminate ^11–^13 |
| Tests | pestphp/pest | `^5.3` | v5.3.6 (2026-11-14) | php ^8.4 |
| Tests (Laravel) | pestphp/pest-plugin-laravel | `^5.0` | v5.0.1 (2026-07-29) | laravel/framework ^13.23; pest ^5.0.1 |
| Arch tests | pestphp/pest-plugin-arch | `^5.0` | v5.0.0 (2026-07-21) | php ^8.4 |
| Browser tests | pestphp/pest-plugin-browser | `^5.2` | v5.2.0 (2026-10-29) | pest ^5.2.1; uses Playwright (npm `playwright` 1.65.1, node >=20) |
| Style | laravel/pint | `^1.32` | v1.32.3 (2026-11-11) | php ^8.3 |
| Static analysis | larastan/larastan | `^3.15` | v3.15.0 (2026-11-11) | illuminate …\|^13; phpstan ^2.3.5 |

Conventions §2 rows without a Packagist package (not version-verified here):
- **Redis server / client:** Redis 7+ on staging/prod with the `phpredis` extension (R-005); server version depends on the host (B-05). No Redis locally (B-04).
- **Rich-text sanitizer:** Filament 5 ships the rich editor; the server-side renderer/sanitizer is chosen in T-P3-15 (ED-003, ED-031).
- **Error reporter:** adapter only; provider OPEN (ED-021). No package chosen.
- **DB server versions** (MySQL 8.4 LTS, PostgreSQL 16+) come from Conventions §2, not from Packagist evidence.

## 4. Decision — front-end (npm)

| Package | Version | Evidence |
|---|---|---|
| tailwindcss | `^4.3` (4.3.5) | npm latest |
| @tailwindcss/vite | `^4.3` (4.3.5) | peer vite ^5.2–^8 |
| vite | `^8.3` (8.3.8) | node ^20.19 \|\| >=22.12 |
| laravel-vite-plugin | `^3.2` (3.2.1) | peer vite ^8 |
| alpinejs | `^3.17` (3.17.5) | npm latest (Livewire ships its own Alpine; only add if needed outside Livewire) |
| playwright | `^1.65` (1.65.1) | Pest browser plugin driver |

Student/public UI = Blade + Livewire 4 + Alpine + Tailwind v4 (R-026, unchanged).

## 5. Decision — database (reversible)

**Primary: MySQL 8.4 LTS. PostgreSQL 16+ stays supported as the alternative.**

- Task step 2 says to match the legacy DB when the host is unknown, but the legacy DB engine is also UNKNOWN (B-06).
  So this choice is provisional and **must be revisited when B-05/B-06 are answered**.
- Why MySQL for now: listed first in Conventions §2; it is the engine most generally offered by PHP hosting,
  which keeps the host choice open; AI does not need PostgreSQL (ED-011: full-context default, MySQL fallback
  for chunked mode; AI spec §5).
- To keep it reversible: no engine-specific SQL in Actions/queries without a portable fallback; JSON columns
  via Laravel's schema builder only; CI verifies framework migrations against MySQL 8.4 (T-P0-03). Switching to PostgreSQL
  before P1 data exists only costs CI config.
- Local dev gap: XAMPP has MariaDB 10.4.32 (end-of-life, not MySQL 8.4) and the 5432 Postgres belongs to Odoo.
  Native Windows uses SQLite for bootstrap, with file cache and synchronous queues. MySQL 8.4 and Redis/Horizon were verified in Linux CI (run 43882611107).
- Cache/queue: Redis + Horizon on staging/prod (R-005). Windows dev uses `database` queue + `file`/`database`
  cache until Redis is available (no phpredis/pcntl locally).

## 6. AI hook — PDF extraction, OCR, SSE (task `ai:` line)

| Need (AI spec) | Choice | Compatibility | Notes |
|---|---|---|---|
| PDF text extraction (§6) | spatie/pdf-to-text `^1.55` (wraps poppler `pdftotext`) | php ^8.4; symfony/process ^7\|^8 (overlaps Laravel 13) | Needs `poppler-utils` on servers. Locally `pdftotext` exists (Git for Windows mingw64). Arabic text-layer quality must be proven by the M7.1 eval set. |
| Pure-PHP fallback | smalot/pdfparser `^2.13` | php >=7.1 | Only if poppler is unavailable on the host; Arabic quality must be proven by the M7.1 eval set before relying on it. |
| OCR fallback (§6.3) | Behind `AiProviderInterface::ocrPage()` (AI spec §17). Page → image with poppler `pdftoppm`; local engine: Tesseract 5 CLI (`ara`+`eng`) invoked via `symfony/process` | symfony/process already in Laravel 13. **Tesseract version and Arabic traineddata unverified** (not installed locally) | thiagoalessio/tesseract_ocr was checked: last release 2.13.0 on 2023-10-05 → **rejected** (unmaintained). Tesseract not installed locally. Multimodal OCR via provider waits for O-014. |
| Streaming answers (SSE, §12 / API §18) | Laravel built-in `response()->eventStream()` + `Illuminate\Http\StreamedEvent` | Present in laravel/framework v13.42.0 (`sse_evidence.txt`) | No extra package. The endpoint is POST (AI spec §18), so the client uses `fetch` + `ReadableStream`, not `EventSource`. Set `endStreamWith` to the spec's final `result` event (default is `</stream>`). Hosting must not buffer `text/event-stream` (check in T-P7-01). Source: `src/Illuminate/Routing/ResponseFactory.php` @ v13.42.0. |
| Vectors (chunked mode only) | pgvector/pgvector if PostgreSQL is chosen; otherwise ED-011 PHP cosine fallback | php >=8.1 | Not installed while MySQL is primary. |
| AI provider SDK | **None chosen** | — | O-014 open; the adapter is in-house per AI spec §17. |

## 7. Rejected options

| Option | Reason |
|---|---|
| PHP 8.3 floor | Blocks Pest 5, activitylog 5, translatable 6.15, sitemap 8, pdf-to-text 1.55. Fallback if a host only has 8.3: Pest 4 / pest-plugin-laravel 4, activitylog 4.12, sitemap 7.4; **unproven** for spatie/laravel-translatable and spatie/pdf-to-text (no 8.3 release checked) — record a new ADR if that happens. |
| Laravel 12 | 13 is current and every chosen package supports it. |
| Filament 4 / Livewire 3 | Filament 5 is current and supported by Shield 4 and Pulse; Filament 5 requires Livewire 4. |
| Laravel Dusk | Pest browser plugin chosen (one test runner); Dusk 8.8 stays compatible if Pest browser blocks something. |
| thiagoalessio/tesseract_ocr | Unmaintained since 2023-10. |
| Choosing an AI SDK now | O-014 open. |

## 8. Consequences

- Conventions §2 and Decision Register R-001/R-002/R-004 are updated in the same change.
- Staging/prod host must offer PHP 8.4, MySQL 8.4 (or PostgreSQL 16+), Redis, Node ≥ 22.12, Chromium
  (spatie/laravel-pdf), poppler-utils and Tesseract 5 with Arabic data. Unverified until B-05.
- B-02 and B-03 were resolved. Composer 2.11.2 and the Git remote are recorded in the Environment Inventory.
- Versions are re-checked when T-P0-03 runs `composer require`; any drift is recorded here.
