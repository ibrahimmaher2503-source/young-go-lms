# P0/P1 review queue audit — 2026-11-17

Independent role: PRD auditor and security reviewer. Scope: T-P0-MG, T-P1-03, T-P1-04, T-P1-07, T-P1-10, T-P1-11 and T-P1-MG. Read-only source review; no implementation changes, commits, browser execution, shared database changes, or task-status changes. Status remains solely in `agents/PROGRESS.md`.

## Snapshot and evidence limits

- Inspected HEAD: `aa903cd3aad82043f17b81b3141368a40f4532fb`.
- Relevant tracked dirty documents: `HANDOFF.md`, `PROJECT_STATE.md`, `docs/04_DELIVERY_ACCEPTANCE_CHECKLIST.md`, `docs/evidence/p1-milestone/README.md`. Numerous P1-08/09/10/11 screenshot files are also modified. Existing changes were preserved.
- Relevant concurrent AI work: modified `config/ai.php` and `app/Domain/AI/PromptRenderer.php`; untracked `app/Domain/AI/Assistant/` and AI models. These are not attributed to the P1 tasks. The current style-tier integration observation below includes this uncommitted state.
- Owning task files, AGENTS, review protocol/gates/brief template, PRD §§5/6/22/29, decision O-015/D-005, ED-016/017, conventions, design/UX contracts, traceability/reuse records, implementation callers and named tests were read.
- Test assertions were inspected, not executed by this reviewer. A source-backed criterion PASS below means the implementation and meaningful assertions exist; fresh QA is the root verifier's responsibility. Historical result counts do not prove this snapshot.
- No independent visual score or screenshot comparison was performed here. Screenshot existence and axe assertions are not D-026 acceptance. No UX/QA PASS is issued.

## Verdicts

| Task | PRD verdict | Security verdict | Closure implication |
|---|---|---|---|
| T-P0-MG | NOT VERIFIED | No new security feature in this milestone task | A1/A2 have records; fresh milestone and reference acceptance still needed |
| T-P1-03 | FAIL | FAIL for the explicit audited-bypass requirement; no privilege-escalation exploit established | F1 blocks closure |
| T-P1-04 | PASS for functional A1–A3, subject to fresh tests | PASS within inspected structured-redaction/read-only-viewer scope | Overall approval remains NOT VERIFIED until current G7 and QA evidence |
| T-P1-07 | FAIL for the complete task; A1/A2 source-backed | No new high-risk authorization entry point | F2 is an external specification blocker; do not invent ranges |
| T-P1-10 | FAIL for current browser acceptance evidence | No new high-risk exploit established in inspected family/auth UI entry points | F3 and F4 block closure |
| T-P1-11 | FAIL | FAIL for sensitive-state/audit atomicity, F5 | Backend A1–A4 largely implemented; browser/visual acceptance also open |
| T-P1-MG | FAIL | Inherits unresolved RBAC/2FA findings | Dependencies and milestone gates remain open |

## Findings

### F1 — BLOCKER: super-admin bypass still lacks its required audit

`app/Providers/AppServiceProvider.php:72` returns true for super_admin at line 73 without recording the bypass. `agents/tasks/P01.md:69` explicitly requires the bypass to be "still audited". Sensitive mutations do have Action-level audit calls, for example `app/Domain/Identity/Actions/AssignStaffRoles.php:46` and `UpdateRolePermissions.php:44`; those do not prove that this explicit implementation step is met. The existing acknowledgement/deferment in `docs/evidence/p1-milestone/README.md:75` is not a Decision Register exception.

Impact: privileged authorization bypass has less provenance than the task requires. This is a task/audit-compliance failure, not evidence that arbitrary readers can become super_admin. Resolve with a defined, minimally noisy audit contract or an approved higher-priority decision changing the requirement. Add a regression that exercises the chosen bypass path and asserts its audit provenance; retain inactive-account denial and the guardian relationship exception.

### F2 — BLOCKER, external: no approved age-band defaults

