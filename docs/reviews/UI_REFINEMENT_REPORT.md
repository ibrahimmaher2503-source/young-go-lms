# UI refinement report — 2026-11-17

This is a checkpoint for the first implementation wave, not acceptance of the complete all-pages refinement. The owner's remaining primitive, shell, learning-layout, page, staff-theme and error-page waves are still open. G7 stays open for visible reference differences and missing verification.

## Scope and current change

Requirements: owner UI-only request, D-026, Design System §§2/4/6, ED-020/ED-023, C01–C24. AI impact: none. No new dependency, feature, text, route, controller, model, policy, Action, query, migration or configuration.

| Shared piece | Before | First-wave change | Consumers |
|---|---|---|---|
| `resources/css/tokens.css` and `app.css` | App body rendered at16px despite the existing18px body token; generic inherited line rhythm | Consume existing `--yg-font-body`; add one shared body line-height token,1.6 for English and1.8 for Arabic | Public, identity, learner, parent, player, errors and galleries |
| `learning/live-session-card.blade.php` | Status background colors used as text; measured light contrast1.22:1 success /1.23:1 danger | Use existing `text-on-success` / `text-on-danger` in joinable, cancelled and join-error text | Every live-card consumer; all conditions, form actions and `data-*` preserved |
| Native Filament typography | Existing18px body and explicit1.6 line-height with approved sidebar/topbar/table measurements | Preserved; the new app body rule is in app.css, not the staff theme | All three staff panels |

Before editing product files, [inventory](UI_PAGE_INVENTORY.md) and [execution plan](UI_REFINEMENT_PLAN.md) were written, original/rejected/derived references were inspected, and actual baseline screenshots were captured. [Original approved reference](../design-system/reference-pack-2026-11-17/00-owner-approved-users.png) and [shared-component reference](../design-system/reference-pack-2026-11-17/18-shared-component-library-v2.png) remain unchanged. No implementation screenshot replaces a reference.

## Evidence and reproduction

- Original browser suite before edits:282 passed,3 failed,2525 assertions.401 fresh screenshots copied into `UI_BASELINE/existing-tests/` with a capture-time/hash manifest. [Log](UI_BASELINE/logs/existing-browser.log).
- Supplemental baseline:456 screenshots/JSON records across57 page/state scenarios × AR/EN × light/dark ×390/1440. Existing tests did not cover all these combinations. The full first supplemental run had fixture/time-budget issues;24 targeted reruns passed after fixing the new evidence harness, without editing original tests. The resulting per-screen records, not the initial aggregate count, identify actual remaining baseline defects. [First run](UI_BASELINE/logs/supplemental-first-complete-run.log), [rerun](UI_BASELINE/logs/supplemental-fixture-and-timeout-rerun.log).
- Final baseline findings:12 English live-state captures fail axe color contrast (joinable/cancelled/join-error ×2 themes ×2 widths). [Machine-readable findings](UI_BASELINE/open-findings.json). Arabic counterparts are also visually inspected; an axe pass alone is not proof of correct appearance.
- Tests first:8 typography checks failed specifically because actual16px did not equal required18px. After the CSS change the same8 checks pass,48 assertions. [Red log](UI_BASELINE/logs/typography-red.log). Green log and final after-matrix results are added below when complete.
- Non-browser baseline:825 passed,2 failed,6 existing TODOs. Both failures concerned disappearing fake-disk files during another chat's simultaneous tests. An isolated rerun of the two affected files passed all19 tests/168 assertions. [Initial full run](UI_BASELINE/logs/non-browser-2g.log), [isolated rerun](UI_BASELINE/logs/non-browser-storage-isolated-rerun.log). Do not treat the initial concurrent-storage failures as established application defects.

Run the evidence matrix with `pwsh -NoProfile -File docs/reviews/Invoke-UIMatrix.ps1 -Phase BASELINE` (or AFTER). It generates48 temporary test files from the single source [UIRefinementMatrixTest.php](UIRefinementMatrixTest.php), runs four existing Pest workers, and removes only its generated files. Optional `-Filter 'UIRefinement[135]'` reruns identity/staff/gallery groups. It copies the installed browser-plugin code unchanged into a unique temporary runtime, remaps only its Composer namespace through [UIBrowserBootstrap.php](UIBrowserBootstrap.php), and uses Laravel's native `LARAVEL_STORAGE_PATH` override to isolate test disks/views. No installed dependency or product configuration is edited by this task.

