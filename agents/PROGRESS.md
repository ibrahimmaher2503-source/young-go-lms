# PROGRESS — Young Go LMS agent loop

LOOP_STATE: RUN

Statuses: `todo` · `in_progress` · `review` · `done` · `blocked` · `skipped`. Only the orchestrator edits this file.

## Current
- Owner goal: complete the remaining task board. Active review: T-P4-01 access checks used by the course player and upcoming progress work.
- Milestone focus: P1 closure (owner requested T-P1-08 visual rework followed by T-P1-MG follow-up).
- Active scope: verify existing P1 work, repair the staff workspace and record unmet requirements. Later development is tracked on its own task rows.
- Branch: feat/lms-build; origin https://github.com/ibrahimmaher2503-source/young-go-lms.git.

## Blockers (credentials / OPEN decisions / owner gates)
- **B-01 Legacy domain mismatch (O-001, escalated):** public site at owner-stated `youngschool.com` is operated by Learning Care Group, Inc. (US childcare). Owner must confirm Young Go's real legacy hostname. Blocks T-P0-04 brand extraction (placeholders only, O-027) and T-P10-01.
- **B-02 Resolved locally:** repository initialized against origin/main; develop is not created; GitHub Actions run 43967044917 on a14fae2 is verified successful. Current local repairs still require exact-head hosted verification.
- **B-03 Resolved:** verified Composer 2.11.2 at .tools/composer.phar; 221 packages installed successfully. sockets must be enabled for Pest browser.
- **B-04 Local profile verified:** SQLite created without overwriting data, migrations/seed and scheduler heartbeat passed app:doctor during closure audit. Redis/async worker remain Linux-CI checks. Historical host inventory: MariaDB 10.4.32 (XAMPP) installed but stopped; port 5432 Postgres belongs to Odoo (not for this project); no Redis, no phpredis, no Docker/WSL distro. Needed by T-P0-03 (decide in T-P0-02).
- **B-05 Staging/production hosting, DB, Redis, mail, storage UNKNOWN (O-002, O-004, O-005).**
- **B-06 Legacy access UNKNOWN (O-003):** admin, source, DB, storage, video providers, live provider, Instapay workflow.
- **B-07 Paymob credentials + legal seller details UNKNOWN** (D-025 selected; O-006, O-023).
- **B-08 AI provider UNKNOWN (O-014).**
- **B-09 `agents/ANTI_HALLUCINATION_PROTOCOL.md` missing** from the pack (named in the brief).

## Task board

