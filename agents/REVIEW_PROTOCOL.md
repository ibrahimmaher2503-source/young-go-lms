# REVIEW PROTOCOL (orchestrator and reviewer)

Review the **diff** and affected callers/dependencies, not only the report.
Apply only relevant checks: docs-only work requires structural/semantic review,
not absent Laravel tooling. Use VERIFICATION_GATES applicability and bootstrap rules. Every finding cites `file:line` and the
rule broken. Severity: **BLOCKER** (must fix before done) · **SHOULD** (fix
in this task unless justified) · **NIT** (optional).

## R1 Spec compliance
- [ ] Each acceptance criterion of the task is implemented. (BLOCKER if missing)
- [ ] Each criterion maps to an actual current successful test or inspected manual result, with command/procedure, outcome, artifact and final revision. Tags alone do not suffice. (BLOCKER)
- [ ] Nothing out of scope was added (features, packages, refactors). (SHOULD)
- [ ] OPEN items were not guessed; defaults follow PRD §29 labels. (BLOCKER)

## R2 Security and invariants
- [ ] Authorization server-side (Policy/`can`) on every new entry point. (BLOCKER)
- [ ] Learner content access only via `EntitlementService`. (BLOCKER)
- [ ] Object scoping: instructor → assigned; parent → linked children; student → own. (BLOCKER)
- [ ] Idempotency by unique constraint for payments, entitlements, ledger, notifications, certificates, help requests, imports. (BLOCKER)
- [ ] Webhooks verify signature + amount + currency + order ref. (BLOCKER)
- [ ] No secrets, tokens, card data, full AI messages, ID documents in logs or code. (BLOCKER)
- [ ] File uploads validated (MIME, size, extension) and stored privately. (BLOCKER)
- [ ] Input validation via Form Requests / Livewire rules. (SHOULD)
- [ ] Rate limits on auth, AI, contact, webhooks where relevant. (SHOULD)

## R3 Architecture and reuse
- [ ] Business logic in Actions; no DB writes in controllers/Livewire/Filament. (BLOCKER)
- [ ] No cross-module `Support/` imports; module boundaries respected. (BLOCKER via arch tests)
- [ ] Reused registered pieces instead of duplicates (search registry + grep). (BLOCKER if duplicate of a registered piece)
- [ ] New shared pieces added to `docs/REUSE_REGISTRY.md`. (SHOULD)
- [ ] Money as integer minor units / brick/money; UTC timestamps. (BLOCKER)
- [ ] Enums for statuses; no magic strings. (SHOULD)
- [ ] Migrations reversible, additive, indexed (FKs, unique keys, frequent filters). (SHOULD)
- [ ] No N+1 on lists (eager loading); pagination on all lists. (SHOULD)

## R4 Localization and UI (UI tasks)
- [ ] All strings in AR + EN. (BLOCKER)
- [ ] Only tokens; only logical direction utilities. (BLOCKER)
- [ ] Composed from `components/ui` primitives. (SHOULD)
- [ ] Loading / empty / error / success states. (SHOULD)
- [ ] Screenshots exist for AR/EN × mobile/desktop (+ light/dark for SKIT) and look correct: alignment, mirroring, truncation, no overflow. (BLOCKER if missing)
- [ ] Accessibility: labels, focus, contrast, touch targets; axe clean. (SHOULD; serious/critical = BLOCKER)

## R5 Tests and quality
- [ ] Tests are meaningful (assert behavior and state, not only status 200). (SHOULD)
- [ ] Authorization datasets include forbidden cases. (BLOCKER for auth-related tasks)
- [ ] No weakened/deleted invariant or arch tests. (BLOCKER)
- [ ] All applicable G1–G11 pass; N/A is justified and required NOT RUN checks remain blockers. (BLOCKER)

## R5A PRD lock and traceability
- [ ] Every change traces to a cited requirement or task step; nothing extra. (BLOCKER)
- [ ] Executable acceptance tests carry task + requirement tags; G10/manual bootstrap map links successful results, not just tags. (BLOCKER)
- [ ] The task's `ai:` line was honored (hook built, or nothing AI added). (BLOCKER)
- [ ] Defaults used match PRD §29 / ED values exactly. (BLOCKER)

## R5B One system
- [ ] Access decisions via `canAccessItem()` with reason codes; ordering via `curriculum_items`. (BLOCKER)
- [ ] UI follows `15_UX_FLOWS_AND_PATTERNS.md` pattern table (no new patterns without registry). (SHOULD → BLOCKER if it creates a second pattern for the same job)
- [ ] Copy uses shared language files (`lang/*/access.php`, `ai.php`, `common.php`). (SHOULD)

## R6 Domain-specific (when touched)
- **AI:** eligibility function only; PDF-only source; quiz guard; PII masking; quote verification; no tools; fake provider in tests. (BLOCKER)
- **Payments/Paymob:** browser return never fulfills; manual proof duplicate reference blocked; Saudi disabled. (BLOCKER)
- **Ledger:** one entry per source event; reversals not edits. (BLOCKER)
- **Family:** parent actions audited as actor; child data minimized. (BLOCKER)
- **Migration:** dry-run mode, idempotent rerun, reconciliation counts. (BLOCKER)

## Verdict
`APPROVE` only when there are zero BLOCKERS and all applicable review work was
performed. Unavailable evidence/tooling yields NOT RUN, not approval. Identify
reviewer/session and reviewed revision; self-review is explicitly labeled and
does not fulfill an independent review gate. Record the verdict and the
criteria→evidence map under the task in `agents/PROGRESS.md`.