For typography, temporarily copy [UITypographyTest.php](UITypographyTest.php) to `tests/Browser/UITypographyTest.php`, run Pest on that file, then remove that owned copy. Pest browser discovery requires the configured tests folder; keeping the evidence source under docs respects the owner's final-path allowlist. No existing browser assertion is weakened or edited.

Windows verification uses PHP8.4.21 with `-d opcache.enable_cli=0 -d extension=sockets`; full suites use `-d memory_limit=2G`, PHPStan `--memory-limit=1G`. The original128MiB test run exhausted memory in Arch reflection. CLI OPcache produced inconsistent internal-class type errors; process-local flags avoid changing php.ini. Concurrent chats were observed running tests and creating commit76b71db after the initial16220bc snapshot; that separate commit adds a demo seeder and progress documentation. This task does not stage or modify it.

## Checks after the first wave

| Check | Evidence/result |
|---|---|
| Build and semantic token pairs | PASS; Vite build and34 light/dark AA pairs |
| Typography matrix | PASS;8 tests/48 assertions |
| Supplemental after matrix | Pending completion |
| All original browser tests, unchanged assertions | Pending post-change run;3 baseline redirect-expectation failures remain separate |
| Full Unit/Feature/Arch | Pending isolated post-change run |
| Pint | Owned PHP evidence files checked; global dirty check must preserve unrelated files |
| PHPStan | Baseline PASS with1GiB; post-change rerun pending |
| Translation and RTL | Baseline PASS; post-change rerun pending |
| Doctor | Existing scheduler and migration checks FAIL locally; no migration/scheduler change authorized |
| Requirement coverage / full G7 | Open; this checkpoint does not close the all-pages task |
| Independent PRD/QA/UX reviews | Pending first-wave diff and after screenshots |

## Reference differences that remain open

| Scope | Observed issue / root cause | Impact / priority | Planned wave |
|---|---|---|---|
| Shared fields | Decorative border token used for inputs and pale danger background token used for invalid outlines | Hard to distinguish controls/errors; P1 | Primitives |
| Shared cards/controls |24px cards and16px controls versus existing16px card /10px control contracts; shared cards lack the approved subtle shadow | Inconsistent with reference18; P2 | Primitives |
| Shared buttons | Hover/pressed visual feedback is missing on several variants | Action feedback; P2 | Primitives |
| Public/identity shell | Branding/navigation rhythm and container geometry differ from the relevant reference vocabulary | Hierarchy/consistency; P2 | Shell/public/identity |
| Learner dashboard | Resume thumbnail order differs from desktop04/mobile16 | Reference layout; P2 | Learner layout |
| Rich content and curriculum views | Embedded style blocks and hard-coded dimensions exist in current Blade | Reuse/token consistency; P2 | Learning/staff |
| Staff learners/cohorts | Dedicated gray/white utilities bypass semantic surfaces and shared card tokens | Theme consistency; P1 | Staff |
| All families |360/768/1024/1920 inspection, all modal/empty/replay states, keyboard-state screenshots and full root reference scoring are not established by this first wave | Required before all-pages closure | Remaining waves |

The inventory maps every currently built route to a reference/archetype and coverage. Live cards, course learners, cohorts, preview banners, devices and several identity/error states have no dedicated raster reference; use the mapped shared components and explicitly retain that limitation. Reference-only public course detail, checkout, quiz, ledger and other future journeys are not built by this task.

## Functional findings and backend requests

1. Original FamilyFlowsTest and TwoFactorScreensTest expect `/profile` after sign-in, while current PageController::home redirects learners to `/my` and parents to `/parent`. Preserve both current behavior and original assertions per owner scope; resolve the test/behavior disagreement in a separate authorized task. No backend correction is included here.
2. Preview URL generation depends on current Filament panel context. The evidence harness initially visited preview after finance pages while switching actors and got an undefined finance-course route. The corrected harness follows the real instructor curriculum entry point before preview and asserts the banner/path. A fresh-request/direct-link production defect has not been established; do not claim the fixture artifact is a confirmed production bug.
3. No additional backend data or field is needed for the first-wave visual corrections. No live provider, real payment, legacy site, DNS, production deployment or real learner record was used.

## Before/after page matrix and root visual comparison

After captures, per-page links, comparison results, scope proof and reviewer verdicts are added here. Screenshot existence alone does not constitute visual acceptance.