| Task | Title | Status | Deps | Agents | Size | Risk | Commit | Evidence |
|---|---|---|---|---|---|---|---|---|
| T-P0-01 | Access and environment inventory | done | — | orchestrator (no code), chore | M | normal | 02453d3 | docs/ENVIRONMENT_INVENTORY.md; independent foundation audit |
| T-P0-02 | Stack compatibility and architecture decision record | done | T-P0-01 | orchestrator, chore | S | normal | 96140ad, 02453d3 | docs/adr/ADR-001-stack.md; lockfiles; independent foundation audit |
| T-P0-03 | Application skeleton, CI and quality gates | done | T-P0-02 | implementer, chore | M | normal | c64dfd8, 02453d3 | CI run 43882611107; local architecture positive and negative checks |
| T-P0-04 | Brand extraction and design tokens | done | T-P0-03 | ui-builder, chore | M | normal | 47cf45a | G10; UX PASS 4.75; eight screenshots; 34 contrast pairs |
| T-P0-05 | Module map and initial domain/database map | done | T-P0-02 | orchestrator, chore | S | normal | fd4b74d | five Mermaid SVGs and scoped independent PRD PASS |
| T-P0-06 | Shared domain primitives | done | T-P0-03 | implementer, test-writer | M | normal | d9e851e | 12 tagged tests; Larastan; G10; independent scoped PRD PASS |
| T-P0-07 | Platform services: flags, settings, phone, doctor, coverage tooling | done | T-P0-03 | implementer, test-writer | M | normal | 8eb07c6, 023eae7 | A1-A4 reviewed; G10; CI 43960234987 |
| T-P0-MG | Milestone gate P0 | review | T-P0-01..07 | orchestrator | S | normal | fe6d741 | Earlier CI/browser pass; closure audit reopened demo/evidence consolidation (docs/reviews/PRIOR_WORK_CLOSURE_2026-11-17.md) |
| T-P1-01 | Localization foundation | done | T-P0-03, T-P0-06 | implementer, test-writer, chore | M | normal | 8937dd2 | G10; scoped PRD/QA review; 13 local checks; CI 43960056036 |
| T-P1-02 | Authentication and profiles | done | T-P1-01 | implementer, test-writer, chore | M | high | 564751d | CI 43963037649 and independent Luna xhigh review |
| T-P1-03 | RBAC catalog, roles and policies base | review | T-P1-02 | implementer, test-writer | M | high | | |
| T-P1-04 | Audit logging | review | T-P1-03 | implementer, test-writer | S | normal | | |
| T-P1-05 | Parent-created child accounts and username login | done | T-P1-03, T-P1-04 | implementer, test-writer, chore | M | high | 308cae9 | ChildAccountsTest 10 tests; I02 guardian case; composer qa tests 218 PASS; req-coverage PASS; independent security review PASS and requirements audit PASS-WITH-NOTES (docs/evidence/p1-milestone/README.md) |
| T-P1-06 | Link codes and parent context switching | done | T-P1-05 | implementer, test-writer | M | high | 94db9e5 | GuardianLinkingTest 11 tests; full Pest 229 PASS; Larastan, translations, RTL, req-coverage PASS; independent security review PASS and requirements audit PASS (docs/evidence/p1-milestone/README.md) |
| T-P1-07 | Age suitability model | review | T-P1-02 | implementer, test-writer | S | normal | | |
| T-P1-08 | Filament panels, SKIT theme and shared Filament blocks | blocked | T-P0-04, T-P1-03, T-P1-04 | implementer, ui-builder, test-writer, chore | L | normal | 75f492d | Isolated browser gates pass; three D-026 review cycles reached 4.33/5 against 4.5 required. Remaining visual and navigation differences are in docs/reviews/WORKSPACE_VISUAL_REVIEW_2026-11-17.md. |
| T-P1-09 | UI primitives library | done | T-P0-04, T-P1-01 | ui-builder, test-writer | L | normal | 564751d | CI 43963037649 and independent Luna xhigh review |
| T-P1-10 | Layouts, auth and family UI | review | T-P1-02, T-P1-05, T-P1-06, T-P1-09 | ui-builder, implementer, test-writer, chore | L | normal | (this commit) | FamilyPagesTest 17 feature + FamilyFlowsTest 47 browser (flows, axe, AR/EN x 390/1440 screenshots); full Pest 397 PASS; Larastan, translations, RTL, req-coverage PASS; independent ux-reviewer pending |
| T-P1-11 | Staff 2FA, child password reset and child notification routing | review | T-P1-06, T-P1-08, T-P0-07 | implementer, ui-builder, test-writer | M | high | (this commit) | TwoFactorTest 14 + ChildRecoveryTest 8 feature, TwoFactorScreensTest 19 browser (axe, AR/EN x 390/1440); full Pest 438 PASS; Larastan, translations, RTL, req-coverage PASS; independent reviewer pending |
| T-P1-MG | Milestone gate P1 | review | T-P1-01..11 | orchestrator | S | normal | (this commit) | Fresh demo, Identity, role/audit and family/2FA browser evidence; Checklist §3 updated. Open gates and dependency reviews in docs/evidence/p1-milestone/README.md |
| T-P2-01 | Categories and disciplines | review | T-P1-08 | implementer, test-writer, chore | S | normal | | See task log |
| T-P2-02 | CMS pages and homepage blocks | review | T-P1-08, T-P1-10 | implementer, ui-builder, test-writer, chore | M | normal | | See task log |
| T-P2-03 | Brand public shell and homepage UI | blocked | T-P2-02, T-P1-09 | ui-builder, chore | L | normal | | O-027 official branding inputs |
| T-P2-04 | Catalog, search and filters | blocked | T-P2-01, T-P3-01, T-P1-07 | implementer, ui-builder, test-writer | L | normal | | Search/filter slice verified; price, popularity and availability data pending |
| T-P2-05 | Course detail page and SEO fields group | todo | T-P2-04 | ui-builder, implementer, test-writer | M | normal | | |
| T-P2-06 | SEO infrastructure | todo | T-P2-02, T-P2-05 | implementer, test-writer | S | normal | | |
| T-P2-07 | Blog | todo | T-P2-06 | implementer, ui-builder, test-writer, chore | M | normal | | |
| T-P2-08 | Contact enquiries and newsletter | todo | T-P2-03 | implementer, ui-builder, test-writer | S | normal | | |
| T-P2-09 | Instructor profiles and category landing pages | todo | T-P2-04, T-P2-06 | implementer, ui-builder, test-writer | S | normal | | |
| T-P2-MG | Milestone gate P2 | todo | T-P2-01..09 | orchestrator | S | normal | | |
| T-P3-01 | Course domain, ownership and course list | review | T-P1-07, T-P1-08, T-P2-01 | implementer, test-writer, chore | L | high | | See task log |
| T-P3-02 | Course wizard: Basic and Information steps | review | T-P3-01 | implementer, ui-builder, test-writer, chore | M | normal | (this commit) | SaveCourseWizard 19 tests, wizard pages 7, browser 8 (axe, AR/EN x 390/1440); full Pest 564 PASS; Larastan, translations, RTL, req-coverage PASS; visual match record docs/reviews/T-P3-02_VISUAL_MATCH.md; independent reviewers pending |
| T-P3-03 | Course wizard: Pricing, Media, SEO, Custom fields, Finish and approval | todo | T-P3-02, T-P2-05 | implementer, ui-builder, test-writer | L | high | | |
| T-P3-04 | Curriculum manager | review | T-P3-01 | implementer, ui-builder, test-writer | L | normal | (this commit) | CurriculumManagerTest 15, page tests 8, browser 6 (drag and drop, keyboard, axe, AR/EN x 390/1440); full Pest 593 PASS; Larastan, translations, RTL, req-coverage PASS; visual record docs/reviews/T-P3-04_VISUAL_MATCH.md; independent reviewers pending |
| T-P3-05 | Lesson media sources, adapters and protection tiers | review | T-P3-04 | implementer, test-writer | L | high | (this commit) | MediaProvidersTest 40 + PaidT3PublishTest 12 + manager tier test; full Pest 637 PASS; Larastan, translations, RTL, req-coverage PASS; independent reviewer (high risk) pending |
| T-P3-06 | Media uploads and lesson editor UI | review | T-P3-05, T-P1-09 | ui-builder, implementer, test-writer | M | normal | (this commit) | UploadLessonMediaTest 16, editor page tests 2, browser 4 (AR/EN x 390/1440, axe); full Pest 659 PASS; Larastan, translations, RTL, req-coverage PASS; independent reviewers pending |
| T-P3-07 | Course revisions after publication | todo | T-P3-03, T-P3-04 | implementer, ui-builder, test-writer | M | high | | |
| T-P3-08 | Private files, lesson resources and curriculum PDF | review | T-P3-04 | implementer, test-writer | M | high | (this commit) | PrivateFilesTest 21 + editor tests 2 + browser 4; full Pest 686 PASS; Larastan, translations, RTL, req-coverage PASS; independent reviewer (high risk) pending |
| T-P3-10 | AI foundation (M7.0) | review | T-P3-08 | implementer, test-writer | M | high | | |
| T-P3-09 | Curriculum PDF ingestion pipeline (M7.1) | review | T-P3-08, T-P3-10 | implementer, ui-builder, test-writer | L | high | | |
| T-P3-11 | Quiz and assignment authoring hooks | review | T-P3-04 | implementer, test-writer | S | normal | | |
| T-P3-12 | Duplicate, archive and safe delete | todo | T-P3-03, T-P3-04, T-P3-08 | implementer, test-writer | S | normal | | |
| T-P3-13 | Content library | todo | T-P3-05, T-P3-06 | implementer, ui-builder, test-writer | M | normal | | |
| T-P3-14 | Subtitle tracks | review | T-P3-05 | implementer, test-writer | S | normal | | |
| T-P3-15 | Rich-text lessons with restricted blocks | todo | T-P3-04, T-P0-07 | implementer, ui-builder, test-writer | M | high | | |
| T-P3-MG | Milestone gate P3 | todo | T-P3-01..15 | orchestrator | S | normal | | |
| T-P4-01 | Unified EntitlementService | review | T-P3-01, T-P1-04 | implementer, test-writer | L | high | | |
| T-P4-02 | Free enrollment, manual grants and course access summary | review | T-P4-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P4-03 | Course player and curriculum navigation | review | T-P4-01, T-P3-05, T-P1-09 | ui-builder, implementer, test-writer | L | normal | | |
| T-P4-04 | Progress, resume and completion state | todo | T-P4-03 | implementer, test-writer | M | high | | |
| T-P4-05 | Student dashboard and library | todo | T-P4-04 | ui-builder, implementer, test-writer | M | normal | | |
| T-P4-06 | Protected PDFs, resources and previews | in_progress | T-P4-03, T-P3-08 | implementer, ui-builder, test-writer | S | high | | Private download authorization; player resource list and G7 pending |
| T-P4-07 | Watermark, session and device limits | todo | T-P4-03, T-P1-02 | implementer, ui-builder, test-writer | M | normal | | |
| T-P4-08 | Parent dashboard | todo | T-P4-05, T-P1-06 | ui-builder, implementer, test-writer | M | high | | |
| T-P4-09 | Layered access: sequence and drip with reason codes | todo | T-P4-01, T-P4-04, T-P3-04 | implementer, ui-builder, test-writer | M | high | | |
| T-P4-10 | Instructor learner insights and view-as-learner preview | todo | T-P4-04, T-P4-09 | implementer, ui-builder, test-writer | M | normal | | |
| T-P4-MG | Milestone gate P4 | todo | T-P4-01..10 | orchestrator | S | normal | | |
| T-P5-01 | Cohorts, capacity and waitlist | todo | T-P4-01, T-P3-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P5-02 | Live session scheduling | todo | T-P5-01, T-P3-04 | implementer, ui-builder, test-writer | M | normal | | |
| T-P5-03 | Protected join links and learner live UI | todo | T-P5-02, T-P4-05 | implementer, ui-builder, test-writer | S | high | | |
| T-P5-04 | Notification base, reminders and lifecycle notices | todo | T-P5-02 | implementer, test-writer, chore | M | normal | | |
| T-P5-05 | Attendance and replay | todo | T-P5-03 | implementer, ui-builder, test-writer | S | normal | | |
| T-P5-MG | Milestone gate P5 | todo | T-P5-01..05 | orchestrator | S | normal | | |
| T-P6-01 | Quiz authoring and question bank | todo | T-P3-11 | implementer, ui-builder, test-writer | M | normal | | |
| T-P6-02 | Quiz runtime and attempts | todo | T-P6-01, T-P4-04 | implementer, ui-builder, test-writer | L | high | | |
| T-P6-03 | Assignments and submissions | todo | T-P3-11, T-P4-03 | implementer, ui-builder, test-writer | M | high | | |
| T-P6-04 | Review and grading queue | todo | T-P6-03, T-P6-02, T-P5-04 | implementer, ui-builder, test-writer | M | high | | |
| T-P6-05 | Completion engine | todo | T-P6-02, T-P6-04, T-P5-05 | implementer, test-writer | M | high | | |
| T-P6-06 | Certificates and verification | todo | T-P6-05, T-P1-02 | implementer, ui-builder, test-writer | M | high | | |
| T-P6-MG | Milestone gate P6 | todo | T-P6-01..06 | orchestrator | S | normal | | |
| T-P7-01 | Assistant core pipeline and output guard | todo | T-P3-09, T-P3-10, T-P4-03 | implementer, test-writer | L | high | | |
| T-P7-02 | Input guards, quiz guard, cache and quotas | todo | T-P7-01, T-P6-01 | implementer, test-writer | M | high | | |
| T-P7-03 | Student assistant panel UI | todo | T-P7-01 | ui-builder, implementer, test-writer, chore | M | high | | |
| T-P7-04 | Feedback, help requests and instructor/admin queue | todo | T-P7-03, T-P5-04 | implementer, ui-builder, test-writer | L | high | | |
| T-P7-05 | Evaluation harness, CI gate and instructor test console | todo | T-P7-02 | implementer, test-writer, chore | M | high | | |
| T-P7-06 | Admin controls, readiness, usage reports, insights and resilience | todo | T-P7-04, T-P7-05 | implementer, ui-builder, test-writer | M | high | | |
| T-P7-07 | Provider selection, privacy gate and pilot | todo | T-P7-06 | orchestrator, implementer | M | high | | |
| T-P7-MG | Milestone gate P7 | todo | T-P7-01..06 (T-P7-07 may be blocked) | orchestrator | S | high | | |
| T-P8-01 | Pricing and order engine | todo | T-P4-01, T-P3-03, T-P0-06 | implementer, test-writer | M | high | | |
| T-P8-02 | PaymentGateway contract and Paymob checkout | todo | T-P8-01 | implementer, test-writer, chore | M | high | | |
| T-P8-03 | Paymob webhook verification and idempotent fulfillment | todo | T-P8-02 | implementer, test-writer | M | high | | |
| T-P8-04 | Instapay/manual payment proofs | todo | T-P8-01, T-P4-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P8-05 | Coupons | todo | T-P8-01 | implementer, test-writer | S | high | | |
| T-P8-06 | Refunds, payment history, receipts and e-receipt adapter | todo | T-P8-03, T-P8-04 | implementer, ui-builder, test-writer | M | high | | |
| T-P8-07 | Bundles and allocation | todo | T-P8-03 | implementer, ui-builder, test-writer | M | high | | |
| T-P8-08 | Subscription plans, periods and manual renewal | todo | T-P8-03, T-P5-04 | implementer, ui-builder, test-writer | L | high | | |
| T-P8-09 | Commission rules and instructor ledger | todo | T-P8-03, T-P8-07, T-P8-08 | implementer, test-writer | L | high | | |
| T-P8-10 | Statements and settlements | todo | T-P8-09 | implementer, ui-builder, test-writer | M | high | | |
| T-P8-11 | Daily Paymob reconciliation | todo | T-P8-03 | implementer, test-writer | S | high | | |
| T-P8-12 | Checkout UI | todo | T-P8-02, T-P8-04, T-P8-05, T-P5-01, T-P1-10 | ui-builder, implementer, test-writer | M | high | | |
| T-P8-13 | Saudi market profile (disabled) | todo | T-P8-01 | implementer, test-writer | S | high | | |
| T-P8-MG | Milestone gate P8 | todo | T-P8-01..13 | orchestrator | S | high | | |
| T-P9-01 | Instructor applications, agreements and payout profiles | todo | T-P1-08, T-P3-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P9-02 | Wishlist and moderated reviews | todo | T-P2-04, T-P4-01 | implementer, ui-builder, test-writer | S | normal | | |
| T-P9-03 | Consent banner and marketing tags | todo | T-P2-03 | implementer, ui-builder, test-writer | S | normal | | |
| T-P9-04 | WhatsApp/SMS channel adapter | todo | T-P5-04 | implementer, test-writer | S | normal | | |
| T-P9-05 | Notification templates, preferences and announcements | todo | T-P5-04, T-P7-04, T-P8-03 | implementer, chore, test-writer | M | normal | | |
| T-P9-06 | Reports and CSV exports | todo | T-P8-10, T-P7-06, T-P6-06 | implementer, ui-builder, test-writer | L | high | | |
| T-P9-07 | Role dashboards with real KPIs | todo | T-P9-06 | ui-builder, implementer, test-writer | M | normal | | |
| T-P9-08 | Privacy rights, retention and staging anonymizer | todo | T-P8-06, T-P7-04 | implementer, test-writer | M | high | | |
| T-P9-09 | Settings module and legacy sidebar parity | todo | T-P9-01..08 | implementer, ui-builder, chore | M | normal | | |
| T-P9-10 | Learner 360 and support view-as | todo | T-P9-06, T-P4-10 | implementer, ui-builder, test-writer | M | high | | |
| T-P9-11 | Demo dataset and demo reset | todo | T-P9-09 | chore, implementer | S | normal | | |
| T-P9-MG | Milestone gate P9 | todo | T-P9-01..11 | orchestrator | S | normal | | |
| T-P10-01 | Legacy source inventory | todo | T-P0-01, T-P3-MG | orchestrator, implementer | M | high | | |
| T-P10-02 | Source-to-target mapping spec | todo | T-P10-01 | orchestrator, chore | M | high | | |
| T-P10-03 | Migration framework | todo | T-P10-02 | implementer, test-writer | M | high | | |
| T-P10-04 | Import taxonomy, courses, translations, SEO and URL redirects | todo | T-P10-03 | implementer, test-writer | M | high | | |
| T-P10-05 | Import curriculum, media, PDFs and resources | todo | T-P10-04, T-P3-09 | implementer, test-writer | L | high | | |
| T-P10-06 | Import quizzes, assignments, live references and certificates | todo | T-P10-05, T-P6-MG | implementer, test-writer | M | high | | |
| T-P10-07 | Learner and business data import | todo | T-P10-06, T-P8-MG | implementer, test-writer | L | high | | |
| T-P10-08 | Staging rehearsal, delta procedure and freeze plan | todo | T-P10-07 | orchestrator, implementer | M | high | | |
| T-P10-MG | Milestone gate P10 | todo | T-P10-01..08 | orchestrator | S | high | | |
| T-P11-01 | Full regression and security review | todo | T-P9-MG, T-P10-MG, T-P7-MG, T-P8-MG | orchestrator, reviewer, test-writer | L | high | | |
| T-P11-02 | Performance and capacity | todo | T-P11-01 | implementer, orchestrator | M | normal | | |
| T-P11-03 | Backups, restore drill and monitoring | todo | T-P11-01 | implementer, orchestrator | M | high | | |
| T-P11-04 | Production configuration and email deliverability | todo | T-P11-03 | implementer, orchestrator | S | high | | |
| T-P11-05 | Training guides | todo | T-P11-01 | chore, ui-builder | M | normal | | |
| T-P11-06 | Cutover runbook and rehearsal | todo | T-P10-08, T-P11-04 | orchestrator | M | high | | |
| T-P11-07 | Production cutover and handover | todo | T-P11-06, T-P11-05 | orchestrator | M | high | | |
| T-P11-MG | Milestone gate P11 | todo | T-P11-01..07 | orchestrator | S | high | | |
| T-P12-01 | Saudi activation readiness | todo | T-P8-13, T-P11-MG | orchestrator, implementer | M | high | | |
| T-P12-MG | Milestone gate P12 | todo | T-P12-01 | orchestrator | S | high | | |