`agents/tasks/P01.md:162` requires seeded editable defaults; `database/seeders/DatabaseSeeder.php:13` seeds roles/categories/CMS only. PRD §5.1 (`docs/01_MASTER_PRD_YOUNG_GO_LMS.md:269`) mandates configurability and prohibits hard-coded brackets, while O-015 (`docs/07_DECISION_REGISTER.md:243`) leaves cutoffs open. Existing tests intentionally create test-only ranges.

Do not fix this by guessing ages. A1/A2 can pass for the resolver and DB-driven model while the seed step remains blocked. Obtain approved O-015 ranges or a recorded decision narrowing the task. When approved, test seeded boundaries and reseed behavior without overwriting intentional administrator edits.

### F3 — BLOCKER: current browser journey assertions target an obsolete landing page

`tests/Browser/FamilyFlowsTest.php:43`, `:50` and `:78` assert `/profile` after verification or login. `tests/Browser/TwoFactorScreensTest.php:57` makes the same assertion after 2FA. Current `app/Http/Controllers/PageController.php:72` routes learners through `/home` to `/my`; verification returns `/home` in `AuthenticationController.php:142`, and `resources/views/pages/login.blade.php:8` submits with success `/home`.

These current source contracts disagree. This is confirmed test/implementation drift; no browser run is claimed here. Historical green family/2FA runs in `docs/evidence/p1-milestone/README.md:89` cannot establish present A1/G7. Keep the intended learner-dashboard behavior, update assertions to verify its meaningful visible state, then rerun both complete browser suites. Do not restore `/profile` just to satisfy old tests without a product requirement.

### F4 — BLOCKER, missing acceptance: full visual/UX review is still open

`docs/evidence/p1-milestone/README.md:77` records a 3.9/5 UX review, below the required 4.5 average; `:83` explicitly says the follow-up was not independently rescored. `:89` limits the later root comparison to Arabic mobile family and 2FA states. `docs/design-system/VISUAL_ACCEPTANCE.md:9` and `agents/VERIFICATION_GATES.md:44` require actual comparison to the task reference, not just screenshot/axe success.

The broad G7 PASS at milestone evidence line 20 therefore cannot serve as final current D-026/UX acceptance. T-P1-04's audit viewer also shares the staff workspace whose visual task is blocked (`agents/PROGRESS.md:43`). Capture/review the actual current matrix and record each task's reference identity, compared regions, remaining differences and independent UX result. This reviewer does not invent visual defects or a replacement score.

### F5 — BLOCKER: 2FA disable/recovery regeneration can commit without the mandatory audit

`app/Domain/Identity/Actions/DisableTwoFactor.php:27` saves the cleared secret/recovery state at line 30, then audits at line 32; `RegenerateRecoveryCodes.php:25` saves new recovery hashes before auditing at line 26. Neither action wraps the mutation and audit in a transaction. Each throws after a null audit result, so an audit failure can produce an error response while retaining the security change without its mandatory audit event. Regeneration additionally invalidates the previous recovery codes even though the caller receives no new codes.

The same module already has the appropriate pattern: `ConfirmTwoFactorEnrollment.php:21` and `VerifyTwoFactorChallenge.php:21` wrap security changes and audit in transactions. Apply that pattern to these two existing Actions. Add focused tests making the audit return null or throw and asserting the original secret/confirmation/recovery hashes remain, with a successful-path audit assertion. This is a source-proven error-path defect; an actual outage was not simulated by this reviewer.

### F6 — BLOCKER for milestone closure: prerequisite/gate conditions remain unmet

`agents/VERIFICATION_GATES.md:25` requires all milestone tasks done; the board still has P1 reviews plus blocked T-P1-08 (`agents/PROGRESS.md:38`–`:47`). The owner exception allowing review dependencies to start work (`agents/PROGRESS.md:175`) does not make the milestone accepted.

`tests/Feature/Invariants/I02_ObjectScopeTest.php:14` still declares a TODO. T-P1-03 explicitly allows that scaffold, so it is not by itself a missing RBAC acceptance implementation; however G5's "none skipped" condition (`agents/VERIFICATION_GATES.md:13`) is unsatisfied. Preserve this distinction rather than weakening/deleting the TODO to obtain a green count. Performance budgets remain unmeasured (`docs/evidence/p1-milestone/README.md:91`; milestone rule at `agents/VERIFICATION_GATES.md:29`). Coverage/runtime/CI records must be reconciled to the current snapshot.

