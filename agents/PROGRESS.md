# PROGRESS — Young Go LMS agent loop

LOOP_STATE: RUN

Statuses: `todo` · `in_progress` · `review` · `done` · `blocked` · `skipped`. Only the orchestrator edits this file.

## Current
- Milestone: P0
- Active task: —
- Branch: —

## Blockers (credentials / OPEN decisions / owner gates)
- (filled by T-P0-01)

## Task board

| Task | Title | Status | Deps | Agents | Size | Risk | Commit | Evidence |
|---|---|---|---|---|---|---|---|---|
| T-P0-01 | Access and environment inventory | todo | — | orchestrator (no code), chore | M | normal | | |
| T-P0-02 | Stack compatibility and architecture decision record | todo | T-P0-01 | orchestrator, chore | S | normal | | |
| T-P0-03 | Application skeleton, CI and quality gates | todo | T-P0-02 | implementer, chore | M | normal | | |
| T-P0-04 | Brand extraction and design tokens | todo | T-P0-03 | ui-builder, chore | M | normal | | |
| T-P0-05 | Module map and initial domain/database map | todo | T-P0-02 | orchestrator, chore | S | normal | | |
| T-P0-06 | Shared domain primitives | todo | T-P0-03 | implementer, test-writer | M | normal | | |
| T-P0-07 | Platform services: flags, settings, phone, doctor, coverage tooling | todo | T-P0-03 | implementer, test-writer | M | normal | | |
| T-P0-MG | Milestone gate P0 | todo | T-P0-01..07 | orchestrator | S | normal | | |
| T-P1-01 | Localization foundation | todo | T-P0-03, T-P0-06 | implementer, test-writer, chore | M | normal | | |
| T-P1-02 | Authentication and profiles | todo | T-P1-01 | implementer, test-writer, chore | M | high | | |
| T-P1-03 | RBAC catalog, roles and policies base | todo | T-P1-02 | implementer, test-writer | M | high | | |
| T-P1-04 | Audit logging | todo | T-P1-03 | implementer, test-writer | S | normal | | |
| T-P1-05 | Parent-created child accounts and username login | todo | T-P1-03, T-P1-04 | implementer, test-writer, chore | M | high | | |
| T-P1-06 | Link codes and parent context switching | todo | T-P1-05 | implementer, test-writer | M | high | | |
| T-P1-07 | Age suitability model | todo | T-P1-02 | implementer, test-writer | S | normal | | |
| T-P1-08 | Filament panels, SKIT theme and shared Filament blocks | todo | T-P0-04, T-P1-03, T-P1-04 | implementer, ui-builder, test-writer, chore | L | normal | | |
| T-P1-09 | UI primitives library | todo | T-P0-04, T-P1-01 | ui-builder, test-writer | L | normal | | |
| T-P1-10 | Layouts, auth and family UI | todo | T-P1-02, T-P1-05, T-P1-06, T-P1-09 | ui-builder, implementer, test-writer, chore | L | normal | | |
| T-P1-11 | Staff 2FA, child password reset and child notification routing | todo | T-P1-06, T-P1-08, T-P0-07 | implementer, ui-builder, test-writer | M | high | | |
| T-P1-MG | Milestone gate P1 | todo | T-P1-01..11 | orchestrator | S | normal | | |
| T-P2-01 | Categories and disciplines | todo | T-P1-08 | implementer, test-writer, chore | S | normal | | |
| T-P2-02 | CMS pages and homepage blocks | todo | T-P1-08, T-P1-10 | implementer, ui-builder, test-writer, chore | M | normal | | |
| T-P2-03 | Brand public shell and homepage UI | todo | T-P2-02, T-P1-09 | ui-builder, chore | L | normal | | |
| T-P2-04 | Catalog, search and filters | todo | T-P2-01, T-P3-01, T-P1-07 | implementer, ui-builder, test-writer | L | normal | | |
| T-P2-05 | Course detail page and SEO fields group | todo | T-P2-04 | ui-builder, implementer, test-writer | M | normal | | |
| T-P2-06 | SEO infrastructure | todo | T-P2-02, T-P2-05 | implementer, test-writer | S | normal | | |
| T-P2-07 | Blog | todo | T-P2-06 | implementer, ui-builder, test-writer, chore | M | normal | | |
| T-P2-08 | Contact enquiries and newsletter | todo | T-P2-03 | implementer, ui-builder, test-writer | S | normal | | |
| T-P2-09 | Instructor profiles and category landing pages | todo | T-P2-04, T-P2-06 | implementer, ui-builder, test-writer | S | normal | | |
| T-P2-MG | Milestone gate P2 | todo | T-P2-01..09 | orchestrator | S | normal | | |
| T-P3-01 | Course domain, ownership and course list | todo | T-P1-07, T-P1-08, T-P2-01, T-P9-01 | implementer, test-writer, chore | L | high | | |
| T-P3-02 | Course wizard: Basic and Information steps | todo | T-P3-01 | implementer, ui-builder, test-writer, chore | M | normal | | |
| T-P3-03 | Course wizard: Pricing, Media, SEO, Custom fields, Finish and approval | todo | T-P3-02, T-P2-05 | implementer, ui-builder, test-writer | L | high | | |
| T-P3-04 | Curriculum manager | todo | T-P3-01 | implementer, ui-builder, test-writer | L | normal | | |
| T-P3-05 | Lesson media sources, adapters and protection tiers | todo | T-P3-04 | implementer, test-writer | L | high | | |
| T-P3-06 | Media uploads and lesson editor UI | todo | T-P3-05, T-P1-09 | ui-builder, implementer, test-writer | M | normal | | |
| T-P3-07 | Course revisions after publication | todo | T-P3-03, T-P3-04 | implementer, ui-builder, test-writer | M | high | | |
| T-P3-08 | Private files, lesson resources and curriculum PDF | todo | T-P3-04 | implementer, test-writer | M | high | | |
| T-P3-10 | AI foundation (M7.0) | todo | T-P3-08 | implementer, test-writer | M | high | | |
| T-P3-09 | Curriculum PDF ingestion pipeline (M7.1) | todo | T-P3-08, T-P3-10 | implementer, ui-builder, test-writer | L | high | | |
| T-P3-11 | Quiz and assignment authoring hooks | todo | T-P3-04 | implementer, test-writer | S | normal | | |
| T-P3-12 | Duplicate, archive and safe delete | todo | T-P3-03, T-P3-04, T-P3-08 | implementer, test-writer | S | normal | | |
| T-P3-13 | Content library | todo | T-P3-05, T-P3-06 | implementer, ui-builder, test-writer | M | normal | | |
| T-P3-14 | Subtitle tracks | todo | T-P3-05 | implementer, test-writer | S | normal | | |
| T-P3-15 | Rich-text lessons with restricted blocks | todo | T-P3-04, T-P0-07, T-P3-13 | implementer, ui-builder, test-writer | M | high | | |
| T-P3-MG | Milestone gate P3 | todo | T-P3-01..15 | orchestrator | S | normal | | |
| T-P4-01 | Unified EntitlementService | todo | T-P3-01, T-P1-04 | implementer, test-writer | L | high | | |
| T-P4-02 | Free enrollment, manual grants and course access summary | todo | T-P4-01, T-P5-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P4-03 | Course player and curriculum navigation | todo | T-P4-09, T-P3-05, T-P3-14, T-P3-15, T-P1-09 | ui-builder, implementer, test-writer | L | normal | | |
| T-P4-04 | Progress, resume and completion state | todo | T-P4-01, T-P3-04 | implementer, test-writer | M | high | | |
| T-P4-05 | Student dashboard and library | todo | T-P4-04, T-P4-03 | ui-builder, implementer, test-writer | M | normal | | |
| T-P4-06 | Protected PDFs, resources and previews | todo | T-P4-03, T-P3-08 | implementer, ui-builder, test-writer | S | high | | |
| T-P4-07 | Watermark, session and device limits | todo | T-P4-03, T-P1-02 | implementer, ui-builder, test-writer | M | normal | | |
| T-P4-08 | Parent dashboard | todo | T-P4-05, T-P1-06 | ui-builder, implementer, test-writer | M | high | | |
| T-P4-09 | Layered access: sequence and drip with reason codes | todo | T-P4-01, T-P4-04, T-P3-04, T-P5-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P4-10 | Instructor learner insights and view-as-learner preview | todo | T-P4-04, T-P4-09 | implementer, ui-builder, test-writer | M | normal | | |
| T-P4-MG | Milestone gate P4 | todo | T-P4-01..10 | orchestrator | S | normal | | |
| T-P5-01 | Cohorts, capacity and waitlist | todo | T-P4-01, T-P3-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P5-02 | Live session scheduling | todo | T-P5-01, T-P3-04 | implementer, ui-builder, test-writer | M | normal | | |
| T-P5-03 | Protected join links and learner live UI | todo | T-P5-02, T-P4-05 | implementer, ui-builder, test-writer | S | high | | |
| T-P5-04 | Notification base, reminders and lifecycle notices | todo | T-P5-02 | implementer, test-writer, chore | M | normal | | |
| T-P5-05 | Attendance and replay | todo | T-P5-03 | implementer, ui-builder, test-writer | S | normal | | |
| T-P5-MG | Milestone gate P5 | todo | T-P5-01..05 | orchestrator | S | normal | | |
| T-P6-01 | Quiz authoring and question bank | todo | T-P3-11 | implementer, ui-builder, test-writer | M | normal | | |
| T-P6-02 | Quiz runtime and attempts | todo | T-P6-01, T-P4-04, T-P4-09 | implementer, ui-builder, test-writer | L | high | | |
| T-P6-03 | Assignments and submissions | todo | T-P3-11, T-P4-03 | implementer, ui-builder, test-writer | M | high | | |
| T-P6-04 | Review and grading queue | todo | T-P6-03, T-P6-02, T-P5-04 | implementer, ui-builder, test-writer | M | high | | |
| T-P6-05 | Completion engine | todo | T-P6-02, T-P6-04, T-P5-05 | implementer, test-writer | M | high | | |
| T-P6-06 | Certificates and verification | todo | T-P6-05, T-P1-02 | implementer, ui-builder, test-writer | M | high | | |
| T-P6-MG | Milestone gate P6 | todo | T-P6-01..06 | orchestrator | S | normal | | |
| T-P7-01 | Assistant core pipeline and output guard | todo | T-P3-09, T-P3-10, T-P4-03 | implementer, test-writer | L | high | | |
| T-P7-02 | Input guards, quiz guard, cache and quotas | todo | T-P7-01, T-P6-02 | implementer, test-writer | M | high | | |
| T-P7-03 | Student assistant panel UI | todo | T-P7-02, T-P4-06 | ui-builder, implementer, test-writer, chore | M | high | | |
| T-P7-04 | Feedback, help requests and instructor/admin queue | todo | T-P7-03, T-P5-04 | implementer, ui-builder, test-writer | L | high | | |
| T-P7-05 | Evaluation harness, CI gate and instructor test console | todo | T-P7-02 | implementer, test-writer, chore | M | high | | |
| T-P7-06 | Admin controls, readiness, usage reports, insights and resilience | todo | T-P7-04, T-P7-05 | implementer, ui-builder, test-writer | M | high | | |
| T-P7-07 | Provider selection, privacy gate and pilot | todo | T-P7-06, T-P9-08 | orchestrator, implementer | M | high | | |
| T-P7-MG | Milestone gate P7 | todo | T-P7-01..06 (T-P7-07 may be blocked) | orchestrator | S | high | | |
| T-P8-01 | Pricing and order engine | todo | T-P4-01, T-P3-03, T-P0-06 | implementer, test-writer | M | high | | |
| T-P8-02 | PaymentGateway contract and Paymob checkout | todo | T-P8-01 | implementer, test-writer, chore | M | high | | |
| T-P8-03 | Paymob webhook verification and idempotent fulfillment | todo | T-P8-02, T-P5-01 | implementer, test-writer | M | high | | |
| T-P8-04 | Instapay/manual payment proofs | todo | T-P8-01, T-P4-01, T-P5-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P8-05 | Coupons | todo | T-P8-01 | implementer, test-writer | S | high | | |
| T-P8-06 | Refunds, payment history, receipts and e-receipt adapter | todo | T-P8-03, T-P8-04 | implementer, ui-builder, test-writer | M | high | | |
| T-P8-07 | Bundles and allocation | todo | T-P8-03 | implementer, ui-builder, test-writer | M | high | | |
| T-P8-08 | Subscription plans, periods and manual renewal | todo | T-P8-03, T-P5-04 | implementer, ui-builder, test-writer | L | high | | |
| T-P8-09 | Commission rules and instructor ledger | todo | T-P8-03, T-P8-07, T-P8-08 | implementer, test-writer | L | high | | |
| T-P8-10 | Statements and settlements | todo | T-P8-09, T-P9-01 | implementer, ui-builder, test-writer | M | high | | |
| T-P8-11 | Daily Paymob reconciliation | todo | T-P8-03 | implementer, test-writer | S | high | | |
| T-P8-12 | Checkout UI | todo | T-P8-02, T-P8-04, T-P8-05, T-P5-01, T-P1-10 | ui-builder, implementer, test-writer | M | high | | |
| T-P8-13 | Saudi market profile (disabled) | todo | T-P8-01 | implementer, test-writer | S | high | | |
| T-P8-MG | Milestone gate P8 | todo | T-P8-01..13 | orchestrator | S | high | | |
| T-P9-01 | Instructor applications, agreements and payout profiles | todo | T-P1-08 | implementer, ui-builder, test-writer | M | high | | |
| T-P9-02 | Wishlist and moderated reviews | todo | T-P2-04, T-P4-01 | implementer, ui-builder, test-writer | S | normal | | |
| T-P9-03 | Consent banner and marketing tags | todo | T-P2-03 | implementer, ui-builder, test-writer | S | normal | | |
| T-P9-04 | WhatsApp/SMS channel adapter | todo | T-P5-04 | implementer, test-writer | S | normal | | |
| T-P9-05 | Notification templates, preferences and announcements | todo | T-P5-04, T-P7-04, T-P8-03 | implementer, chore, test-writer | M | normal | | |
| T-P9-06 | Reports and CSV exports | todo | T-P8-10, T-P7-06, T-P6-06 | implementer, ui-builder, test-writer | L | high | | |
| T-P9-07 | Role dashboards with real KPIs | todo | T-P9-06 | ui-builder, implementer, test-writer | M | normal | | |
| T-P9-08 | Privacy rights, retention and staging anonymizer | todo | T-P8-06, T-P7-04 | implementer, test-writer | M | high | | |
| T-P9-09 | Settings module and legacy sidebar parity | todo | T-P9-01..08 | implementer, ui-builder, chore | M | normal | | |
| T-P9-10 | Learner 360 and support view-as | todo | T-P9-06, T-P4-10, T-P4-07 | implementer, ui-builder, test-writer | M | high | | |
| T-P9-11 | Demo dataset and demo reset | todo | T-P9-09 | chore, implementer | S | normal | | |
| T-P9-MG | Milestone gate P9 | todo | T-P9-01..11 | orchestrator | S | normal | | |
| T-P10-01 | Legacy source inventory | todo | T-P0-01, T-P3-MG | orchestrator, implementer | M | high | | |
| T-P10-02 | Source-to-target mapping spec | todo | T-P10-01 | orchestrator, chore | M | high | | |
| T-P10-03 | Migration framework | todo | T-P10-02 | implementer, test-writer | M | high | | |
| T-P10-04 | Import taxonomy, courses, translations, SEO and URL redirects | todo | T-P10-03 | implementer, test-writer | M | high | | |
| T-P10-05 | Import curriculum, media, PDFs and resources | todo | T-P10-04, T-P3-09 | implementer, test-writer | L | high | | |
| T-P10-06 | Import quizzes, assignments, live references and certificates | todo | T-P10-05, T-P6-MG | implementer, test-writer | M | high | | |
| T-P10-07 | Learner and business data import | todo | T-P10-06, T-P8-MG | implementer, test-writer | L | high | | |
| T-P10-08 | Staging rehearsal, delta procedure and freeze plan | todo | T-P10-07, T-P9-08 | orchestrator, implementer | M | high | | |
| T-P10-MG | Milestone gate P10 | todo | T-P10-01..08 | orchestrator | S | high | | |
| T-P11-01 | Full regression and security review | todo | T-P1-MG, T-P2-MG, T-P3-MG, T-P4-MG, T-P5-MG, T-P6-MG, T-P9-MG, T-P10-MG, T-P7-MG, T-P8-MG | orchestrator, reviewer, test-writer | L | high | | |
| T-P11-02 | Performance and capacity | todo | T-P11-01 | implementer, orchestrator | M | normal | | |
| T-P11-03 | Backups, restore drill and monitoring | todo | T-P11-01 | implementer, orchestrator | M | high | | |
| T-P11-04 | Production configuration and email deliverability | todo | T-P11-03 | implementer, orchestrator | S | high | | |
| T-P11-05 | Training guides | todo | T-P11-01 | chore, ui-builder | M | normal | | |
| T-P11-06 | Cutover runbook and rehearsal | todo | T-P10-08, T-P11-04 | orchestrator | M | high | | |
| T-P11-07 | Production cutover and handover | todo | T-P11-06, T-P11-05, T-P11-02 | orchestrator | M | high | | |
| T-P11-MG | Milestone gate P11 | todo | T-P11-01..07 | orchestrator | S | high | | |
| T-P12-01 | Saudi activation readiness | todo | T-P8-13, T-P11-MG | orchestrator, implementer | M | high | | |
| T-P12-MG | Milestone gate P12 | todo | T-P12-01 | orchestrator | S | high | | |

## Task logs

<!-- Orchestrator appends per task: plan, subtasks (agent/model), review verdicts, criteria→evidence map, gate results. -->

## Notes
- Gate-blocked by design until approvals: T-P7-07 (O-014/O-015), T-P8 live activation (Paymob credentials, O-023), T-P10-01 (O-003), T-P10-07 (O-018), T-P11-07 (owner cutover approval), T-P12-01 (Saudi readiness).
