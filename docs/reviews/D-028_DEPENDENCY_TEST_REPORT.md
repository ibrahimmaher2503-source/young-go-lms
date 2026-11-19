# Functional dependency checks, 19 November 2026

## Tested workspace

Local PHP 8.4.21, Pest browser, Laravel test database. The canonical checkout was on `feat/lms-build` at `4ecb051` with concurrent local changes. Results apply to that working copy. Relevant Git blob IDs include `b7acbb9` for PageController, `9d1fca3` for the parent family view, `8f7d62a` for the new learner fallback, `7140c18` for FamilyPagesTest and `a8a7adf` for the expanded FamilyFlowsTest. The combined run started before the additional fallback tests were added.

## T-P1-10 — FUNCTIONAL PASS

The previously reported Arabic dashboard failure did not reproduce in the combined run or in the independent Sol retry. The child signs in by username after the parent logs out, reaches `/my` and sees the child's display name in the expected locale. Both languages passed. There is insufficient evidence to attribute the earlier failure to timing or an application defect.

| Command (prefix `php -d memory_limit=1G vendor/bin/pest`) | Result |
|---|---|
| `tests/Browser/AuditViewerBrowserTest.php tests/Browser/FamilyFlowsTest.php tests/Browser/ErrorPagesBrowserTest.php` | Exit 0, 138 passed, 1,004 assertions. Registration, verification, family journeys, screenshot matrix, axe and branded errors. |
| `tests/Browser/FamilyFlowsTest.php --filter="lets a parent create a child who then signs in"` | Independent Sol QA and orchestrator each passed 2 cases, 22 assertions. |
| `tests/Feature/Pages/FamilyPagesTest.php` | Exit 0, 37 passed, 127 assertions. |
| `tests/Browser/FamilyFlowsTest.php --filter="family-unavailable\|forbids the family page to a parent"` | Exit 0, 9 passed, 97 assertions. Eight learner fallback captures, locale, axe, header and mobile nav, absent guardian controls; a parent lacking the permission receives 403. |

A1–A3 passed functional and technical review. Sol independently reviewed the new test coverage and the unchanged child sign-in assertions. The eight new captures use the `family-unavailable-` prefix. [Family captures](../../tests/Browser/screenshots/T-P1-10)

## T-P2-02 — FUNCTIONAL PASS

`php -d memory_limit=1G vendor/bin/pest tests/Feature/Pages/CmsPagesTest.php tests/Browser/CmsPagesBrowserTest.php` exited 0 with 17 passes and 199 assertions. A1 covers public draft denial and authorized, signed editor previews including expiry. A2 covers homepage order and visibility, including edits through Filament. Independent Sol PRD review passed; the earlier scoped QA and the current browser run cover public and editor behavior. Both prerequisites qualify for dependency use on this snapshot. [CMS captures](../../tests/Browser/screenshots/T-P2-02)

For both tasks, requirement coverage, translation key parity and RTL checks passed. No failures, skips or TODOs in the listed runs. Full combined logs are available locally under `agents/logs/yg-family-closure-20261119.log` and `agents/logs/yg-cms-closure-20261119.log`. Both tasks qualify for dependent implementation under D-028. Visual fidelity scoring, milestone approval and branch-wide release QA remain open.

## T-P2-03 — FUNCTIONAL PASS

After T-P2-02 qualified for dependency use, the orchestrator built the assets and ran the public home feature and browser suites (12 cases, 162 assertions) and a new bilingual mobile Categories click test (2 cases, 4 assertions). The click checks the destination element and the URL fragment. Independent Sol QA reran the combined command on `beebd04` plus the public home test diff (`8e36643`). Exit 0, 14 passed, 166 assertions, no skips or TODOs.

`php -d memory_limit=1G vendor/bin/pest tests/Feature/Pages/PublicHomeTest.php tests/Browser/PublicHomeBrowserTest.php`

Verified published-only content, saved footer links, locale and theme, axe, 390px overflow, responsive and lazy image behavior and font-display swap. Both hero variants returned HTTP 200 on a local static server and decoded at 640×213 and 1200×400. The production build emitted one application JS entry at 8.40 kB gzip. G10 and scoped Pint passed. Sol's independent functional PRD review passed. The brand/reference comparison remains deferred. LCP, CLS and INP on mid-range mobile 4G remain milestone measurements. [Home captures](../../tests/Browser/screenshots/T-P2-03)