### F7 — SHOULD: clarify the age-band AI hook before accepting its consumer

T-P1-07's AI line (`agents/tasks/P01.md:156`) says AgeBandResolver output is the AI style-tier input. The resolver returns an AgeBand (`app/Domain/Catalog/AgeBandResolver.php:13`), with editable translated label/min/max/sort (`Models/AgeBand.php:12`), but no stable style-tier identifier. The currently untracked consumer `app/Domain/AI/Assistant/StyleTier.php:16` independently calculates age and uses separate `ai.adult_age`/`ai.teen_age` values rather than consuming AgeBandResolver.

No hard-coded numeric cutoff was found in these config defaults; null defaults are intentional O-015 safeguards. The concern is two possible age classifications diverging after an administrator edits bands. Do not add a provider call or guess a mapping in P1. Record the hook/mapping decision and make the eventual AI consumer use the single approved classification; add a consumer test after that decision. The untracked consumer is concurrent work and is not attributed as a committed P1 regression.

## Acceptance and implementation map

### T-P0-MG

- **A1 — source/record PASS:** P0-01..07 are recorded done at `agents/PROGRESS.md:28`; open real-world inputs remain documented in Notes. This is a board-state check, not a fresh re-audit of every P0 feature.
- **A2 — source/record PASS:** `PROJECT_STATE.md:49` and `HANDOFF.md:43` distinguish the development profile/earlier CI from unknown legacy, deployment, provider and recovery evidence. The later P1 handoff (`HANDOFF.md:57`) identifies the current branch. Earlier summaries/counts in the same documents are historical, not current QA evidence.
- **Demo step — recorded, NOT VERIFIED at this HEAD:** reproducible clone/setup/up/QA/token-preview procedure and execution results exist at `docs/evidence/p0-closure/README.md:5` and `:16`. The execution does not claim an actual fresh clone at this HEAD; its CI/log counts predate current source.
- **G7 — NOT VERIFIED:** eight preview variants are recorded at `docs/evidence/p0-closure/README.md:25`; current task-linked reference comparison has not been independently established by this reviewer.
- **AI:** none; this milestone is an evidence/document consolidation task, not authority for additional AI code.

### T-P1-03

- **A1 — source-backed PASS:** `RolePermissionSeeder.php:20` uses findOrCreate, only applies role defaults for newly created roles (`:30`), and preserves revoked defaults. Meaningful tests: `tests/Feature/Identity/RbacCatalogTest.php:26` and `:104`.
- **A2 — source-backed PASS:** finance defaults in `config/permissions.php:111` omit course.publish and system.security.manage; explicit negative test at `RbacCatalogTest.php:40`.
- **A3 — source-backed PASS:** catalog uniqueness/naming/grant-subset test at `RbacCatalogTest.php:47`.
- **Other steps:** all ten role names/catalog exist, shared policy trait delegates to Laravel Gate (`app/Policies/Concerns/AuthorizesWithPermissions.php:11`); roles-by-ability allow/deny dataset exists at `RbacCatalogTest.php:63`. It is inline rather than a separately reusable harness. Pending invariant scaffold is exactly task-requested; F6 explains why the milestone still cannot call G5 passed.
- **Inactive/guardian safeguards:** `AppServiceProvider.php:63` denies inactive users before bypass; `:68` excludes guardian.child.access from bypass. Tests cover inactive super-admin and guardian relationships.
- **Unmet step:** F1 audited bypass.
- **AI hook — PASS as catalog:** required ai.configure, ai.view_usage, help_request.reply/manage and lesson_document.approve are present in `config/permissions.php:12` and following catalog lines. No provider or AI conversation UI required here.
- **G7:** backend task's assignment UI is expressly out of scope; defer its visual acceptance to T-P1-08 rather than fabricating a standalone RBAC screen.

### T-P1-04

