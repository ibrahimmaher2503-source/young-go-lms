# VERIFICATION GATES

Run in order. A task is `done` only when every applicable gate passes.
Commands are finalized in T-P0-03 (`composer.json` scripts) and must not be
loosened afterwards without a Decision Register entry.

| Gate | When | Command / check | Pass condition |
|---|---|---|---|
| G1 Format | always | `vendor/bin/pint --test` | no changes needed |
| G2 Static analysis | always | `vendor/bin/phpstan analyse` (Larastan, level per `phpstan.neon`) | 0 errors (no new baseline entries) |
| G3 Tests | always | `vendor/bin/pest --exclude-group=browser --parallel --coverage --min=<per phpunit.xml>` | all green; coverage ≥ thresholds (≥ 90% for Entitlements, Commerce, InstructorFinance, AI) |
| G4 Architecture | always | `vendor/bin/pest tests/Arch` | all green |
| G5 Invariants | always | `vendor/bin/pest tests/Feature/Invariants` | all green, none skipped |
| G6 Hygiene | always | `php artisan app:check-translations` · `php artisan app:check-rtl` · `composer audit` · `php artisan migrate:fresh --seed` (local) · `npm run build` | no missing keys, no forbidden classes, no high/critical advisories, migrations + seed + build succeed |
| G7 Visual | task touches UI | `vendor/bin/pest --group=browser --filter=<task>` with screenshots → `tests/Browser/screenshots/<task-id>/`; axe check | screenshots for AR/EN × 390×844 & 1440×900 (+ light/dark SKIT); 0 serious/critical axe issues; no horizontal scroll at 390px |
| G8 AI eval | task touches `Domain/AI`, prompts, extraction, guards | `php artisan ai:eval --suite=all --provider=fake` (CI) and `--provider=<selected>` (pre-release, manual) | thresholds in AI spec §13.3 |
| G9 Payments | task touches Commerce/Subscriptions/Ledger | `vendor/bin/pest --group=payments` incl. webhook replay, tamper, duplicate, amount mismatch | all green |
| G10 Requirement coverage | always | `php artisan app:req-coverage --task=<task-id>` (task scope) and `--phase=<Px>` at milestone gates | every cited requirement and acceptance item has a tagged test or recorded evidence; no untagged acceptance tests |
| G11 Doctor | always (after migrate/seed) | `php artisan app:doctor` | exit 0 |

## Verification wave
After gates pass, ORCHESTRATOR §7 runs `prd-auditor`, `qa-verifier`, `ux-reviewer` (UI) and `reviewer` (high risk). Their PASS verdicts are part of the definition of done.

## Milestone gate (task `T-Px-MG`)
1. All tasks of the milestone `done`.
2. G1–G6 on the milestone branch; G7–G9 for affected areas.
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
GitHub Actions (or the repo's CI) runs G1–G6 on every push and PR, G7 on PRs
labeled `ui`, G8 with fake provider on PRs labeled `ai`, G9 on PRs labeled
`payments`. A red CI blocks merge.

## G7 reference matching

Compare each changed screen against the PNG listed in its task at comparable viewport, language and theme. Save the actual screenshot and record the matched reference, checked regions and remaining differences alongside the task evidence. Check page layout, type, spacing, colors, components and visible states. Resolve unexplained differences before marking the task done. Keep the existing AR/EN, responsive, dark mode and accessibility checks. See the [implementation and matching guide](../docs/design-system/reference-pack-2026-11-17/IMPLEMENTATION.md).
