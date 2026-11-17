# AGENTS.md --- Young Go LMS (rules for every AI agent)

Read this file first in every session. It applies to Codex, Claude Code and
any other agent. `CLAUDE.md` points here.

## 1. What we are building
A fully custom LMS for **Young Go** (Egypt active, Saudi disabled), Arabic +
English, recorded/live/hybrid creative courses, parent-linked child accounts,
unified entitlements, Paymob payments, instructor ledger, and a restricted
lesson-PDF AI assistant. **No packaged LMS core** (D-001).

## 2. Source of truth (in order)
1. Latest owner decision in `docs/07_DECISION_REGISTER.md`
2. `docs/01_MASTER_PRD_YOUNG_GO_LMS.md` (incl. §29 gap-closure)
3. `docs/06_AI_LESSON_ASSISTANT_SPEC.md` v3.0, `docs/05_ARCHITECTURE_DATA_API.md`
4. `docs/13_ENGINEERING_DECISIONS.md` (ED-*), `docs/11_DESIGN_SYSTEM.md`, `docs/15_UX_FLOWS_AND_PATTERNS.md`, `docs/12_CODE_ARCHITECTURE_CONVENTIONS.md`
5. Task spec in `agents/tasks/*.md`
6. `docs/02_DMC_DEVELOPMENT_MASTER_CONTEXT.md` execution rules

If two sources conflict, follow the higher one and record the conflict in
`agents/PROGRESS.md` → "Notes".

## 3. How work is executed
- The **orchestrator** follows `agents/ORCHESTRATOR.md` and the loop in
  `agents/LOOP_PROMPT.md`.
- **Subagents** receive a brief built from `agents/SUBAGENT_BRIEF_TEMPLATE.md`
  and only touch the files listed in it.
- Every change passes `agents/VERIFICATION_GATES.md` and
  `agents/REVIEW_PROTOCOL.md` before a task is marked `done`.
- Status lives only in `agents/PROGRESS.md`.

## 4. Non-negotiable rules
1. Never modify the legacy production site, DNS or data.
2. Never invent repository URLs, hosts, credentials, gateway keys, provider
   accounts or deployment evidence. Unknown = write `UNKNOWN` and mark the
   task `blocked` with the reason.
3. Never enable live Paymob collection, Saudi checkout, AI for minors, or a
   domain switch without the gates in `docs/09_CODEX_START_HERE.md`.
4. Business logic only in `app/Domain/*/Actions`. Reuse before you create;
   check `docs/REUSE_REGISTRY.md`.
5. All learner access checks go through `EntitlementService`.
6. Only design tokens and logical (RTL-safe) CSS utilities in views.
7. Every user-facing string in both `lang/ar` and `lang/en`.
8. Tests with every change; never weaken `tests/Feature/Invariants/*` or
   `tests/Arch/*` without a Decision Register entry.
9. Small, focused commits: `type(scope): summary [T-Px-yy]`.
10. Concise English in code, commits and agent messages.
11. **PRD lock:** build only what cited requirements and the task require; unknown detail → documented default (PRD §29, ED-*) or a Spec question + `blocked`. Never invent scope.
12. **Tests first:** acceptance tests (tagged with task + requirement IDs) are written before implementation.
13. **AI in every step:** follow the task's `ai:` line exactly — build the hook, or add nothing AI-related.
14. **Independent verification:** no task is done without `prd-auditor`, `qa-verifier` (user-visible), `ux-reviewer` (UI) and `reviewer` (high risk) PASS.
15. **Single system:** `curriculum_items` for ordering, `EntitlementService::canAccessItem()` for access, design tokens/components/patterns for UI.
16. LearnHouse is a design reference only: never copy its code (AGPL).

## Approved Filament table workspace

For D-026 table-page composition, follow [docs/design-system/TABLE_WORKSPACE.md](docs/design-system/TABLE_WORKSPACE.md). Keep the resource-native table and permission-scoped summary/sidebar data; the mockup does not approve product data or official branding.

## 5. Commands

Local verification uses PHP 8.4.21, Composer 2.11.2, Node 24.15.0 and npm 11.12.1. Exact dependencies are in the lockfiles. See README.md for Windows setup.
```bash
composer install && npm ci
php artisan migrate --seed            # local/dev only
composer qa                           # pint --test + larastan + pest (all suites except browser)
php artisan test --testsuite=Browser  # browser + screenshots
npm run build
php artisan app:check-translations    # missing AR/EN keys
php artisan app:check-rtl             # forbidden physical direction classes
php artisan app:req-coverage --task=T-Px-yy   # requirement coverage (G10)
php artisan app:doctor                # environment health (G11)
```
`composer qa` is defined in T-P0-03.


## D-026 visual acceptance, owner correction 2026-11-17

Read `docs/design-system/VISUAL_ACCEPTANCE.md`, the approved reference and its rejected implementation before any UI change. The owner requires visual fidelity on every page, not just a similar theme. Reuse the complete shared system (native forms/modals, login/dashboard, WorkspaceListRecords for every table); no generic default screen, independent palette or copied table markup. Capture actual AR/EN desktop/mobile light/dark screenshots and compare with the reference at equal viewport. Technical PASS alone is insufficient; keep visible mismatches open and fix them. New pages join the automated visual matrix and require actual root visual review. Never approve the implementation by replacing its reference with its own screenshot. Exact shared measurements and reference identity live in visual-contract.json. Preserve real permissions and data; no fake counts or nonfunctional decorative actions.


## PRD visual reference pack — 2026-11-17

For every future page read docs/design-system/reference-pack-2026-11-17/README.md, manifest.json and COMMON_COMPONENTS.md. The pack contains19new generated references plus the owner original, with PRD and reuse mappings. Choose the matching screen/archetype and reuse existing components; preserve dedicated public/learner navigation. These images do not approve data, rules, unknown policies or implemented status. PRD/decisions remain authoritative. Keep screenshot comparison mandatory and never use drafts as final references.

## Task-linked UI implementation

Start with the task's `visual` links and follow the [fast implementation guide](docs/design-system/reference-pack-2026-11-17/IMPLEMENTATION.md). Match the referenced layout and component details. G7 requires actual screenshots and a documented comparison before closure.