- **A1 — source-backed PASS:** recursive key-based redaction rejects sensitive structured fields and opaque objects in `AuditLogger.php:61`; `tests/Feature/Audit/AuditLoggerTest.php:12` asserts stored JSON excludes nested secret values. This proves the declared structured-key contract, not recognition of arbitrary secret strings under innocent keys.
- **A2 — source-backed PASS:** actor/subject/acting_for in `AuditLogger.php:35`; explicit stored-context test at `AuditLoggerTest.php:70`. Automatic child-context provenance is shared with ActingLearner; no child conversation content is exposed.
- **A3 — source-backed PASS:** `app/Filament/Resources/Audit/AuditLogResource.php:43` checks audit.view, disallows create/edit/delete, scopes log_name and eager-loads causer (`:68`). `AuditViewerTest.php:15` exercises guest, denied and granted users plus non-audit exclusion/read-only capability.
- **Other steps:** actor/subject/event/date filters exist at `AuditLogResource.php:87`; actor and subject type are raw text inputs, an acknowledged usability follow-up rather than missing filter logic. Audit query composite-index assertions exist at `AuditViewerTest.php:41`. Registry row: `docs/REUSE_REGISTRY.md:26`.
- **G7 — NOT VERIFIED:** current viewer inherits F4 staff visual blocker; screenshots alone are insufficient.
- **AI:** none in this logging/viewer scope. The logger being reused by later AI Actions does not add AI functionality here.

### T-P1-07

- **A1 — source-backed PASS:** `AgeBandResolver.php:19` validates month, computes age from year/month and resolves inclusive DB bounds. Dataset boundaries/year rollover at `AgeBandResolverTest.php:14` and `:29`; missing data/config and invalid-month cases at `:59` and `:65`.
- **A2 — source-backed PASS:** DB-stored translated labels/ranges/sort, with update-and-reresolve test at `AgeBandResolverTest.php:40`. A later administration screen is not required by this backend task's "in admin later" criterion.
- **Other steps:** nullable inclusive AgeRange and data-aware validation rule exist; behavior tests at `AgeBandResolverTest.php:70` and `:89`. Production seed defaults remain F2.
- **AI hook — PARTIAL / consumer NOT VERIFIED:** resolver API exists; F7 identifies missing single-source integration in concurrent AI work. Birth fields are not passed to a provider by this resolver.
- **G7:** no UI introduced by the resolver itself; referenced course wizard belongs to its owning later UI task.

### T-P1-10

- **A1 — FAIL current acceptance consistency:** full browser scenarios exist at `FamilyFlowsTest.php:29` (register/verify/login), `:60` (parent creates child/child login), `:81` (context switch) and `:99` (link confirmation). F3 must be resolved and the actual suites rerun; old green output is insufficient.
- **A2 — NOT VERIFIED current output:** AR/EN × 390/1440 screenshot/axe/overflow matrix at `FamilyFlowsTest.php:115` and `:127`; context strip captured at `:154`; additional errors have separate browser coverage. Many captures are dirty; current reference review remains F4. Existing dark test only covers login/register at mobile (`:169`), so it does not prove the owner-required full light/dark matrix.
- **A3 — source coverage present, runtime NOT VERIFIED:** feature rendering/error/locale cases in `tests/Feature/Pages/FamilyPagesTest.php:51` onward and per-screen axe assertions in FamilyFlowsTest. Root must run translation parity and current axe checks; visual scores are distinct.
- **Other steps:** `components/layouts/base.blade.php:2` implements public/auth/student/parent variants with dedicated navigation and child strip; pages/profile contains names, certificate names, avatar, locale, timezone, notification preference and password. PageController family/link queries are scoped to the signed-in guardian/learner (`:97`, `:113`). Shared UI and existing JSON Actions are reused; no AI UI found in these task screens.
- **AI:** none; honored in inspected layout/auth/profile/family/link/error scope.

### T-P1-11