## Task logs

### T-P4-01 — Preview access review

- Ready: an inactive learner must be denied item and file access even when the lesson is a preview (A2, I01, PRD §10). Reuse EntitlementService, LessonAccessChecker and EntitlementServiceTest. At-risk entry points: player, private files and curriculum query.
- AI: hook — AI eligibility consumes EntitlementService; replace AI stub. This correction preserves the existing LessonAccessChecker binding and assistant eligibility integration. No provider, UI or schema changes.
- Evidence: extend A2 to cover suspended and deactivated accounts with a preview lesson and a current entitlement. Run the Entitlements, Learning and Invariants suites and requirement coverage before independent review.
- Result: both inactive preview cases failed before the fix. Entitlements, Learning and Invariants passed 47 tests / 223 assertions with two existing TODOs using the English fixture locale. Requirement coverage, Pint and independent scoped QA review passed. Full T-P4-01 closure remains in review. The archived and unflagged attachment branches need separate T-P4-06 verification.
- Worktree sync: base advanced to `7df6c04` (T-P4-03) from the local development branch. The previously reviewed P1 edits and captures are present in that history; the missing row geometry thresholds were restored in `ac60a95` and the actual 1568px browser case passed 36 assertions. The earlier isolated snapshot remains backed up.

### T-P4-06 — Protected PDFs, resources and previews

- Ready for the authorization slice after T-P4-03 and T-P3-08 implementations in review. PRD §9.7 and Checklist §6 require entitled download and explicit preview flags. Reuse SignedFileUrl, PrivateFileAccess, PrivateFileController and EntitlementService. No new tables.
- AI: none — add nothing AI-related. Preserve assistant eligibility behavior except where its existing access dependency requires the authoritative entitlement.
- A1/A2 evidence plan: use the real entitlement binding with a published preview lesson to distinguish a flagged resource from unflagged materials and curriculum PDF; then exercise a same-account grant, forwarding and expiry in the signed route. Preserve the T-P3-08 domain tests.
- UI follows the lesson toolbar and existing authorized download primitives. Match the 07/17/06 task references when extending the resource list, with AR/EN viewport captures and visible state review before G7 closure.
- Authorization slice: both unflagged preview-attachment issuance cases failed before the fix. The real file checker now requires an active course entitlement for private attachments in addition to item access; explicitly public resources retain the existing preview path. Signed forwarding/expiry acceptance passed. PrivateFiles, SubtitleTracks, EntitlementService and AssistantEligibility passed 83 tests / 338 assertions with one existing TODO. Scoped Pint, Larastan and G10 passed. The player still needs the approved PDF link and preview checks for external resources. T-P4-04 landed separately at `5244e00` on the development branch while this worktree was in use; integrate that revision before editing the player.

### T-P2-01 — Categories and disciplines

- PO override 2026-11-17: T-P2-01 may proceed with T-P1-08 in review; visual score gap deferred, T-P1-08 not closed.
- PO loop override 2026-11-17: `review` dependencies count for starting (T-P1-07, T-P1-08, T-P1-10 and P2 tasks moved to review). Capture required AR/EN 390/1440 screenshots; visual deferred per PO. Set completed tasks to `review`; run T-P2-MG only after T-P2-01..09 reach `review`.
- Outcome: bilingual, ordered admin taxonomy for PRD §5.1, YG-WEB-003 and YG-GAP-SRCH-001.
- Reuse verified in source: `HasSearchText`, `TranslatableTabs`, and `WorkspaceListRecords`.
- UI: native workspace list and create/edit dialogs with AR/EN fields, file upload, empty/loading states and inline validation. Public pages belong to T-P2-04. AI: none — add nothing AI-related.
- Data: self-referencing categories with indexed parent/order, unique AR and EN slug columns, display fields and derived search text.
- Stages: focused acceptance tests, then migration/model/action and seeder, then panel resource and translations. A1 hierarchy/order, A2 locale slug uniqueness, and A3 normalized search text are exercised by tagged tests. Visual deferred per PO.
- S0 RED: `php -d memory_limit=-1 vendor/bin/pest tests/Feature/Filament/CategoriesTest.php --compact` exited 1 (6 failed, 2 assertions). Category routes returned 404 and the category model/seeder are absent.
- PO shared-file ruling applied: backed up and restored only `CHANGELOG.md`, `docs/TRACEABILITY_MATRIX.md` and `agents/ORCHESTRATOR.md` to HEAD (`agents/logs/stale-shared-files-backup-2026-11-17.patch`). Implementation resumed.
- A1–A3: `CategoriesTest.php` 6 passed / 65 assertions, covering hierarchy and ordering, localized slug constraints, normalized bilingual search, editable seeds and access control. G1 Pint, G2 Larastan, G4 architecture (6/26), G6 translation/RTL/audit/build/isolated SQLite migration and seed, G10 requirement coverage and G11 local doctor all passed. G3 non-browser suite: 313 passed / 1708 assertions with 1 pre-existing I02 TODO (G5: 3 passed / 9 assertions, same TODO). Local gate output: `agents/logs/T-P2-01/local-gates.txt`.
- G7: `CategoriesBrowserTest.php` 8 passed / 48 assertions with axe, direction and overflow checks. AR/EN × 390/1440 × light/dark captures in `tests/Browser/screenshots/T-P2-01/`; visual deferred per PO. G8/G9 N/A. Independent review remains pending.
- G3 coverage rerun: 313 passed / 1708 assertions, 1 existing TODO, 92.6% total. Module coverage verifier exited 0 (threshold modules pending implementation). `agents/logs/T-P2-01/coverage.txt`.
- Additional full-browser run in an isolated snapshot exited 1 (13 failed, 161 warnings, 1178 assertions) in the P1 family, staff and 2FA suites. The task-scoped browser gate passed. `agents/logs/T-P2-01/full-browser-suite.txt`.

<!-- Orchestrator appends per task: plan, subtasks (agent/model), review verdicts, criteria→evidence map, gate results. -->

### T-P0-01 — Access and environment inventory (2026-11-16)
**DoR:** Outcome: verified inventory, every fact verified/UNKNOWN with source (M0.1, DMC §4, O-001..O-003, Checklist §1). Reuse: n/a (doc only). AI impact: `ai: none` — nothing AI-related added. UI: none. Data: none. Subtasks: orchestrator only (chore merged; doc task). Tests: n/a (no code); evidence = command outputs.
**Evidence map:**
- A1 inventory exists, no guesses → `docs/ENVIRONMENT_INVENTORY.md`; raw outputs `agents/logs/T-P0-01/{local_runtime,local_services,legacy_public_observation}.txt`.
- A2 blocking credentials → Blockers B-01..B-09 above + Notes.
- A3 no legacy change → only public DNS, 3 HEAD, 1 GET, 1 TLS handshake; stopped on operator mismatch (inventory §7).
**Gates:** none required (task `gates: —`). **Wave:** prd-auditor NOT RUN this iteration. **Commit:** not possible (no git repo, B-02).
**Status:** `review` — owner must answer B-01 before `done`.

### T-P0-02 — Stack compatibility and architecture decision record (2026-11-16)
**Selection:** T-P0-01 is `review` waiting on owner (B-01); its `blocked-if` allows local tasks T-P0-02..06 to proceed. T-P0-02 is doc-only.
**DoR:**
1. Outcome: ADR-001 with exact, mutually compatible versions for every Conventions §2 package + target runtime (R-001, R-002, R-026, O-004; DMC §5).
2. Reuse: Conventions §2 table, ED-011 (vectors), ED-021 (monitoring), AI spec §6/§17 (OCR, SSE); inventory facts from T-P0-01.
3. AI impact: `ai: hook` — confirm compatibility of PDF text extraction, OCR and SSE libraries; record in ADR. No code.
4. UI: none. 5. Data: none.
6. Subtasks: orchestrator only (chore merged): S-a fetch Packagist/npm metadata → `agents/logs/T-P0-02/`; S-b write `docs/adr/ADR-001-stack.md`; S-c update Decision Register R-001/R-002/R-004 status + Conventions §2.
7. Test plan: n/a (no code). Evidence = Packagist `require` constraints per package saved in logs; A1/A2 checked by prd-auditor.
DB choice: host and legacy DB are UNKNOWN (B-01, B-05, B-06) → choose reversibly per task step 2.
**Evidence map:**
- A1 exact versions + compatibility evidence → `docs/adr/ADR-001-stack.md` §2–§6; Packagist/npm/source evidence in `agents/logs/T-P0-02/`.
- A2 replacements recorded → ADR §7 (PHP floor 8.3→8.4, Dusk→Pest browser, tesseract wrapper rejected, no AI SDK).
- Step 4 → Decision Register R-001/R-002 ACCEPTED, R-004 PROVISIONAL (MySQL 8.4); Conventions §2 updated; superseded pointers added to DMC §5.2 and Arch §2.
**Wave:** prd-auditor PASS (round 1; 28/28 version claims matched; 6 minor notes applied: Livewire ^4.4.8, Linux "recommended", non-Packagist §2 rows, SSE POST/fetch + endStreamWith, pdftoppm + Tesseract unverified, PHP 8.3 fallback gaps). No app, so `app:req-coverage` n/a.
**Gates:** none required (`gates: —`). **Commit:** not possible (no git repo, B-02).
**Status:** `review` — `done` once T-P0-01 is done (waits on B-01) and the work can be committed.

### T-P0-05 — Module map and initial domain/database map (2026-11-16)
**Selection:** user asked to run T-P0-05. Dep T-P0-02 is `review` (not `done`) only because T-P0-01 waits on B-01; ADR-001 content is final, so T-P0-05 proceeds (doc-only).
**DoR:**
1. Outcome: ADR-002 module map (modules, owned tables, public Actions/Queries/Events), Mermaid ERD for P1–P4, ADR-003 git conventions (Arch §3–§5, §7, §10, §11; Conventions §3; AI spec §16, §23; Checklist §1).
2. Reuse: Conventions §3 folders as modules, with Arch §3 domains and DMC §5.3 names mapped onto them (no conflict: different grain); ED-002/004/006/007/026 model changes; ORCHESTRATOR §8 branch/commit rules.
3. AI impact: `ai: hook` — AI module + AI spec §16 tables placed in module map and ERD (later-phase marked). No code.
4. UI: none. 5. Data: none created (no migrations; out of scope).
6. Subtasks: orchestrator only (chore merged): S-a ADR-002; S-b `docs/erd/core.md`; S-c ADR-003; S-d coverage self-check script (every Arch §4/§10.1 table → exactly one module).
7. Test plan: no code. A1 → coverage check output in `agents/logs/T-P0-05/`; A2 → Mermaid syntax check if a renderer is available, else prd-auditor review.
**Wave cycle 1 (2026-11-16):** prd-auditor **FAIL**. A1 holds (coverage.txt PASS, auditor confirmed independently). **Rework open (none applied yet):**
- ADR-002 §3: AI §16 tables (except help_requests*) → phase P3 (T-P3-10), not P7/T-P3-09; ERD: draw them in §3, not as P7 stubs.
- Add tables named by task specs: `age_bands` (T-P1-07), `blog_categories`, `tags` (T-P2-07), `notification_dispatches` (T-P5-04); sweep P*.md for others.
- ERD: `courses.min_age/max_age` (not `age_suitability`); drop invented `lessons.course_id/media_asset_id`, `user_sessions.user_device_id`; mark users email/phone UK proposed; `notification_dispatches.dedupe_key` proposed; reword blanket FK claim; add `settlement_items` unique.
- Citations: OcrProvider → Arch §10.3; lesson_media_assets supersession → Arch §10.1/§11.1; drop PdfRenderer "used by"; events "assigned here"; consistent "inferred" marks.
- ADR-003: env mapping "proposed"; status "Accepted; proposed items pending".
Then re-run prd-auditor (cycle 2 of 3).

