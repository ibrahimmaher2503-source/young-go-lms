# Environment Inventory — Young Go LMS (T-P0-01)

**Captured:** 2026-11-16 · **Task:** T-P0-01 · **Refs:** Roadmap M0.1, DMC §4, O-001..O-003, Checklist §1
**Rule:** every item is `verified` (with the command/source) or `UNKNOWN`. Nothing here is guessed.
Raw outputs: `agents/logs/T-P0-01/` (`local_runtime.txt`, `local_services.txt`, `legacy_public_observation.txt`).

> **Critical finding (escalates O-001 to a blocker):** the public site at the owner-stated domain
> `youngschool.com` is operated by **Learning Care Group, Inc.** (US childcare chain; page title
> "Maryland Daycare, Preschool, and Infant Childcare | Young School"). It does not match Young Go
> (Egypt, AR/EN creative-course LMS). The owner-stated domain is **unconfirmed** until the owner
> confirms the correct legacy hostname. No further requests were made to that host. See §3.

## 1. Repository and source control

| Item | Value | Status | Source |
|---|---|---|---|
| Working directory | `C:\projects\young-go-lms-pack-v3.2\young-go-lms-pack-v3.2` (pack nested one level inside the session folder) | verified | `ls` |
| Git repository | Local repository connected to origin | verified | git rev-parse HEAD; git remote get-url origin |
| Local feature branch | feat/T-P0-03-bootstrap at c64dfd8 during the inventory update | verified | git branch --show-current; git rev-parse HEAD |
| Remote URL | https://github.com/ibrahimmaher2503-source/young-go-lms.git | verified | git remote get-url origin |
| Default branch | main (documentation baseline 3caa712) | verified | gh repo view; original origin/main baseline |
| Branch convention | `feat/<task-id>-<slug>` per ORCHESTRATOR §8 (doc rule, not observed in a repo) | documented | `agents/ORCHESTRATOR.md` |
| `agents/ANTI_HALLUCINATION_PROTOCOL.md` | **Missing** from the pack (named in the brief; not referenced by any file in the pack) | verified | `ls`, `grep -ril anti_hallucination .` → no match |

## 2. Local development runtime (this machine)

OS: Windows 11 Pro 10.0.26200 (verified, `Win32_OperatingSystem`). Shells: PowerShell 5.1, Git Bash.

| Component | Value | Status | Source |
|---|---|---|---|
| PHP (on PATH) | 8.4.21 CLI, ZTS, VC++ 2022 x64, installed via winget | verified | `php -v`, `Get-Command php` |
| PHP extensions | bcmath, calendar, ctype, curl, date, dom, exif, fileinfo, filter, gd, hash, iconv, intl, json, libxml, mbstring, mysqli, mysqlnd, openssl, pcre, PDO, pdo_mysql, pdo_pgsql, pdo_sqlite, pgsql, Phar, random, readline, Reflection, session, SimpleXML, sodium, SPL, sqlite3, standard, tokenizer, xml, xmlreader, xmlwriter, Zend OPcache, zip, zlib | verified | `php -m` |
| PHP extensions absent | `redis` (phpredis), `pcntl`/`posix` (not available on Windows; affects Horizon locally) | verified (absent from `php -m`) | `php -m` |
| Second PHP | XAMPP PHP 8.0.30 at `C:\xampp\php\php.exe` — not on PATH; PATH-conflict risk | verified | `C:\xampp\php\php.exe -v` |
| Composer | 2.11.2, project-local .tools/composer.phar | verified | php .tools/composer.phar --version |
| Node.js | v24.15.0 | verified | `node -v` |
| npm | 11.12.1 | verified | `npm -v` |
| git | 2.54.0.windows.1 | verified | `git --version` |
| GitHub CLI | Authenticated access to the private repository and CI logs | verified | gh repo view; gh run view 43882611107 |
| MySQL/MariaDB | MariaDB 10.4.32 (XAMPP) installed at `C:\xampp\mysql`; **not running**; nothing listening on 3306; client not on PATH | verified | `C:\xampp\mysql\bin\mysql.exe --version`, `Get-NetTCPConnection` |
| PostgreSQL | PostgreSQL 16.14 binaries bundled with Odoo 18 (`C:\Program Files\Odoo 18.0.20260614\PostgreSQL`). Service `PostgreSQL_For_Odoo` reports Stopped, yet a `postgres` process listens on 0.0.0.0:5432 and [::]:5432; owning executable not readable. **Belongs to another application — not designated for this project.** | verified (listener) / UNKNOWN (owner) | `psql.exe --version`, `Get-Service`, `Get-NetTCPConnection` |
| Redis | Not installed (no redis-server, redis-cli, Memurai; nothing on 6379) | verified | `Get-Command`, `Get-NetTCPConnection` |
| Docker | `C:\Program Files\Docker` exists but empty; no `docker` CLI — **not usable** | verified | `Test-Path`, `Get-Command docker` |
| WSL | Installed, **no distributions** | verified | `wsl -l -v` |
| Local web server | XAMPP Apache present, not running (nothing on 80/443) | verified | `Get-Process httpd`, `Get-NetTCPConnection` |
| Local mail catcher | UNKNOWN (XAMPP mailtodisk/MercuryMail folders exist; not configured or tested) | UNKNOWN | directory listing only |

