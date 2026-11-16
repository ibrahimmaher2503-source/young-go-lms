# VERIFICATION GATES

Run in order. A task is `done` only when every applicable gate passes.
The commands below are planned application checks, not evidence that this pack
contains an app. Commands are finalized by their owning bootstrap tasks; later
weakening requires a Decision Register entry.

## Applicability and bootstrap
- Documentation/inventory-only changes: check references, IDs, task dependencies,
  internal consistency, scope and every acceptance item; record the actual results.
  Application gates are N/A unless the changed instructions/config affect an existing app.
- T-P0-03 establishes G1–G6; run them after creating their tools. Before then they
  are N/A (not bootstrapped), never PASS. Missing required tools after their owning
  task are NOT RUN and block acceptance.
- G5 initially verifies the implemented skeleton invariants and inventories future
  invariants as pending coverage. Pending future invariants are not passing tests.
  Each owning feature task must implement its invariants; milestone claims cannot
  include pending behavior. Do not create skipped tests to simulate coverage.
- G10 uses a manual acceptance-to-evidence map until T-P0-07 implements the command;
  G11 is N/A until T-P0-07. That task must run and test both tools before completion.
- After bootstrap, application changes run the applicable checks below. Select
  additional checks by changed paths and dependency impact, not merely task labels.
- Record PASS, FAIL, NOT RUN or N/A with rationale. An external gate may block one
  slice while local/fake-provider work continues, but unverified required criteria
  keep the whole task incomplete. No automatic waiver of milestone/launch gates.

| Gate | When | Command / check | Pass condition |
|---|---|---|---|
| G1 Format | application changes after bootstrap | `vendor/bin/pint --test` | no changes needed |
| G2 Static analysis | application changes after bootstrap | `vendor/bin/phpstan analyse` (Larastan, level per `phpstan.neon`) | 0 errors (no new baseline entries) |
| G3 Tests | application changes after bootstrap | `vendor/bin/pest --exclude-group=browser --parallel --coverage --min=<per phpunit.xml>` | all green; coverage meets configured thresholds (≥ 90% for implemented Entitlements, Commerce, InstructorFinance, AI code; absent modules are not reported as covered) |
| G4 Architecture | application changes after bootstrap | `vendor/bin/pest tests/Arch` | all green |
| G5 Invariants | application changes after bootstrap | `vendor/bin/pest tests/Feature/Invariants` | implemented applicable invariants green; none skipped; future coverage explicitly pending |
| G6 Hygiene | application changes after bootstrap | `php artisan app:check-translations` · `php artisan app:check-rtl` · `composer audit` · migration + seed on an allowlisted disposable database (see safety rule below) · `npm run build` | no missing keys, no forbidden classes, no high/critical advisories, migrations + seed + build succeed |
| G7 Visual | application UI changes | `vendor/bin/pest --group=browser --filter=<task>` with screenshots → `tests/Browser/screenshots/<task-id>/`; axe check | screenshots for AR/EN × 390×844 & 1440×900 (+ light/dark SKIT); 0 serious/critical axe issues; no horizontal scroll at 390px |
| G8 AI eval | implemented AI behavior/prompts/extraction/guards change | `php artisan ai:eval --suite=all --provider=fake` (CI) and `--provider=<selected>` (pre-release, manual) | thresholds in AI spec §13.3 |
| G9 Payments | implemented Commerce/Subscriptions/Ledger behavior changes | `vendor/bin/pest --group=payments` incl. webhook replay, tamper, duplicate, amount mismatch | all green |
| G10 Requirement coverage | every task: manual for docs/before T-P0-07, command thereafter | `php artisan app:req-coverage --task=<task-id>` (task scope) and `--phase=<Px>` at milestone gates | each criterion has a current successful test or inspected manual result; tags locate evidence but cannot prove it |
| G11 Doctor | application changes after T-P0-07 (after migration/seed when applicable) | `php artisan app:doctor` | exit 0 |

## Evidence and reset safety
For every acceptance item retain test name/manual steps, command, exit code or
observed result, environment, timestamp, artifact path and final revision/diff snapshot.
Coverage tooling must validate linked execution results, not just count tags.
Missing, failed or stale results block acceptance. Manual evidence is appropriate
for non-executable criteria and must identify who inspected it.

Before any `migrate:fresh`, `app:demo-reset` or equivalent destructive operation,
resolve the effective connection (including cached config), host and database name;
match all three to an explicit disposable local/test allowlist and verify isolation
from legacy, staging/shared and production data. `APP_ENV=local` alone is insufficient.
Do not print credentials. Unknown/shared target: do not reset; provision an isolated
database or report NOT RUN. Preserve data and use normal host permission boundaries.

## Verification wave
After gates pass, ORCHESTRATOR §7 runs `prd-auditor`, `qa-verifier`, `ux-reviewer` (UI) and `reviewer` (high risk). Only applicable independent reviews are required; unavailable ones remain NOT RUN.
Self-review cannot satisfy the independent review requirement.

## Milestone gate (task `T-Px-MG`)
1. All tasks of the milestone `done`.
2. Applicable G1–G11 on the milestone branch, including G11 environment health.
3. Demo script executed with seeded data (written in the MG task) and
   results recorded (screens + key DB rows).
4. G10 at phase scope; performance budgets (ED-030) for public/student pages touched in the phase.
5. Relevant checklist items in `docs/04_DELIVERY_ACCEPTANCE_CHECKLIST.md`
   ticked with evidence.
6. Continuity record (DMC §13) written to `PROJECT_STATE.md` and
   `HANDOFF.md`.

## Final phase (P11) adds
Full regression, security review, load test sized to expected traffic,
backup + restore drill, migration reconciliation, cutover rehearsal.

## CI
GitHub Actions (or the repo's CI) must classify changed paths and their dependency
impact. Docs-only changes run document consistency/traceability checks; application
changes run G1–G6 and G10/G11 after bootstrap. UI/views/tokens/translations and
shared entry-point changes add G7; AI/prompts/extraction/guards add G8 with the
fake provider; Commerce/Subscriptions/Ledger and their dependencies add G9.
Shared authorization, infrastructure, dependency, test or CI changes run every
potentially affected suite; uncertain classification defaults to the broader set.
Labels may add checks, never suppress path-triggered checks. Publish selection and
N/A reasons in CI; missing required jobs or red CI block merge. Live provider and
release checks remain separately gated and cannot be replaced by fake results.