### T-P2-02 — CMS pages and homepage blocks

- Outcome: content editors manage bilingual custom pages and ordered homepage blocks (YG-WEB-002, YG-WEB-005, PRD §19.2). T-P1-08 and T-P1-10 meet the PO review exception.
- Reuse `content.page.manage`, `TranslatableTabs`, `WorkspaceListRecords`, Laravel signed URLs and `x-layouts.base`. AI: none — add nothing AI-related.
- UI: admin list plus create/edit dialogs, edit then preview for drafts. Public pages render escaped CMS content; existing layout/typography and AR/EN keys; a missing/unpublished page uses the branded 404. Full home design belongs to T-P2-03.
- Data: `pages` with unique slug and publication state, translatable title/body/SEO, and template. `homepage_blocks` with typed bilingual content, visibility and stable order. Seed six draft placeholders and leave legal copy pending owner input.
- S0 feature tests → S1/S2 migration, models, actions and seeder → S3/S4 signed public/preview routes and native admin resources → S5 AR/EN copy. A1 test valid/invalid/expired preview and editor access plus public publication state; A2 verify root homepage visibility and order. Capture AR/EN desktop/mobile screenshots; visual deferred per PO.
- S0 RED: `CmsPagesTest.php` exited 1 (3 failed, 3 assertions): missing admin route/seeder and placeholder redirect at `/`. GREEN: 3 passed / 57 assertions, including publish, scoped preview, escaping, protected seed copy, and reorder/hide through the admin form.
- G1 Pint and G2 Larastan passed. G3 full non-browser: 316 passed / 1767 assertions, 1 existing I02 TODO, 92.9% coverage; threshold-module verifier exited 0. G4 architecture: 6/28. G5 invariants: 3/9 with the same TODO. G6 AR/EN/RTL, audit, build, isolated SQLite migrate+seed, G10 coverage and G11 doctor passed. Logs: `agents/logs/T-P2-02/`.
- G7: 14 browser tests / 142 assertions, including keyboard access for empty CMS and category tables. 24 captures in `tests/Browser/screenshots/T-P2-02/`; visual deferred per PO. G8/G9 N/A. Independent review pending. Legal placeholders remain drafts pending owner copy.

### T-P2-03 — Brand public shell and homepage UI

- Blocked 2026-11-17 on O-027. A1 and D-002 require the existing Young Go logo and brand. `docs/brand/BRAND_SHEET.md` records only a provisional wordmark and palette. Required input: approved existing logo artwork and usage rules, verified colors and font references or owner acceptance of the current provisional kit as the project brand.
- T-P2-08 remains unstarted because T-P2-03 has not reached `review`. Continue with the independently ready T-P3-01.

### T-P3-01 — Course domain, ownership and course list

- Outcome: admin and instructors can see a permission-scoped course directory with accurate summaries (PRD §9.1, §9.3, §9.4, D-007, Roadmap M3.1).
- Reuse `Category`, `HasSearchText`, `ArabicNormalizer`, `AgeRange`, `BasePolicy`, `WorkspaceListRecords`, `ExportCsvAction`, and existing role catalog/Pennant flag. AI: hook — course `ai_enabled` default on, effective only when global flag on.
- UI: native admin/instructor table with summaries, search/filters, read-only details and permission-gated feature action. Preserve built-in empty/loading/error behavior. Wizard, public details, curriculum, enrollment counts and checkout depend on later tasks.
- Data: `courses` with bilingual title/short/body and unique locale slugs, category, age and ownership fields, publication enum, access-mode placeholder, featured and AI flags. `course_instructors` with unique course/instructor key and role/scope.
- S0 tests for A1 role-scoped list and 404, A2 independent publishing denial, A3 real counters, and course AI hook. Then migration/enums/model, policies/queries/actions, and resource/screenshots/AR/EN keys. Visual deferred per PO.
- S0 RED: 3 failures from absent course model/status. GREEN: `I02_CourseScopeTest.php` 3/24, including out-of-scope direct 404, view-only assignment, normalized query scope, independent publish denial, academy publish, counters and global/local AI flag.
- G1 Pint, G2 Larastan, G6 translations/RTL/audit/build/migrate+seed, G10 requirement coverage and G11 doctor passed. G3 full non-browser: 319 passed / 1792 assertions, 93.1% coverage, 1 pre-existing I02 TODO; module coverage verifier exited 0. G4 architecture 6/29. G5 invariants 6/33 with the same TODO. `agents/logs/T-P3-01/`.
- G7 browser: 16/192, 32 screenshots for list/details in both panels and locales at 390/1440, light/dark. `tests/Browser/screenshots/T-P3-01/`. Visual deferred per PO. G8/G9 N/A. Independent PRD, QA and high-risk reviewer verdicts pending.

### T-P2-04 — Catalog, search and filters

- Outcome: make published courses discoverable with YG-WEB-003 and YG-GAP-SRCH-001. The owner pulled T-P3-01 forward for this task; it is now in `review`.
- Reuse `Course`, `Category`, `ArabicNormalizer`, `AgeBandResolver`, `ActingLearner`, public layout and existing UI primitives. AI: none — add nothing AI-related.
- UI: search, URL-backed filters, mobile expandable filter panel, result cards and pagination, bilingual empty state. Available filters: category/subcategory, manual or learner/configured age band, level, format, content language, instructor and access type. Newest order uses persisted timestamps.
- No schema changes. S0 route tests for A1 normalized matches and A2 combined filters/public visibility. Then query/controller/route, course card and filters, AR/EN browser captures including empty state. Visual deferred per PO.
- Missing data sources for the full task: prices per market (T-P3-03/T-P8-01), popularity metric/enrollment data (T-P4-02), and live availability/capacity (T-P5-01). No guessed amounts, counts or capacity. Deliver the independently testable catalog slice; full A2 remains open.
- Search uses the existing status index to narrow candidates to published courses. Portable substring LIKE scans the normalized text of those rows; evaluate an Arabic-aware index against catalog volume and the agreed search/performance budget before changing semantics.
- S0 RED: `PublicCatalogTest.php` exited 1 (3 failures on missing /courses). GREEN: 3/27 for Arabic substitutions, published-only combined filters, manual/configured age, timestamp order and pagination.
- G1 Pint, G2 Larastan, G6 AR/EN/RTL/audit/build/isolated SQLite migration and seed, G10 requirement coverage and G11 doctor passed for this slice. G3 full non-browser: 322/1819, 93.3% coverage, 1 existing I02 TODO; module coverage verifier exited 0. G4 architecture 6/29. G5 invariants 6/33 with the same TODO. `agents/logs/T-P2-04/`.
- G7 browser: 4/36, 10 captures for results, mobile filters and empty state in AR/EN at 390/1440. `tests/Browser/screenshots/T-P2-04/`. Visual deferred per PO. G8/G9 N/A. Full task stays blocked; G10 tags do not close the missing A2 behavior.

## Notes
- **Source conflict (2026-11-16, T-P0-02):** DMC §5.2 and Arch §2 say "PHP 8.3+"; Pest 5 and current Spatie majors need PHP 8.4. Followed ADR-001 (task + Conventions §2 mandate); pointers added in both docs.
- **Source conflict (2026-11-16, T-P0-01):** owner-stated domain `youngschool.com` (PROJECT_STATE, DMC §4, O-001) vs public observation (Learning Care Group childcare site, TLS org "Learning Care Group, Inc."). Followed observation: domain marked unconfirmed. Affects T-P0-04 (use O-027 placeholders), T-P10-01, domain-retention direction.
- **Brief vs repo:** brief says branch `develop`; working directory is not a git repository. Brief names `agents/ANTI_HALLUCINATION_PROTOCOL.md`; file does not exist.
- **Blocking credentials (A2):** legacy hostname confirmation; repo remote/branch; staging/prod host+SSH; legacy admin/DB/storage/video/live/Instapay access; Paymob test/live keys + seller details; AI provider; mail provider. Local: Composer, DB, Redis.
- Gate-blocked by design until approvals: T-P7-07 (O-014/O-015), T-P8 live activation (Paymob credentials, O-023), T-P10-01 (O-003), T-P10-07 (O-018), T-P11-07 (owner cutover approval), T-P12-01 (Saudi readiness).

### T-P0-03 — Bootstrap execution (2026-11-16)
- DoR outcome: bootable skeleton and checks proving A1-A5, YG-GAP-CODE-001, YG-GAP-QA-001/002.
- Reuse: Laravel skeleton 13.11.0, approved ADR001 dependencies; registry has no existing hygiene commands.
- AI: hook — pending I08/I09 documented, Http::preventStrayRequests in base TestCase; no AI feature.
- UI: none; data: framework migrations only, no domain schema or legacy access.
- Split: root bootstrap/config/architecture/CI/docs; quality_commands agent owns two console commands and its feature test; independent reviewer checks P005 docs.
- Tests: A1 health response; A3 physical vs logical utilities; A4 asymmetric locale keys; A5 forbidden import negative fixture; A2 format/static/test/build and CI (remote evidence pending).
- Readiness ruling: T-P0-01 explicitly permits local-only T-P0-02..06 while access is UNKNOWN. Prior review statuses are retained; no claim of full prerequisite acceptance.
- Local bootstrap uses SQLite/file cache/sync queue only; Redis/Horizon and MySQL require Linux integration verification. No existing database will be reset.
- Missing ANTI_HALLUCINATION_PROTOCOL remains B09; existing AGENTS evidence and PRD-lock rules apply.


**Local evidence:** composer QA exit 0, 25 tests and 45 assertions (agents/logs/T-P0-03-qa.txt); /up HTTP 200 in the application harness; translation and RTL commands exit 0; Vite build passed; Composer audit found no security advisories; three framework migrations and seeding ran on isolated in-memory SQLite. A3/A4 red then green (17 focused tests); A5 negative HTTP fixture failed the dependency rule as expected.

**Open acceptance:** GitHub Actions workflow is local only, MySQL/Redis/Horizon runtime, browser journeys, and independent end-to-end verdict remain unverified. Business invariants and coverage tooling remain pending per owning tasks.

**T-P0-05 rework:** corrected AI schema vs ingestion ownership, four missing task tables, age fields, unsupported FKs and dedupe constraints. Five diagrams rendered against the hash in agents/logs/T-P0-05/verification.txt. Independent prd-auditor PASS for document scope after rework (116 unique owner rows, five valid SVGs). Final status remains review pending prerequisite sign-off and scoped commit.

