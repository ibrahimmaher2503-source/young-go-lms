# P1 milestone gate evidence (T-P1-MG)

Scope: Phase 1 — identity, roles, family accounts and bilingual shells. Executed locally on 2026-11-17 against the working checkout (Windows, PHP 8.4, SQLite, file cache, synchronous queue). Hosted MySQL/Redis/Horizon results are separate CI evidence and were **not** re-run here. Nothing was pushed.

## Verdict

**The gate is not closable yet.** All verification gates below pass locally, the demo script runs end to end and the RTL review found one defect (fixed). The gate cannot be marked `done` because the milestone rules require every P1 task to be `done`, and the items in "Open items" are still open. Status: `review`.

## Gates (agents/logs/p1-mg-*.txt)

| Gate | Result | Evidence |
|---|---|---|
| G1 Format | PASS (`pint --test`) | p1-mg-g1-pint.txt |
| G2 Static analysis | PASS, 0 errors | p1-mg-g2-larastan.txt |
| G3 Tests (non-browser) | PASS, 302 tests / 1625 assertions, 1 planned todo | p1-mg-g3-tests.txt (earlier run 294/1516 before the demo and account-type tests were added) |
| G3 coverage thresholds | NOT RUN — no PCOV/Xdebug driver in this PHP build; the module thresholds apply to Entitlements, Commerce, InstructorFinance and AI, which do not exist yet | — |
| G4 Architecture | PASS, 6 tests (see "Repairs") | p1-mg-g4-arch.txt |
| G5 Invariants | PASS, 3 passed + 1 todo — the todo (generic I02 object-scope cases) is planned scaffolding for later domains, so "none skipped" is **not** met | p1-mg-g5-invariants.txt |
| G6 Hygiene | PASS: translations, RTL utilities, `composer audit` (no advisories), `migrate:fresh --seed` on a throwaway SQLite file, `npm run build` | p1-mg-g6-hygiene.txt |
| G7 Visual | PASS: 144 browser tests / 1015 assertions, axe clean, no horizontal scroll | p1-mg-g7-browser.txt; tests/Browser/screenshots/T-P1-08, -09, -10, -11 |
| G10 Requirement coverage | PASS for T-P1-01..11; phase scope lists only T-P1-MG:A1/A2, closed by this record | p1-mg-g10-phase.txt |
| G11 Doctor | PASS on the throwaway database after `schedule:run` (scheduler heartbeat); a fresh database fails the scheduler check until the first run, as in P0 | p1-mg-g11-doctor.txt |

Totals after the last change: 302 non-browser + 144 browser tests pass.

## Demo script (executed)

`tests/Feature/Milestone/P1DemoTest.php`, 87 assertions, runs over HTTP against the seeded roles:

1. A parent registers (account type "parent"), cannot create a child before verifying the email, then verifies through the signed link.
2. The parent creates two children (usernames only, no email).
3. A child signs in with the username and opens the profile.
4. Link-code flow: a learner issues a code, a second parent redeems it (pending), the learner confirms (verified).
5. The parent re-enters the password, switches into a child's context, sees the context strip, and exits.
6. A super admin assigns two roles to a staff user; the audit viewer (`filament.admin.resources.audit.audit-logs.index`) lists the events.
7. Every guest and account screen renders in Arabic (rtl) and English (ltr); the language switch returns to the same page.

Key rows after the run: 3 `guardian_child_links` (2 created-by-parent, 1 link-code, all verified), 2 email-less child users, audit events `guardian.child_created`, `guardian.link_code_issued`, `guardian.link_requested`, `guardian.link_confirmed`, `guardian.context_entered`, `guardian.context_exited`, `user.roles_changed`. Roles/permissions seed: 10 roles, 0 age bands (cutoffs unapproved, O-015).

## Gap found by the demo and repaired

Public registration only created learners, so no adult could become a parent and journey J02 could not start. Registration now takes `account_type` of `learner` (default) or `parent`; staff roles are rejected (`tests/Feature/Identity/AccountTypeTest.php`, 7 tests). The register screen has the choice in both languages. This follows PRD J02 ("Register → add your child") and is flagged here because it is a product decision the specs did not state; the owner can veto it.

## A2 — RTL screenshot review

Reviewed by the root session: Arabic family (1440), register (390), 2FA setup (1440) and child-reset dialog (390), plus the English family and login captures. Mirroring is correct (logo at the start edge, language switch at the end edge, right-aligned labels and hints, primary buttons at the start edge, radio and checkbox controls on the reading-start side, LTR secrets and usernames stay LTR). One defect: an Arabic sentence ending in a Latin product name pushed the full stop to the line start in the 2FA setup text. Fixed by ending the sentence on an Arabic word (`lang/ar/pages.php`, `tf_step_app`). The remaining AR/EN screenshots were not individually inspected; the matrix is covered by automated axe, direction and overflow assertions. This is not the formal `ux-reviewer` score.

## Checklist (docs/04 §3 and §14A) — items a test proves

`docs/04_DELIVERY_ACCEPTANCE_CHECKLIST.md` was **not edited**: its working copy differs from the committed version (older text, version 1.1 versus 1.2), so ticking it here would have mixed unrelated changes into the commit. The owner should tick the items below in the file they treat as current; the evidence is this record and the named tests.

Proven: registration/login/logout/reset/verification; deactivated users blocked; roles and permissions manageable; backend policies on direct URLs for the P1 surface (panels, audit, roles, guardian); Finance cannot alter curriculum/security by default; parent links multiple children; parent cannot access unlinked children; child with username and no email; link code single-use, expiring and confirmed; parent context recorded as actor.
Left open: instructor access to assigned courses and independent-instructor publish approval (P3 domains); separate child progress (P4); guardian consent and minor privacy policy (external gate); parent visibility into AI conversations (policy open); age band computed server-side (resolver exists, no approved cutoffs, O-015); certificate-name lock and everything in P3+.

## Open items that keep the gate from closing

- Independent verification waves not run: T-P1-05, -06, -10, -11 need `reviewer` (high risk) / `ux-reviewer` / `qa-verifier` PASS; T-P1-03, -04, -07, -08 remain `review`. Formal visual score for the staff panels is 4.0 against the 4.5 threshold.
- T-P1-07 step 1: age-band numbers are OPEN O-015 and were not invented.
- Owner decisions and external inputs still pending: official brand (O-027), legacy hostname, approved terms/privacy versions (registration shows an "unavailable" notice until set), SMTP/provider credentials, minors' AI/privacy policy.
- G5 "none skipped" and the G3 coverage thresholds as described above.
- Exact-head hosted CI for the new commits has not run (nothing pushed).
- Performance budgets (ED-030) for the new pages were not measured.

## Repairs made during the gate

- `tests/Pest.php`: the Arch suite now boots the application like the other suites; it failed when run alone because `app_path()` needs a booted app. No rule was loosened.
- Registration account type and the Arabic sentence fix above.