## 3. Legacy site / domain (public read-only observation)

Owner-stated domain: `youngschool.com` (PROJECT_STATE, DMC §4, O-001). Observed 2026-11-16 ~12:58 GMT.

| Item | Observed value | Status | Source |
|---|---|---|---|
| A record | 23.101.174.2 | verified | `Resolve-DnsName -Type A` |
| `www` | CNAME → `youngschool.com` | verified | `Resolve-DnsName www.youngschool.com` |
| Nameservers | Azure DNS (`ns1-09.azure-dns.com` … `ns4-09.azure-dns.info`) | verified | `Resolve-DnsName -Type NS` |
| HTTP edge | `Microsoft-Azure-Application-Gateway/v2`; http→https→`www` 301 redirects; HSTS 1 year | verified | `curl -I` |
| TLS certificate | Subject `O=Learning Care Group, Inc., CN=www.learningcaregroup.com`; issuer DigiCert Global G2; valid 2026-05-17 → 2026-11-26; SAN includes `youngschool.com` and ~60 Learning Care Group brands | verified | `openssl s_client` + `x509` |
| Page identity | `<html lang="en">`, title "Maryland Daycare, Preschool, and Infant Childcare \| Young School" | verified | `curl -sSL` home page |
| MX / SPF / TXT | Present but belong to the third-party operator — **not attributable to Young Go** | verified (not reproduced) | `Resolve-DnsName` |
| **Is this Young Go's legacy LMS?** | **No match observed** — US childcare operator, English only, no Arabic/Egypt LMS indicators | verified mismatch | above rows |
| Correct Young Go legacy hostname | UNKNOWN | **UNKNOWN — blocker** | owner must confirm (O-001) |
| Legacy hosting provider | UNKNOWN | UNKNOWN | depends on correct hostname |

Impact: T-P0-04 (brand extraction "from the current site") must **not** use youngschool.com; use O-027 placeholders until the owner confirms the domain. T-P10-01 and the domain-retention direction in PROJECT_STATE are also affected.

## 4. Legacy LMS access items (O-003)

| Item | Status | Source / note |
|---|---|---|
| Legacy admin panel URL + read-only account | UNKNOWN | not supplied |
| Legacy source code | UNKNOWN | not supplied |
| Legacy database (engine, schema, read-only dump) | UNKNOWN | not supplied |
| Legacy file/media storage | UNKNOWN | not supplied |
| Video providers + accounts | UNKNOWN | DMC §4: multiple legacy source types seen in screenshots; accounts not verified |
| Live provider (Zoom/Meet/BigBlueButton) | UNKNOWN | DMC §4: legacy PRD names Zoom/Meet, screenshot shows BigBlueButton; not inspected |
| Instapay workflow | UNKNOWN | DMC §4: admin nav shows "Instapay Payments"; workflow/API not inspected |
| Legacy mail provider | UNKNOWN | — |
| Brand assets (logo files, colors, fonts) | UNKNOWN | O-027; source site unconfirmed |

## 5. New platform environments and providers

| Item | Status | Source / note |
|---|---|---|
| Staging host / server / SSH | UNKNOWN | not supplied (O-002) |
| Production host | UNKNOWN | not supplied |
| Staging/production database | UNKNOWN | not supplied (O-004) |
| Staging/production Redis / queue | UNKNOWN | not supplied |
| Transactional mail provider | UNKNOWN | not supplied |
| Object/file storage | UNKNOWN | not supplied (O-005) |
| Payment gateway | Paymob selected | verified (decision) — D-025, Decision Register |
| Paymob credentials (test + live), legal seller details | UNKNOWN | not supplied; live collection gated (O-006, O-023) |
| AI provider/model/region/terms | UNKNOWN | O-014 open |
| Video/streaming provider | UNKNOWN | O-005 open |
| CI provider | UNKNOWN | no repo/remote |

Note: a Hostinger connector is configured in this Claude session. It was **not queried**; whether staging is on Hostinger is UNKNOWN until the owner says so.

## 6. Consequences for next tasks

| Task | Can proceed locally? | Missing item |
|---|---|---|
| T-P0-02 (stack ADR) | Yes, doc-only; DB choice reversible (legacy DB engine UNKNOWN) | Target host runtime |
| T-P0-03 (skeleton, CI) | **No** until Composer is installed and a git repo exists | Composer; git repo + `develop` branch; remote for CI |
| T-P0-04 (brand tokens) | Placeholders only (O-027) | Confirmed legacy domain / brand assets |
| T-P0-05 (module map) | Yes, doc-only | — |
| T-P10-01 (legacy inventory) | No | Correct hostname + O-003 access |

## 7. Legacy safety (acceptance A3)

Only public DNS queries, HTTP HEAD/GET of the home page and one TLS handshake were made, all
read-only and unauthenticated. No login, form submission, crawl or change to any system. After the
operator mismatch was found, no further requests were sent to that host.

## Development integration evidence

[GitHub Actions run 43882611107](https://github.com/ibrahimmaher2503-source/young-go-lms/actions/runs/43882611107) verified Laravel setup, MySQL migrations, and Horizon executing a Redis job under Linux CI. Local Windows setup uses SQLite, file cache, and synchronous jobs. Production and staging service access remain UNKNOWN.
