# AGENTS.md --- Young Go LMS (rules for every AI agent)

Read this file first in every session. It applies to Codex, Claude Code and
any other agent. `CLAUDE.md` points here.

This repository is an instructions/specification pack, not a verified application.
Implementation begins only when requested; review of this pack does not authorize building it.

## 1. What we are building
A fully custom LMS for **Young Go** (Egypt active, Saudi disabled), Arabic +
English, recorded/live/hybrid creative courses, parent-linked child accounts,
unified entitlements, Paymob payments, instructor ledger, and a restricted
lesson-PDF AI assistant. **No packaged LMS core** (D-001).

## 2. Source of truth (in order)
1. Latest explicit owner instruction (record date/source in `docs/07_DECISION_REGISTER.md`), then APPROVED D-* decisions in that register.
2. `docs/01_MASTER_PRD_YOUNG_GO_LMS.md` (incl. §29 gap-closure)
3. `docs/06_AI_LESSON_ASSISTANT_SPEC.md` v3.0, `docs/05_ARCHITECTURE_DATA_API.md`
4. `docs/13_ENGINEERING_DECISIONS.md` (ED-*), `docs/11_DESIGN_SYSTEM.md`, `docs/15_UX_FLOWS_AND_PATTERNS.md`, `docs/12_CODE_ARCHITECTURE_CONVENTIONS.md`
5. Task spec in `agents/tasks/*.md`
6. `docs/02_DMC_DEVELOPMENT_MASTER_CONTEXT.md` execution rules

RECOMMENDED/OPEN entries and engineering defaults are not owner approvals and cannot override approved business policy.
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
   accounts or deployment evidence. Unknown = write `UNKNOWN` and block only the dependent acceptance items; continue independent authorized work.
3. Never enable live Paymob collection, Saudi checkout, AI for minors, or a
   domain switch without the gates in `docs/09_CODEX_START_HERE.md`.
4. Business mutations belong in `app/Domain/*/Actions`; focused Queries, Policies and value objects own reads and rules per ED-033. Reuse before you create;
   check `docs/REUSE_REGISTRY.md`.
5. All learner access checks go through `EntitlementService`.
6. Only design tokens and logical (RTL-safe) CSS utilities in views.
7. Every user-facing string in both `lang/ar` and `lang/en`.
8. Behavior changes require meaningful tests; documentation changes require structural and semantic checks; never weaken `tests/Feature/Invariants/*` or
   `tests/Arch/*` without a Decision Register entry.
9. Small, focused commits: `type(scope): summary [T-Px-yy]`.
10. Concise English in code, commits and agent briefs; communicate with the owner in their language.
11. **PRD lock:** build only what cited requirements and the task require; unknown detail → documented default (PRD §29, ED-*) or a Spec question + `blocked`. Never invent scope.
12. **Tests first:** executable behavior acceptance tests are written before implementation. Documentation/inventory acceptance uses recorded review evidence; tags alone prove no behavior.
13. **AI in every step:** follow the task's `ai:` line exactly — build the hook, or add nothing AI-related.
14. **Independent verification:** use the applicable reviewers in ORCHESTRATOR §7. Self-review never counts as independent review; unavailable required review stays pending.
15. **Single system:** `curriculum_items` for ordering, `EntitlementService::canAccessItem()` for access, design tokens/components/patterns for UI.
16. LearnHouse is a design reference only: no copying its code/brand into this custom build (D-001); licensing is documented separately in doc 14.

## 5. Planned application commands (not available in this pack)
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
`composer qa` is defined in T-P0-03. Gate applicability and bootstrap sequencing
are defined in `agents/VERIFICATION_GATES.md`; do not run absent app commands for a docs-only task.

## 6. Operational boundaries
Use normal host permissions and respect existing authorization. No permission bypass flags.
Commits, pushes, merges and external writes require task authorization; a methodology
review alone authorizes none of these. Preserve unrelated changes. Never reset a database
without resolving and allowlisting its isolated disposable connection (see G6).
See `docs/16_REVIEW_FINDINGS_AND_DECISIONS.md` for unresolved findings and evidence limits.

## D-026: mandatory reference fidelity

Before any UI work read docs/design-system/VISUAL_ACCEPTANCE.md, TABLE_WORKSPACE.md and the approved/rejected reference images. Every current/future page must match its approved reference in detail. Use shared native components, tokens, WorkspaceListRecords and registered visual checks; compare actual AR/EN mobile/desktop light/dark screenshots. Technical PASS does not establish visual acceptance. Never replace the owner reference with the output just to pass. Record all visible discrepancies and keep closure open until resolved; do not fabricate data or unsupported actions.
