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

## Independent review wave (2026-11-17)

Three read-only reviewer agents ran against the committed code: security/correctness, requirements audit, and UX. Their reports are model output, not owner approval; every finding below was checked against the code before acting.

**Security/correctness — PASS on all four areas** (2FA, child reset and notifications, guardian linking, registration); no critical or high findings. Fixed after review: per-user cap on 2FA attempts (10 per 15 min) on top of the per-address limit; recovery codes lengthened to 40 bits; a passed 2FA challenge is forgotten on any new sign-in in the same session; 2FA enrollment now needs the account password; setup page sent with `no-store`; guardian email copies never include the original action link; one failing guardian mailbox no longer stops the others; redeeming a link code needs a verified email; `password.confirm` GET route exists so non-JSON requests do not 500; `recovery` added to audit redaction. New tests cover each. Left as known low risks: link-code entropy (about 40 bits, rate limited, learner must confirm), email enumeration at sign-up, no minimum age at self-registration (PRD silent), `acting_for` is added automatically for the context child even if the parent audits another subject.

**Requirements audit — PASS-WITH-NOTES for T-P1-03/04/07/08/10/11 and T-P1-05; PASS for T-P1-06.** Fixed: stale reuse-registry rows, missing screenshots/axe for the 403/419/429/500/503 pages (20 captures added). Not fixed, deferred on purpose: audit of the super_admin `Gate::before` bypass (T-P1-03 step 3 says "still audited"; sensitive super_admin changes are audited in their Actions, a gate-level audit needs an owner decision on noise); reusable roles-by-ability test helper; navigation groups mirroring PRD 19.2 (T-P1-08 step 5, items appear as modules ship); audit viewer subject-type and actor filters need select inputs; Form Request classes for the two controllers (SHOULD); defaults to record: username rule (`alpha_dash`, min 3), password minimum 8, link-code re-issue deletes unused codes, `app:two-factor-reset` and self-disable for optional roles (not in the spec). Open owner question: a parent can reset the password of any verified-linked learner, including an adult who linked by code; no age cutoff exists (O-015).

**UX review — 3.9 of 5, below the 4.5 threshold** (auth 4.2, profile 3.7, family 4.0, link 4.2, 2FA 4.0, errors 3.6, child strip 3.4). Fixed after review: Arabic child-context copy and a sticky strip, profile layout and distinct save labels, "ولي الأمر" wording, read-only email on the reset page, centred error cards, recovery-code copy button and a warning when none are left, challenge page without the signed-in pill, buttons stay disabled while the page navigates. Still open (J02 and polish): password-strength meter and "copy login details" on the child card, per-child primary action and a call to action in the empty state, birth month as a select, "Go back"/retry actions and a support channel on error pages, loading text on buttons, lighter inline error style, dark-mode card elevation, a stronger strip colour. These were not re-scored, so the UX score stays at 3.9 until an independent re-review.

**Status effect:** T-P1-05 and T-P1-06 have no screens of their own and passed the reviewer and requirements audit, so they are recorded as `done` in agents/PROGRESS.md. T-P1-10 and T-P1-11 stay `review` (UX below threshold). T-P1-03/04/07/08 stay `review` (see notes above and the open O-015 seed). The milestone gate stays `review`.

### UX follow-up pass (2026-11-17)

Closed the remaining items from the UX review: password-strength meter (child form and register), copy sign-in details on each child card (username and sign-in link only, never the password), birth month as a select that starts empty, a primary action on each child and in the empty state, back/retry actions and an optional support email (`SUPPORT_EMAIL`, shown only when set) on error pages, lighter inline field errors, visible "please wait" text on busy buttons, a stronger child-context strip border. Not changed on purpose: dark-mode card elevation (the page/surface tokens are shared with the owner-approved staff reference, D-026; a swap needs a design decision), 429 wait time. The UX score has not been independently re-scored.
Caveat: four staff-panel browser tests (users/roles at 390 px, axe "scrollable region must have keyboard access") fail while uncommitted changes to `resources/css/filament-theme.css` and a new Categories resource are in the working tree; they are not part of this change and passed before it.