T-P0-03 acceptance audit: A1 health endpoint passed in the harness; fresh-clone replay remains open. A2 local QA passed, but remote CI and G3 parallel/coverage enforcement remain open. A3/A4 focused tests passed; independent verifier still required. A5 verified through a forbidden HTTP fixture. G5 is pending feature implementation. No merge or deployment evidence.

### Next task selection — T-P0-04
- T-P0-03 remains in review. CI now collects parallel Pest coverage with Xdebug; the GitHub result and module threshold enforcement remain open. Do not lower documented module targets.
- T-P0-04 is next by phase/ID once T-P0-03 is accepted. References: Design System sections 2, 7 and 8, D-002, O-027 and YG-GAP-UI-001.
- The verified legacy hostname is still unavailable. Use the task's O-027 fallback for provisional colors, fonts and logo treatment; keep official asset fields unknown until the owner confirms them.
- Scope: shared Tailwind/Filament tokens, contrast checks, brand sheet and the gated token preview. Preview must cover AR/EN and light/dark; AI scope is limited to panel and readiness badge tokens.

- A1 local setup replay passed from an isolated copy of current application sources and lockfiles, with fresh dependencies, app key, SQLite database, and build output. Composer install, migrate --seed, npm ci, Vite build, and composer qa passed (25 tests / 45 assertions, including /up). The initial fixture omitted .gitignore, so Tailwind scanned dependencies; the build passed after copying the repository exclusions. Fresh Git checkout and hosted CI remain unverified.

- Hosted CI evidence supersedes the earlier pending CI notes. Quality run https://github.com/ibrahimmaher2503-source/young-go-lms/actions/runs/43880890076 passed on feat/T-P0-03-bootstrap at 96140ad9021609a2fc51d03a0b0cd037d303a3e3. Fresh Linux checkout, MySQL migrate/seed, Pint, Larastan, parallel Pest (25 tests, 45 assertions, 83.6% overall coverage), AR/EN parity, RTL, Composer audit and Vite build passed. Redis service became healthy; Horizon worker execution and per-module thresholds remain outside this evidence. No merge.

- Horizon/Redis acceptance evidence: Quality run https://github.com/ibrahimmaher2503-source/young-go-lms/actions/runs/43882611107 passed on feat/T-P0-03-bootstrap at c64dfd8. Horizon consumed a queued Redis job and the workflow verified its result in Redis cache. The full bootstrap workflow also passed. Per-module coverage thresholds are not yet measurable: Entitlements, Commerce, InstructorFinance and AI have no code in this checkout.


### T-P0-06 local foundation
P00 permits local-only tasks through T-P0-06 while legacy access is unknown. Hosted P0-03 checks are green; independent foundation acceptance remains in progress. Implementing the documented Arabic normalizer, search text trait, money, market, and UTC date primitives. AI hook is reuse of normalizer only. Tests cover P00 A1–A3. Registry updates follow implementation verification.


### Foundation acceptance continuation
- Independent foundation auditor verified hosted CI on c64dfd8 and checked T-P0-01..03 acceptance. Repository/runtime inventory and stack ADR are current; legacy hostname and production access remain UNKNOWN and apply to legacy-dependent tasks.
- Closed the App-only env guard gap. The architecture test scans first-party PHP outside config. A temporary route containing a fully qualified env call failed the guard as expected; clean architecture suite passed (5 tests, 20 assertions).
- T-P0-07 owns per-module thresholds and wiring G10/G11. Bootstrap coverage and CI evidence are available now. Business invariant suites remain with owning feature tasks.
- T-P0-04 started using O-027 provisional identity with a gated AR/EN light/dark preview, contrast checks, shared tokens and AI readiness states. No schema changes.

Closure supersedes earlier review and blocker notes for T-P0-01/02/03/05. Legacy access remains UNKNOWN and is tracked for migration. T-P0-03 closure covers the named bootstrap acceptance plus the standalone architecture follow-up; P0-07 owns module threshold enforcement and G10/G11 tooling.

T-P0-07 started with the requirement coverage command and isolated A3 acceptance fixtures. The other T-P0-07 services and CI gates still require implementation.

### G10 acceptance evidence
| Task criterion | Evidence |
| --- | --- |
| T-P0-03:A2 | evidence: GitHub Actions run 43882611107 passed all bootstrap steps, including parallel Pest coverage and Horizon. |
| T-P0-06:A4 | evidence: docs/REUSE_REGISTRY.md lists the implemented primitives and their paths. |

| T-P0-04:A1 | evidence: resources/css/tokens.css, CSS and view hex audit, and both compiled Vite stylesheets. |
| T-P0-04:A2 | evidence: npm run build passed 34 WCAG AA light/dark contrast pairs. |
| T-P0-04:A3 | evidence: UTF-8 docs/brand/BRAND_SHEET.md lists provisional values and owner sign-off inputs. |
| T-P0-04:D-002 | evidence: O-027 provisional brand sheet records legacy identity verification as pending. |

T-P0-04 review: 47cf45a. Independent UX PASS 4.75/5. Eight exact-size AR/EN light/dark screenshots; browser checks reported no axe violations or horizontal overflow and validated 44px controls. Official assets are still pending owner sign-off under O-027.
T-P0-07 partial implementation: 5cd8e6b adds doctor, scheduler heartbeat, scoped requirement checking, module coverage enforcement and EG/SA phone normalization. Local checks passed. Coverage gate fixture accepted 90% and rejected 80% or missing file metrics. Hosted run 43886039522 is the verification target. Flags/settings and observability are separate uncommitted slices.
T-P1-01 local implementation is in progress, with unprefixed locale negotiation and an ADR for reserved public AR/EN prefixes.

Run 43886757053 passed the live MySQL/Redis/Horizon/doctor checks. Its isolated doctor case inherited the job Redis environment, so commit 411c255 explicitly gives the Pest step array cache, sync queue and SQLite. A targeted local rerun with the same step environment passed (6 tests, 21 assertions).

T-P1-01: 71a3175 + 8937dd2. Independent reviewer confirmed A1–A4 and identified the controller write. Moved persistence into UpdatePreferredLocale and reran localization/architecture with 13 tests, 54 assertions. CI pending for 8937dd2.
T-P0-07: CI 43959579389 passed after isolating Pest environment from live service smoke tests. Added approval/settings, Pulse and local structured logs in 8eb07c6. Independent review confirmed A1–A4 and diagnosed G10 false-green on missing criteria; regression reproduced and fixed, three tests / seven assertions passed, and traceability refreshed. Doctor checks configured mail transport; add enabled provider-specific checks with integrations.


| T-P0-01:A1 | evidence: docs/ENVIRONMENT_INVENTORY.md verified and UNKNOWN entries, refreshed in 02453d3. |
| T-P0-01:A2 | evidence: this file's blocker register and inventory handoff. |
| T-P0-01:A3 | evidence: legacy_public_observation.txt records public DNS/TLS observations only. |
| T-P0-02:A1 | evidence: ADR-001 version/source tables and Composer/npm lockfiles were independently reviewed. |
| T-P0-02:A2 | evidence: ADR-001 rejected alternatives and provisional database decision. |
| T-P0-05:A1 | evidence: independent audit enumerated 116 unique ownership rows in ADR-002. |
| T-P0-05:A2 | evidence: Mermaid CLI rendered five SVGs, with matching source hash and independent PRD review. |

T-P1-02 started in parallel slices: authentication and consent, plus profile updates and certificate-name locking. Both slices use acceptance tests first. Real legal versions, delivery transport and external conditions remain governed by the open decisions.


### P0 milestone evidence
| T-P0-MG:A1 | evidence: T-P0-01 through T-P0-07 done in the task board. Local QA and CI 43960234987 verify bootstrap. The preview evidence records installed tokens, contrast and eight browser variants. |
| T-P0-MG:A2 | evidence: PROJECT_STATE.md and HANDOFF.md continuity records distinguish verified CI/dev state from UNKNOWN deployment, provider, legacy access and recovery evidence. |

Demo: CI checked out the private feature branch fresh, installed Composer/npm lockfiles, configured MySQL plus Redis, ran migrations, Horizon queue smoke, scheduler and doctor, and passed Pest including /up. Local token preview ran with DESIGN_TOKEN_PREVIEW enabled and produced the G7 screenshot set in tests/Browser/screenshots/T-P0-04.
Scope: provisional brand under O-027. Integration-specific provider credential probes and meaningful per-module coverage wait for the owning modules. No production deployment, migration or cutover performed.

P0 G10 phase gate and T-P1-01 G10 passed. P0 milestone is in independent review. T-P1-09 starts against verified P0-04 and P1-01 dependencies, with Blade primitives, gallery, RTL and accessibility evidence as acceptance.

Independent P0-MG audit confirmed G10 and all eight screenshot dimensions. Addressed exact CI SHA and verified governance checklist entries. I14 UTC and I15 Saudi checkout now run in the invariant suite. The scoped run passed 12 tests / 21 assertions and G10 phase P0 passed again. I01-I13 are linked to upcoming feature work and I16 to release evidence. Runnable browser gate remains in progress.


Browser gate update: 35e27ab provides a runnable Pest preview matrix and runs it in GitHub Actions. Implementer recorded 8 passing cases and 32 assertions. A separate overlapping local run encountered Playwright timeouts, so hosted run 43961597365 remains the integration target.

Identity integration: localization now rejects suspended accounts and stale password sessions before saving a preference. Both negative checks were reproduced before the middleware changes. Localization suite passes 10 tests / 40 assertions.

P0 milestone closure: CI 43961974995 passed on fe6d741a82fda8e3609dc26fe182746b512c0e53, including executable browser acceptance and the P0 invariants in Pest. Earlier milestone audit findings were addressed with checklist, exact CI evidence, and the browser matrix. Official assets and hosting remain tracked separately.


| T-P1-09:A2 | evidence: app:check-rtl and app:check-translations passed; npm build validated 34 token contrast pairs. |

Identity and UI integration pushed as 564751d. Local non-browser suite was 99 tests / 325 assertions. Gallery retest after localizing upload labels passed 10 cases / 40 assertions. Pint, Larastan, RTL and translations passed. Luna independently checked the session and mail fixes and all 35 UI primitives. Production mail and legal versions remain configuration prerequisites. Hosted integration result is pending.

Luna xhigh is implementing T-P1-03 RBAC and T-P1-07 age bands in disjoint scopes. These depend on account integration; production feature acceptance remains gated on that CI result.

T-P1-02 and T-P1-09 accepted after exact 564751d CI 43963037649 passed, including the browser gallery and the MySQL, Redis and Horizon checks. Production mail and published legal versions remain deployment configuration. Child accounts and RBAC continue in their own tasks.


T-P1-07 specification check: PRD §5.1 requires administrator-configurable age bands and bars hard-coded brackets. D-005 has no numeric values. AI spec §4 lists Kids, Teens and Adults and marks cutoffs OPEN under O-015. Implement and test the configurable resolver without seeding invented ages. Initial production seed values and activation for minors remain pending O-015.

T-P1-04 backend starts in a disjoint slice while RBAC is under implementation. Viewer permissions and any acceptance claim still depend on T-P1-03. Audit must redact nested sensitive properties and retain actor/acting_for references.