- **A1 — source-backed PASS for positive/negative behavior; security exception F5:** `EnforceTwoFactor.php:21` gates required roles and enrolled users; all three panels use the middleware. `TwoFactorTest.php:52` covers super_admin/admin/finance; `:80` encryption/hidden properties, `:90` new-session challenge, `:104` replay, `:114` one-use recovery, `:145` optional/required disable, and per-user throttle tests later in the file. Secrets use encrypted model casts; recovery codes stored as HMAC hashes inside encrypted JSON; challenge consumption uses a row lock. Default enforcement is true (`config/identity.php:11`).
- **A2 — source-backed PASS:** `tests/Feature/Identity/ChildRecoveryTest.php:71` exercises Action, HTTP and family-screen refusal for unverified email, then success after verification. Creation reuses CreateChildAccount.
- **A3 — source-backed PASS:** `ResetChildPassword.php:24` requires active parent role/permission, student target and verified relationship; transaction at `:32` changes password/remember token, deletes DB sessions and records audit. Guardian route requires password.confirm (`routes/guardian.php:18`). Tests at ChildRecoveryTest `:87`, `:100`, `:113` and `:127` verify old password rejection, session invalidation, stranger/super-admin rejection, confirmation and 404 object hiding.
- **A4 — source-backed PASS:** `LearnerNotificationRouter.php:22` preserves normal delivery for learners with email; `:28` sends email-less child database-only and selects verified linked guardians. It filters active/verified-email guardians and selects their locale. `GuardianCopyNotification.php:35` omits child action links. Tests at ChildRecoveryTest `:143`, `:167`, `:176`, `:187` cover routing, locale selection, actual inbox persistence and omitted action link. External SMTP delivery is not proven by tests.
- **A5 — NOT VERIFIED current reference acceptance:** screen matrix exists at `TwoFactorScreensTest.php:78` and captures AR/EN desktop/mobile setup/enabled/challenge/reset states; current G7 reference matching and UX review remain F4. Current dark-mode coverage is absent from this matrix.
- **Other steps:** the reset endpoint has trust-boundary validation and permission/object scoping; no new SMS or AI feature in this scope. F5 prevents an unqualified security PASS even though most planned protections are present.
- **AI:** none; honored in inspected security/recovery/notification scope.

### T-P1-MG

- **A1 — PARTIAL / NOT VERIFIED:** the working checklist §3 has selected evidence-linked ticks, while protected-route/policy and other items remain open. The demo `tests/Feature/Milestone/P1DemoTest.php` records register/verify/two-child/login/link/context/roles/audit and bilingual routes; `docs/evidence/p1-milestone/README.md:28` records an earlier execution. Need root's current run and exact item-to-test reconciliation; do not tick downstream progress/minor-policy requirements from identity tests.
- **A2 — PARTIAL / NOT VERIFIED:** root reviewed selected RTL captures according to evidence `:43` and `:89`; full set/reference/current-state comparison and independent UX rescore remain open.
- **Prerequisites and gates — FAIL:** F1–F6 and unresolved T-P1-08 acceptance. The evidence's own "not closable" conclusion at line 7 is correct; its blanket historical G7 PASS is not a current visual acceptance verdict.
- **Continuity — exists:** PROJECT_STATE/HANDOFF include later closure links and unknown operational inputs. Older nested summaries/counts should be labelled historical when consolidated.
- **AI:** none; no additional AI work is needed to execute this milestone demo.

## Minimum next verification

1. Root runs the current focused RBAC/Audit/AgeBand/Identity/P1Demo suites and records the snapshot, then the standard applicable gates without altering the shared production/legacy systems.
2. Repair F3 in the existing tests for intended dashboard routing, rerun FamilyFlowsTest and TwoFactorScreensTest, inspect actual failures, and retain meaningful flow assertions.
3. Repair F5 with atomic mutation/audit and failure regressions; resolve F1 through implementation or a documented higher-priority decision. Do not classify an old report as proof of those fixes.
4. Keep F2 pending owner decision; reconcile F7 with the owning AI task before its acceptance.
5. Obtain the complete root visual comparisons and independent UX score, plus applicable milestone performance/coverage evidence. Preserve intentionally open downstream checklist rows.

Skipped: no live browser, database, CI, SMTP, deployment, or screenshot scoring was performed by this reviewer; root owns fresh execution and final visual judgment.
Risk: prior green counts and old security/UX reports are not acceptance of the current dirty checkout; unresolved audit atomicity and task/visual gates prevent closure.