Audit viewer slice: added bilingual read-only AuditLogResource/ListAuditLogs, filtered by actor, subject type, event and date, guarded by audit.view. A3 resource-level permission regression failed before implementation and passed afterward (1 test / 7 assertions). Focused Larastan passed. Actual panel registration and route-level guest/role verification are pending T-P1-08 integration; backend storage is in a separate Luna slice.


Staff panel integration: /admin, /instructor and /finance registered with the shared theme, role-gated panel access and persistent account/session checks. Audit viewer is available only inside the admin panel and only with audit.view. A route-level matrix for all ten roles, guest redirects, real audit-view permission and scoped panel locale return passed (15 tests, 55 assertions). Remaining P1-08 work includes shared Filament blocks/actions, user and role management, visual acceptance and hosted CI.

T-P1-08 A2 groundwork: AssignStaffRoles locks actor/target during changes, requires an active super_admin, protects super_admin membership from the staff screen, and records before/after in the same transaction. Two focused tests (10 assertions) and Larastan passed. Staff users list/dialog is a separate Luna slice.

T-P1-03 independent Luna review: hold on inactive super_admin central Gate bypass, expansion of the authorization dataset, and seed behavior that would restore revoked defaults. Implementer is addressing those findings. I02 object scopes remain downstream feature work.

RBAC integration cbfa9ce replaces Spatie auto Gate registration with a single ordered check, then denies inactive accounts before checking grants. Seeder applies defaults only to newly created roles. Integrated 48 tests / 221 assertions plus G10 T-P1-03 and T-P1-04 passed. Commit pushed with audit, age-band and staff panel slices; hosted verification pending. T-P1-07 retains owner seed ranges open under O-015. T-P1-05 begins test-first locally while hosted checks run.

T-P1-04 redaction review follow-up: additional abbreviated account/card/identity-document keys now redact under the existing structured-field contract. Regression failed before the change; audit suite passed afterward (3 tests / 34 assertions). Retention and scheduled pruning remain T-P9-08 and are not activated.

Hosted CI 43966413798 completed successfully on cbfa9ce. The rerun covered MySQL migration/seed, inactive-account RBAC, audit viewer, shared staff panels, age resolver, and existing browser suites. RBAC re-review was requested following remediation. Age seed cutoffs remain open; staff resource UI work remains active.

CSV staff integration: the users table now exposes ExportCsvAction with separate user.export permission (no default grant to ordinary roles). Livewire test confirmed status/search filters restrict the actual download and a viewer lacking export permission sees no download action. StaffUsers suite passed 6 tests / 35 assertions and Larastan passed. Remaining P1-08 gates include approval action, status editing, visual screenshots and independent review/CI.

## Prior-work closure audit — 2026-11-17

Owner direction: do not start new tasks. Review previous implementation and its requirement coverage; repair gaps in that scope only. No task is promoted to done by this audit's test totals alone.

- Current local composer qa: Pint PASS, Larastan PASS, 166 tests / 643 assertions PASS, plus one explicit I02 TODO scaffold for downstream object-scope implementation. Evidence: agents/logs/closure-qa-after-repairs.txt.
- Existing complete browser suite: 26 tests / 152 assertions PASS before final staff-label repair; affected staff browser suite rerun afterward: 8 tests / 80 assertions PASS. Evidence: agents/logs/closure-browser-current.txt and staff-browser-after-repairs.txt. Sixteen staff screenshots regenerated in tests/Browser/screenshots/T-P1-08; representative AR mobile dark and EN desktop light images visually reviewed.
- Translation parity, RTL and build PASS; 34 token contrast pairs PASS; Composer locked audit exits 0 with no advisories. G10 tag checks PASS for P0-01..07/MG and previously implemented P1-01/02/03/04/07/08/09. Tag coverage is not functional completeness.
- Fixed existing staff Arabic user heading/status labels and reused StatusBadgeColumn. Added bilingual rendered regression (failed before fix; staff suite now 8 tests /45 assertions).
- Fixed ExportCsvAction to reauthorize at execution, protecting permission revocation after rendering. Regression failed before fix; combined staff/export suite 11 tests /55 assertions PASS. Domain writes remain in Actions.
- Local SQLite was absent. Created only if missing; migrate --seed and schedule:run then app:doctor passed applicable checks. Redis intentionally skipped in local sync/file profile. Mail delivery and persistent scheduler service are not verified.
- T-P1-08 remains in_progress: ApproveRejectAction is absent; shared reason+audited-domain-callback contract is not implemented. No new approval feature was started during this audit. Staff screenshot accessibility corrections and Filament asset-publishing setup remain local pending exact-head CI and independent closure review.
- T-P1-07 remains review: no approved numeric seed ranges exist (PRD 5.1, D-005, O-015, AI spec 4). Database-backed computation is implemented; task seed step is still unmet and must not be silently counted complete. AI for minors stays off.
- T-P1-05 implementation is held; no guardian files were created. Its subagent's attempted write was rejected by automatic approval due to usage limit. No alternate write path was attempted.
- New read-only Luna xhigh reviews are checking foundation, identity and panels independently. Their concrete findings must be resolved/recorded before closing prior work. No broad no-gaps claim is made.

Closure evidence update: P0 phase G10 rerun passed (agents/logs/closure-p0-g10.txt). Non-sensitive local seed snapshot captured 16 settings, 10 role names, 64 permissions, zero age bands (unapproved cutoffs), in agents/logs/closure-seed-summary.txt. Doctor and scheduler output captured in closure-doctor.txt and closure-scheduler.txt. These repair missing phase/seed evidence, but are not a historical proof of a previously unrecorded demo or a substitute for the milestone script. Handoff stage and registry/CI inventory stale claims corrected without changing external readiness.

Detailed current closure report: docs/reviews/PRIOR_WORK_CLOSURE_2026-11-17.md. Independent source review confirms remaining ApproveRejectAction, bilingual role/permission labels and role-menu matrix gaps. A roleless self-registration integration gap and milestone evidence consolidation require follow-up; status editing and SMTP coverage are explicitly qualified rather than invented requirements. Current staff browser post-repair evidence is staff-browser-after-repairs.txt (8/80), not the older staff-browser-current.txt.

Closure follow-up: actual learner signup now assigns only the seeded student role in the creation transaction (PRD J01/role catalog), ignoring client staff-role/status input. Observed failing role regression before repair; authentication suite 14/75 passed. Ten-role × three-panel navigation matrix now runs each case in its own application lifecycle (Filament request-scoped NavigationManager); 34/134 passed, including explicit menu presence/absence. UI file drop already existed; new actual DragEvent AR/EN single/multiple/disabled checks passed 2/6. P0 reproducible demo record is docs/evidence/p0-closure/README.md with booted HTTP kernel /up200. Full recheck is running; role/permission display labels, shared approval action, requested HTML helper and audit-query indexes remain recorded gaps. No new task started.

Final local rerun for this audit repair batch: composer qa PASS (Pint/Larastan, 187 tests /738 assertions, plus explicit downstream I02 TODO); all browser suites PASS (28 tests /158 assertions). Logs: closure-qa-final.txt, closure-browser-final.txt. Independent Luna source rechecks accepted signup/drop repairs and closed the A1 navigation evidence gap. Remaining source gaps and external gates are in the closure report; no new task has been started or promoted to done.

Milestone network demo completed: started php artisan serve on 127.0.0.1:8099, real Invoke-WebRequest to /up returned HTTP200 (agents/logs/closure-network-health.txt), then stopped that temporary server. Repair commits 837c127, b737097, 0755842 and closure record2972737 pushed to existing feat/T-P0-03-bootstrap. Exact-head hosted run43973122520 is live; MySQL migrations, Redis/Horizon, scheduler/doctor, formatting/static checks passed before Tests and coverage. Current technical source files are committed; pre-existing local documentation differences remain preserved.


## Owner-approved calm table workspace — 2026-11-17
Outcome: implement approved reference as reusable composition for existing table pages (T-P1-08, D-026, ED-023). Owner explicitly authorized this UI work; earlier all-task goal stays paused. Reuse native Filament ListRecords/Table/Schema and shared tokens; no new business writes or AI features. Luna xhigh scopes: shared base/tests; token/theme CSS. Root owns page integration, bilingual data cards, decision/docs and visual verification. No schema changes. States: existing native loading/empty/error/actions; optional sidebar disappears cleanly. Tests: real scoped summaries, denied roles, native search/filter/export regressions and AR/EN ×390/1440×light/dark axe/screenshots. Spec/plan saved under docs/superpowers.

Owner steering: rejected first visual output; root now personally refines the complete existing UI against the approved image. Shared native login/dashboard, all lists, role/permission labels, forms/dialogs/controls, local avatars and font are included. Future pages must follow VISUAL_ACCEPTANCE.md/visual-contract.json; similar palette or passing tests does not prove visual completion. Reference viewport geometry recorded268px sidebar/70px topbar/844px table/380px aside. Existing69Filament tests passed314assertions; new quick-filter/search/label/surface contracts13passed305. Complete surface/visual checks running; no task promoteddone.


### D-026 current reference fidelity verification — 2026-11-17

Root personally refined all existing staff surfaces against the owner reference. Current composer qa PASS:204tests/1077assertions plus1I02TODO; complete browser PASS:78tests/748assertions; build/34contrastpairs/RTL/translations/G10 PASS. Current geometry and all repaired mobile/modal findings are recorded in docs/reviews/WORKSPACE_VISUAL_REVIEW_2026-11-17.md. Independent source/QA review found no remaining reviewed blocker; visual screenshot review scores4.0, below the formal4.5 threshold, so full visual closure remains review and no business task is promoteddone. Exact raster font and100%pixel equality are not established. Native single-page pagination differs from sample reference. The requested agents/ANTI_HALLUCINATION_PROTOCOL.md is absent in the actual checkout (searched), not falsely counted as read; existing AGENTS/ORCHESTRATOR source/evidence rules were applied. Older unrelated local documentation changes remain preserved.

## Notes — 2026-11-17 (owner resumed task work)
- Owner instructed in chat to continue with the next task and finish fast; the earlier "no new task" pause is lifted.
- T-P1-05 implemented by root directly: guardian_profiles, guardian_child_links (consent placeholders empty, AI for minors stays off), CreateChildAccount (parent role required; super_admin denied), GuardianAccess::parentCanAccessChild (404 via denyAsNotFound, gate `guardian.child.access`), audit guardian.child_created. No route uses `verified` middleware, so email-less children are not blocked. Status `review`: independent reviewer (high risk) not yet run.
- Pint --test reports CRLF line endings in 4 pre-existing locally-modified files (StaffPanelsServiceProvider, lang/*/staff.php, WorkspacePaginationTest); not touched here.
- Local runs need PHP_INI_SCAN_DIR=.tools/php-conf and memory_limit=-1 for Larastan/Pest.
- T-P1-06 implemented by root: guardian_link_codes (HMAC-hashed 8-char codes, 48h, single-use, reissue deletes unused), IssueLinkCode/RedeemLinkCode (pending link)/ConfirmGuardianLink (verified), EnterChildContext (requires Laravel password confirmation; `password.confirm` middleware returns 423) and ExitChildContext, ActingLearner service + ResolveActingLearner web middleware, POST /confirm-password. AuditLogger now adds `acting_for` automatically while in child context (actor stays the parent). O-016 hook: ActingLearner::canViewPrivateLearnerConversations() false in parent context. Status `review`: independent reviewer pending.
- T-P1-08 closure gap closed: shared ApproveRejectAction (Filament) + RecordReviewDecision (Domain) — execution-time authorization recheck, reject reason 10–1000 chars required, owning feature's domain mutation and `{prefix}.approved|rejected` audit in one transaction. Role/permission display labels were already translated (verified in RolePermissionResource). Task moved to `review`; formal visual score (4.0 < 4.5) remains open, so not `done`.
- T-P1-04 review R3 closed: composite indexes (log_name, created_at) and (log_name, event, created_at) on activity_log, with schema test.
| T-P1-10:A2 | evidence: tests/Browser/FamilyFlowsTest.php captures full-page screenshots of login, register, forgot, reset, verify, profile, family, link and 404 in AR/EN at 390 and 1440 plus the child-context strip and dark mode (tests/Browser/screenshots/T-P1-10). |
- T-P1-10 implemented by root: layouts (`x-layouts.base` public/auth/student/parent + minimal for errors, header with logo, locale switcher returning to the same path, account menu, child-context strip), screens (login, register, forgot/reset, verify notice, profile with 4 sections, family with children/add child/redeem code/child-switcher modal, learner link-code page), branded AR/EN 403/404/419/429/500/503 pages, `/home` role router. Pages post through `x-ui.ajax-form` + resources/js/forms.js to the existing JSON endpoints; new `POST /guardian/children` (parent only, authorizes before validating). Register shows an honest "unavailable" notice while legal versions are unset (external gate). `Route::fallback` keeps the 404 page on the selected locale. Known limits: no no-JS form fallback; dark mode follows OS/localStorage on these pages; independent ux-reviewer score not yet run, so `review` not `done`. Pre-existing CRLF files still fail `pint --test`.
| T-P1-11:A5 | evidence: tests/Browser/TwoFactorScreensTest.php captures full-page screenshots of 2FA setup, enabled status, challenge and the child password reset dialog in AR/EN at 390 and 1440 (tests/Browser/screenshots/T-P1-11), each with an axe pass. |


### Owner-requested PRD image reference pack — 2026-11-17

Generated19new raster references with built-in image_gen and saved them with the owner original under docs/design-system/reference-pack-2026-11-17. Canonical manifest, hashes/dimensions, full prompts, offline gallery, PRD page map and shared-component contract are included. Root reviewed outputs and corrected creative-course context, selected navigation, adult recorded-AI paused state, parent privacy and component-board scope. Images are references only; no business task is promoteddone. Earlier pagination refinement remains a separate source change pending its recorded fresh checks.
- T-P1-11 implemented by root: TOTP 2FA (pragmarx/google2fa + SVG QR, secret `encrypted` cast, 8 HMAC-hashed single-use recovery codes, replay protection via last accepted time step, `two-factor` limiter 5/min), `EnforceTwoFactor` middleware on all three panels and every account route group (required roles super_admin/admin/finance must enroll; any enrolled user gets a per-session challenge bound to the user id; `:setup` mode on the security pages), security page + challenge page, required roles cannot self-disable, `php artisan app:two-factor-reset {email}` operator recovery (audited, actor null). ResetChildPassword (linked verified parent only, password.confirm, audited, child sessions deleted + remember token rotated + hash change invalidates AuthenticateSession). Parent email must be verified before creating children. Notification routing base `LearnerNotificationRouter`: email-less child -> database only, verified guardians with verified email get `GuardianCopyNotification` in their locale with the child's name in the subject. Added `notifications` table. Config `identity.two_factor_enforced` (env TWO_FACTOR_ENFORCED, default true; phpunit sets false and the feature tests turn it on). LOCAL NOTE: your own .env now enforces 2FA for admin accounts unless you set TWO_FACTOR_ENFORCED=false. No SMS 2FA (out of scope). Status `review`: independent reviewer pending.
| T-P1-MG:A1 | evidence: docs/evidence/p1-milestone/README.md ticks no checklist box itself; it lists the docs/04 section 3 and 14A items proven by named tests and the P1 demo script (tests/Feature/Milestone/P1DemoTest.php). |
| T-P1-MG:A2 | evidence: docs/evidence/p1-milestone/README.md records the RTL screenshot review of the T-P1-10 and T-P1-11 captures, one defect found and fixed. |
- T-P1-MG: all local gates pass and the demo script ran (87 assertions), but the milestone rules need every P1 task `done`; see the open list in docs/evidence/p1-milestone/README.md. Gap found and repaired: public registration could not create a parent (account type learner/parent added). Arch suite now boots the app so `pest tests/Arch` passes alone. docs/04 checklist intentionally not edited (working copy diverges from committed text).

- Review wave 2026-11-17: security PASS (fixes applied), requirements audit PASS-WITH-NOTES, UX 3.9 < 4.5 (fixes applied, not re-scored). T-P1-05 and T-P1-06 promoted to done; T-P1-10/11 and T-P1-03/04/07/08 stay review. Deferred items and the adult-reset question are listed in docs/evidence/p1-milestone/README.md.
| T-P3-02:A3 | evidence: tests/Browser/CourseWizardBrowserTest.php captures both wizard steps in AR/EN at 390 and 1440 with axe (tests/Browser/screenshots/T-P3-02); compared with docs/design-system/reference-pack-2026-11-17/13-course-wizard-v2.png in docs/reviews/T-P3-02_VISUAL_MATCH.md. |
- T-P3-02 implemented by root (loop iteration 2026-11-17): migration adds wizard_step, wizard_completed, faqs, requirements, outcomes to courses; `SaveCourseWizard` (create needs course.create, edit needs the `update` policy; drafts save leniently at any time, passing a step enforces required fields; ownership/status/featured only for admin with the matching permission, a different value from anyone else is rejected; wizard cannot set published/pending_review; unique AR/EN slugs generated when empty; rows trimmed, empties dropped, order kept, max 30); Filament Create/Edit wizard pages (steps persist when passed, "Save draft" saves anything, setup-progress side card, resume on the saved step); CoursePolicy::create. Description stays plain text until the HtmlSanitizer (T-P3-15) exists. Steps are non-skippable so each step is validated and saved when passed. Status `review`.
| T-P3-04:A1 | evidence: tests/Browser/CurriculumBrowserTest.php drag-and-drop and keyboard reorder plus screenshots tests/Browser/screenshots/T-P3-04; docs/reviews/T-P3-04_VISUAL_MATCH.md. |
- T-P3-04 implemented by root (loop): migrations course_sections, lessons (no sort column), curriculum_items (only ordering source; draft default; required, ai_enabled, unlock fields present); morph map `lesson`; Actions ManageSections, ManageLessons, ReorderCurriculum (atomic, full-set payload so stale tabs cannot drop items, validated shape), MoveCurriculumItem (keyboard alternative, crosses section edges), SetCurriculumStatus (bulk, audited), CurriculumOutline (one query feeds manager and student order, archived hidden but kept); permissions: managing instructor or admin plus curriculum.* permission, unassigned 404 / view-only 403; Filament page `/courses/{id}/curriculum` with drag/drop, buttons, multi-select bulk publish, section publish, archive with confirm, student preview (read-only, no progress), `filament.courses.curriculum-readiness` slot for T-P3-09. Status `review`.
- T-P3-05 implemented by root (loop): `media_assets` (one source per lesson, tier T1/T2/T3, exception reason/by/at, status, metadata); contract `VideoDeliveryProvider` + drivers PrivateFileProvider (signed_storage video, audio, document, image; T1; private disk; signed link, TTL config media.signed_ttl=300), VimeoProvider (T2), YouTubeProvider and ExternalUrlProvider (T3), AllowlistedIframeProvider (T3; allowlist in config/media.php is EMPTY by default, owner must approve hosts; allowlist rechecked at playback; sandbox attr), RichTextProvider (T1, plain text only until the T-P3-15 sanitizer); `SourceUrl` blocks non-https, credentials, localhost and private IPs; `MediaProviders` registry (swap point for the O-005 streaming provider); `AttachLessonMedia` (auth via curriculum access, replaces source and clears any exception, audited), `GrantMediaException` (admin with course.publish, reason 10-500 chars, T3 only, audited), `LessonPublishRule` wired into SetCurriculumStatus and section publish (paid course, non-preview lesson, T3, no exception = blocked); signed route `media.play` serves T1 files only. Tier shown in the curriculum manager. OPEN: the signed route proves a link was issued, it does not check entitlement — the player must call LessonPlayback only after EntitlementService (T-P4-01/03); lessons without media can still be published (spec silent). Status `review`.
| T-P3-06:A3 | evidence: tests/Browser/CurriculumBrowserTest.php captures the lesson editor with the upload field in AR/EN at 390 and 1440 with axe (tests/Browser/screenshots/T-P3-06); matched against docs/design-system/reference-pack-2026-11-17/03-curriculum-builder.png in docs/reviews/T-P3-04_VISUAL_MATCH.md. |
- T-P3-06 implemented by root (loop): `UploadLessonMedia` (authorizes before writing; the file CONTENT decides the type, not the client name or declared type; allowed types per provider; size cap config media.max_kilobytes; random private name `media/<uuid>.<ext>`; images re-encoded to JPEG/PNG so EXIF/GPS never reach storage, large images scaled to 4096px; streamed to the private disk; cleaned up if attaching fails; the replaced file is deleted by AttachLessonMedia). Lesson editor (add/edit in the curriculum manager): description, type-appropriate media source, Filament upload with progress bar, link and text fields, lesson + media saved in one transaction so a refused file leaves no half-made lesson and the author sees the reason; media status and tier badges on rows. LIMITS: no chunked upload — the ceiling is PHP's upload_max_filesize/post_max_size and Livewire's temporary-upload limit (raise them for large videos in the server config); no automatic duration detection (no media service connected); no transcoding (provider O-005). Status `review`.
| T-P3-08:A2 | evidence: tests/Browser/CurriculumBrowserTest.php captures the PDF version badge on lesson rows and the lesson editor PDF and material fields in AR/EN at 390 and 1440 with axe (tests/Browser/screenshots/T-P3-08); reference match recorded in docs/reviews/T-P3-04_VISUAL_MATCH.md. |
- T-P3-08 implemented by root (loop): tables `lesson_resources` and `lesson_curriculum_documents` (version, sha256 unique per lesson, pages, status, active, approved_by/at); `PdfInspector` (no PDF library installed: header and end marker, /Encrypt, active content /JavaScript /JS /Launch /EmbeddedFile, page count including pages inside compressed object streams; anything unreadable is rejected, T-P3-09 brings a real parser); `UploadCurriculumDocument` (25 MB / 120 pages / not encrypted / no scripts, same bytes again = no-op, new bytes = new INACTIVE version), `ApproveCurriculumDocument` (needs lesson_document.approve; makes the version READY + active and retires the previous in one transaction — the pipeline calls it later), `ManageLessonResources` (pdf/text/images re-encoded/https links, random private names, remove deletes the file), `SignedFileUrl` + `PrivateFileAccess` + `PrivateFileController` (signed link tied to the account via `u`, short TTL, access rechecked on use, fails closed), `LessonAccessChecker` contract with `DenyLessonAccess` default until the EntitlementService (T-P4-01). Staff who manage the course may open its files; explicit public-preview resources open to any signed-in account. Lesson editor: curriculum PDF and material upload; rows show PDF version/status and material count. NOT DONE: UI to remove a resource or approve a PDF (domain actions exist); the AI is not touched. Status `review`.
| T-P3-10:A1 | evidence: tests/Feature/AI/AssistantEligibilityTest.php (dataset: global flag, course, item, live/text lesson, no entitlement, missing/unapproved/inactive PDF, daily/lesson quota, minor without consent/unverified guardian/no guardian, minors flag, adult age open). |
| T-P3-10:A2 | evidence: same file; FakeAiProvider is the only provider bound in local/testing, Http::preventStrayRequests stays on, NullAiProvider refuses in other environments. |
- T-P3-10 implemented by root (loop): 13 AI tables (document pages/chunks/key terms, prompt versions, conversations/messages/feedback, usage with student hash only, answer cache, quota counters, eval cases/runs, safeguarding flags; help_requests tables stay with T-P7-04), prompt version 1 seeded (assistant stays OFF), `AiProviderInterface` (complete/stream/embed/ocrPage/health) + DTOs, `FakeAiProvider` (queued answers, recorded requests, forced failures) bound in local/testing, `NullAiProvider` otherwise (O-014 provider/model still OPEN), `PromptRenderer` (lesson text cannot close the source tag or fake a page marker), `AssistantEligibility` in AI spec §2 order with a reason per failure and AR/EN messages; minors/unknown age fail closed (O-015: `AI_ADULT_AGE` unset = nobody adult, needs a verified guardian with consent + `ai.minors`). Gate G8 (`ai:eval`) belongs to T-P7-05 — not yet applicable. NOT DONE: API endpoint, quota increment (T-P7-01), real provider. Status `review`.
| T-P3-09:A1 | evidence: tests/Feature/AI/PdfIngestionTest.php — Arabic presentation forms repaired to base letters, visual-order lines reversed (numbers keep order), repeated headers/footers removed, quality scorer. |
| T-P3-09:A2 | evidence: same file — page with no text layer goes to OCR through the fake provider and is flagged; unreadable pages / missing extractor / no provider give FAILED with a readable AR/EN reason. |
| T-P3-09:A3 | evidence: same file + tests/Feature/Filament/ReviewDocumentPageTest.php — only approved text is active, edited text wins, replacement retires the old version atomically, chunks/embeddings only above the full-context size, key terms proposed from the text and approved by a person. |
| T-P3-09:A4 | evidence: tests/Fixtures/ai/redteam/pdf-injection.txt stored as plain page data (kept for T-P7-05); the prompt renderer neutralises the closing tag and fake page markers. |
| T-P3-09:A5 | evidence: tests/Browser/CurriculumBrowserTest.php captures the approval screen in AR/EN at 390 and 1440 with axe (tests/Browser/screenshots/T-P3-09); match note in docs/reviews/T-P3-09_VISUAL_MATCH.md. |
- T-P3-09 implemented by root (loop): queued `ProcessCurriculumDocument` -> `IngestCurriculumDocument` (text layer via poppler `pdftotext`, `ArabicTextRepair` NFKC + control/bidi removal + visual-order fix + repeated header/footer removal, OCR per empty page through `PageRenderer` (poppler `pdftoppm`) + `AiProviderInterface::ocrPage`, `QualityScorer`, thresholds in config/ai.php) ending in AWAITING_APPROVAL or FAILED(reason); `EditExtractedPage` (audited), `ApproveCurriculumDocument` (only from AWAITING_APPROVAL: approved text, chunks+embeddings above 40k tokens, key terms proposed, previous version retired atomically), `ApproveKeyTerms`, `DocumentReadiness` (⚪🟡🟠🔴🟢) in the curriculum manager rows and an approval page (PDF beside editable text). Upload queues the job (`AI_INGEST_ON_UPLOAD`, off in phpunit). LIMITS / OPEN: poppler (`pdftotext`, `pdftoppm`) must be installed on the server or extraction FAILS with a clear reason; no real OCR/embedding provider (O-014) so scanned pages stay unreadable and chunked mode (>40k tokens) cannot be approved until one is configured; reversed-line detection is a heuristic (needs 2+ Arabic words ending in "لا" more than starting with "ال"); thresholds are starting values to tune on real PDFs; no page images server-side; a REJECTED state exists but no reject UI yet; key-term extraction is frequency-based. Gate G8 belongs to T-P7-05. Status `review`.
| T-P3-11:A1 | evidence: tests/Feature/Curriculum/AssessmentItemsTest.php — mixed lesson/quiz/assignment items keep one order in the manager and the student query (reorder, up/down, publish, archive); existing curriculum browser tests (AR/EN, 390/1440, axe) still pass with the new rows. |
- T-P3-11 implemented by root (loop): tables `quizzes`, `assignments` (translatable title/description, archived_at) linked only through `curriculum_items` (morph types `quiz`, `assignment`); order and publish state stay on the item (ED-002), so there is no status column on the shells; `ManageAssessmentItems` (add as draft at section end, archive, audited, same permissions as lessons); `CurriculumOutline` covers all item types; manager header action "Add quiz or assignment" and rows with type label, move and archive. NOT DONE: editing a shell's title, questions, attempts, grading (P6); no dedicated screenshot of a quiz row (covered by the existing manager screenshots). Status `review`.
| T-P3-14:A1 | evidence: tests/Feature/Media/SubtitleTracksTest.php — invalid VTT (empty, not UTF-8, wrong signature, script markup, bad/ out-of-order timing, no cues, bad timestamps) rejected with a reason and nothing stored; one track per language, one default, replace and remove clean up files. |
| T-P3-14:A2 | evidence: same file — tracks appear in the playback descriptor only for a viewer the file rules allow (entitled or course staff), as signed account-bound links; a link opens for its account only and stops working when access is lost. |
- T-P3-14 implemented by root (loop): table `subtitle_tracks` (locale ar/en, label, private path, sha256, default), `VttValidator`, `ManageSubtitleTracks` (video/audio lessons only, same permission as lesson editing, audited), `PlaybackDescriptor::tracks` + `LessonPlayback::for($lesson, $viewer)`, `files.download` type `subtitle`. NOT DONE: caption upload field in the lesson editor and the player rendering (T-P4-03); no screenshots (no UI added). Status `review`.
- NOTE (not from this task): tests/Browser/StaffPanelsTest.php "staff users and roles" fails on the uncommitted-until-now calm-table workspace work: axe "scrollable region must have keyboard access" on `.fi-ta-content-ctn` and a screenshot write to `tests/Browser/Screenshots/...` (capital S) denied. Needs a fix by whoever owns T-P1-08/workspace tables.
| T-P4-01:A1 | evidence: tests/Feature/Entitlements/EntitlementServiceTest.php — same source+reference grants once (idempotent, audited once); manual grants need staff with `manual_entitlement.grant` and no payment (I05). |
| T-P4-01:A2 | evidence: same file — expired, revoked, not-yet-started and wrong-state cases deny lessons, files and AI eligibility; reason codes with AR/EN copy (lang/*/access.php); request cache dropped on grant/revoke/expire; hourly `entitlements:expire` scheduled. |
| T-P4-01:A3 | evidence: tests/Feature/Invariants/I01_EntitlementOnlyViaServiceTest.php — no code outside app/Domain/Entitlements touches the entitlements table or model. |
- T-P4-01 implemented by root (loop): table `entitlements` (unique source_type+source_ref+learner+course), `EntitlementSource`, `Entitlement`, `EntitlementService` (implements LessonAccessChecker, replaces the DenyLessonAccess stub which is deleted; checks course open, item/section published, preview lessons, current entitlement, date/days unlock, suspended learners), `AccessDecision`, `GrantEntitlement`/`RevokeEntitlement`/`ExpireEntitlements`. DECISIONS TO CONFIRM (OPEN): only `published` and `private` courses are open to learners (suspended/archived/upcoming/draft deny, even for existing learners); unlock rules supported are none/date/days (anything else fails closed as drip_locked); `not_in_cohort`, `sequence_locked`, `live_window_closed` codes belong to later tasks. I03/I04 are todo scaffolds for P8. NOT DONE: UI for grants/revokes (T-P4-02), commerce sources (P8). Status `review`.
| T-P4-02:A1 | evidence: tests/Feature/Entitlements/EnrollmentAndGrantsTest.php — manual grant creates one entitlement with no order/payment table involved; summary by source/state; filters; CSV export with formula-safe cells; screenshots tests/Browser/screenshots/T-P4-02 (AR/EN, 390/1440, axe) and docs/reviews/T-P4-02_VISUAL_MATCH.md. |
| T-P4-02:A2 | evidence: same test file — revoke needs a reason, is audited once, and the learner's access ends immediately. |
| T-P4-02:A3 | evidence: same test file — a verified parent enrolls a child in a free published course and the entitlement belongs to the child; strangers and unverified links are refused. |
- T-P4-02 implemented by root (loop): `EnrollInFreeCourse` (self or verified parent; free + published only; idempotent), `CourseAccessSummary`, Manual Grants Filament resource inside the Entitlements module (keeps I01) for admin and finance with grant (lifetime or end date, required reason), revoke, status/source/course filters, CSV export. NOT DONE: learner-facing enroll button on the course page (T-P2-05/T-P4-03), parent-side child enroll screen, per-course summary on the course view page (summary follows the course filter here). Status `review`.
| T-P4-03:A1 | evidence: tests/Feature/Learning/CoursePlayerTest.php — non-entitled, revoked, closed-course and cross-course lesson URLs return 404 (not 403); parent acts as the child through ActingLearner. |
| T-P4-03:A2 | evidence: same file — a dated lesson shows its lock reason to an entitled learner (page and curriculum list); only the preview lesson opens without enrolling; previous/next skip locked items. |
| T-P4-03:A3 | evidence: tests/Browser/CoursePlayerBrowserTest.php captures player and (mobile) curriculum drawer in AR/EN at 390 and 1440 with axe (tests/Browser/screenshots/T-P4-03); docs/reviews/T-P4-03_VISUAL_MATCH.md. |
| T-P4-03:A4 | evidence: tests/Browser/CoursePlayerBrowserTest.php "keyboard shortcuts and remembers the playback speed" — speed up/down keys, captions key and toggle state, speed saved and restored after reload, `window.ygPlayer.currentPosition()`. YG-GAP-UX-001 / YG-GAP-LRN-004. |
| T-P4-03:YG-GAP-UX-001 | evidence: same browser tests (responsive player, drawer, locked/preview states, AR/EN). |
- T-P4-03 implemented by root (loop): `EntitledCurriculumQuery` (per-learner open/preview/locked with reasons, previous/next), `LearnController` + routes `/learn/{course}` and `/learn/{course}/{item}`, Blade player (`learn.show`, `learning.lesson-media` for video/audio/embed/document/image/text, `learning.curriculum-list`, drawer on mobile), `resources/js/player.js` (speed 0.75-2x remembered in localStorage, captions toggle, full screen, shortcuts Space/K, arrows, J/L, F, C, < >; assistant hook `currentPosition()`), assistant slot rendered only when AssistantEligibility says available. NOT DONE: it is a Blade page with Alpine-free vanilla JS, not a Livewire component; rich-text lessons render as escaped plain text until T-P3-15; quizzes/assignments show a "coming soon" card (P6); no progress/resume (T-P4-04); live items do not exist yet so "hidden for live" is not exercised; the curriculum PDF is not offered to learners as a download. Status `review`.
