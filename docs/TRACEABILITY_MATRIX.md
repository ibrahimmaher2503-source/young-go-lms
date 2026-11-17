# TRACEABILITY MATRIX — Young Go LMS

Planning map reviewed against `docs/01_MASTER_PRD_YOUNG_GO_LMS.md`, `docs/07_DECISION_REGISTER.md` (D-*), `docs/13_ENGINEERING_DECISIONS.md` (ED-*) and `agents/tasks/*.md`. Review mappings after any spec or task change; task assignment is not implementation coverage. Exact D-* and ED-* identifiers are separate namespaces. `app:req-coverage` is planned in T-P0-07; until available, review references manually. Empty Tests cells mean no test evidence has been supplied. A requirement is **done** only when its tasks are `done` and every acceptance item has passing evidence on the final revision plus required independent reviews (G10). Test tags alone are insufficient.

| Requirement | Title | Tasks / coverage | Tests (tags) |
|---|---|---|---|
| PRD §1 | Executive summary | context | |
| PRD §2 | Product vision, goals and KPIs | T-P9-03, T-P9-06, T-P9-07 | |
| PRD §2.1 | Vision | context | |
| PRD §2.2 | Business goals | context | |
| PRD §2.3 | KPI framework | T-P9-06, T-P9-07 | |
| PRD §3 | Scope and boundaries | T-P6-03, T-P6-04 | |
| PRD §3.1 | In scope | scope | |
| PRD §3.2 | Out of scope unless separately approved | Scope constraint; prd-auditor verifies excluded features remain absent | |
| PRD §4 | Brand, domain, markets and languages | T-P0-04, T-P0-06, T-P1-01, T-P2-03 | |
| PRD §4.1 | Brand continuity \[APPROVED\] | T-P0-04, T-P2-03 | |
| PRD §4.2 | Country rollout \[APPROVED\] | T-P0-06 | |
| PRD §4.3 | Languages \[APPROVED\] | T-P1-01 | |
| PRD §5 | Audience, age model and family accounts | T-P1-05, T-P1-07, T-P1-10, T-P2-01, T-P4-08, T-P7-02, T-P7-07 | |
| PRD §5.1 | Learner audience \[APPROVED\] | T-P1-07, T-P2-01 | `tests/Feature/Filament/CategoriesTest.php` (taxonomy) |
| PRD §5.2 | Parent/guardian account \[APPROVED\] | T-P1-05, T-P1-10, T-P4-08 | |
| PRD §5.3 | Child safety and consent \[RECOMMENDED / launch gate\] | T-P1-05, T-P7-02, T-P7-07 | |
| PRD §6 | Roles and permission model | T-P1-02, T-P1-03 | |
| PRD §7 | Core user journeys | journeys → 15_UX + qa-verifier | |
| PRD §8 | Public website, catalog and CMS requirements | T-P2-09 | |
| PRD §9 | Course management and Course Builder | T-P2-05, T-P3-01, T-P3-02, T-P3-03, T-P3-04, T-P3-05, T-P3-06, T-P3-08, T-P3-12, T-P4-06 | |
| PRD §9.1 | Course management list | T-P3-01, T-P3-12 | `tests/Feature/Invariants/I02_CourseScopeTest.php` (scoped list and counts) |
| PRD §9.2 | Multi-step course wizard \[LEGACY-CARRY\] | T-P2-05, T-P3-02, T-P3-03 | |
| PRD §9.3 | Course status | T-P3-01 | `tests/Feature/Invariants/I02_CourseScopeTest.php` (publication and counts) |
| PRD §9.4 | Course ownership and instructor model \[APPROVED\] | T-P3-01, T-P3-03 | `tests/Feature/Invariants/I02_CourseScopeTest.php` (assignment and approval boundary) |
| PRD §9.5 | Curriculum manager | T-P3-04, T-P3-12 | |
| PRD §9.6 | Lesson creation flow \[APPROVED DIRECTION\] | T-P3-05, T-P3-06 | |
| PRD §9.7 | Lesson curriculum PDF \[APPROVED\] | T-P3-08, T-P4-06 | |
| PRD §9.8 | Media security | T-P3-05, T-P3-08 | |
| PRD §10 | Student learning experience | T-P4-03, T-P4-04, T-P4-05 | |
| PRD §10.1 | Student dashboard | T-P4-05 | |
| PRD §10.2 | Course player \[LEGACY-CARRY\] | T-P4-03 | |
| PRD §10.3 | Progress | T-P4-04 | |
| PRD §11 | Live and hybrid learning | T-P5-02, T-P5-03, T-P5-05 | |
| PRD §12 | Quizzes and assessments | T-P3-11, T-P6-01, T-P6-02 | |
| PRD §13 | Practical assignments and submissions | T-P3-11, T-P6-03, T-P6-04 | |
| PRD §14 | Certificates and completion | T-P6-05, T-P6-06 | |
| PRD §15 | Personal AI Lesson Assistant \[APPROVED --- SIMPLIFIED SCOPE\] | T-P3-10, T-P7-01, T-P7-02, T-P7-03, T-P7-04, T-P7-07 | |
| PRD §15.1 | Purpose | T-P7-03 | |
| PRD §15.2 | Availability/context | T-P3-10, T-P7-01 | |
| PRD §15.3 | Student interaction | T-P7-03 | |
| PRD §15.4 | Response rules | T-P7-01 | |
| PRD §15.5 | Human escalation/in-platform communication | T-P7-04 | |
| PRD §15.6 | AI safety/operations | T-P7-01, T-P7-02 | |
| PRD §16 | Instructor help-request workflow | T-P7-04 | |
| PRD §17 | Commerce, plans, bundles and access | T-P4-01, T-P8-01, T-P8-04, T-P8-05, T-P8-06, T-P8-07, T-P8-09, T-P8-10, T-P8-13 | |
| PRD §17.1 | Sales models \[APPROVED\] | T-P8-01 | |
| PRD §17.2 | Unified entitlement engine | T-P4-01 | |
| PRD §17.3 | Checkout/payment | T-P8-01, T-P8-04, T-P8-05, T-P8-06 | |
| PRD §17.4 | Instructor revenue and settlement \[APPROVED SIMPLE MODEL\] | T-P8-07, T-P8-09, T-P8-10 | |
| PRD §17.5 | Country activation | T-P8-13 | |
| PRD §18 | Manual grants, enrollment and registrations | T-P4-02 | |
| PRD §19 | Admin and instructor operations | T-P1-08, T-P2-02, T-P9-09 | |
| PRD §19.1 | SKIT \[APPROVED\] | T-P1-08 | |
| PRD §19.2 | Legacy sidebar/module inventory \[LEGACY-CARRY\] | T-P1-08, T-P2-02, T-P9-09 | `tests/Browser/CmsPagesBrowserTest.php` (CMS admin modules) |
| PRD §20 | Notifications and communications | T-P5-04, T-P9-05 | |
| PRD §21 | Reporting and exports | T-P9-06 | |
| PRD §22 | Audit, settings and integrations | T-P1-04, T-P9-09 | |
| PRD §23 | Data and business invariants | T-P0-03, T-P0-06, T-P4-01, T-P8-03 | |
| PRD §24 | Non-functional requirements | T-P11-02 | |
| PRD §25 | Migration and cutover requirements | T-P10-01, T-P10-02, T-P10-03, T-P10-04, T-P10-05, T-P10-06, T-P10-07, T-P10-08, T-P11-06 | |
| PRD §26 | Launch acceptance gates | T-P11-01 | |
| PRD §27 | Open decisions and dependencies | OPEN register | |
| PRD §28 | Source references | sources | |
| PRD §29 | Gap-closure requirements \[v3.1\] | see IDs | |
| PRD §29.1 | Family accounts and child login | see YG-GAP IDs below | |
| PRD §29.2 | Names and identity | see YG-GAP IDs below | |
| PRD §29.3 | Cohorts, capacity and waitlist | see YG-GAP IDs below | |
| PRD §29.4 | Content protection and account sharing | see YG-GAP IDs below | |
| PRD §29.5 | Course changes after publication | see YG-GAP IDs below | |
| PRD §29.6 | Payments (Paymob) and receipts | see YG-GAP IDs below | |
| PRD §29.7 | Subscriptions renewal | see YG-GAP IDs below | |
| PRD §29.8 | Independent instructor onboarding | see YG-GAP IDs below | |
| PRD §29.9 | Engagement features carried from June 2026 PRD | see YG-GAP IDs below | |
| PRD §29.10 | Search, analytics, notifications | see YG-GAP IDs below | |
| PRD §29.11 | Privacy rights and retention | see YG-GAP IDs below | |
| PRD §29.12 | Quality, accessibility and recovery | see YG-GAP IDs below | |
| PRD §29.13 | Design system and code organization | see YG-GAP IDs below | |
| PRD §29.14 | Second-review additions \[v3.2\] | see YG-GAP IDs below | |
| YG-GAP-A11Y-001 |  | T-P1-09, T-P11-01 | |
| YG-GAP-CHG-001 |  | T-P3-07 | |
| YG-GAP-CHG-002 |  | T-P3-07 | |
| YG-GAP-CHG-003 |  | T-P3-07 | |
| YG-GAP-CMS-001 |  | T-P2-09 | |
| YG-GAP-CMS-002 |  | T-P2-09 | |
| YG-GAP-CNT-001 |  | T-P3-04, T-P3-11 | |
| YG-GAP-CNT-002 |  | T-P3-15 | |
| YG-GAP-CNT-003 |  | T-P3-13 | |
| YG-GAP-CODE-001 |  | T-P0-03, T-P0-06 | |
| YG-GAP-COH-001 |  | T-P5-01 | |
| YG-GAP-COH-002 |  | T-P5-01 | |
| YG-GAP-COH-003 |  | T-P5-01 | |
| YG-GAP-DR-001 |  | T-P11-03 | |
| YG-GAP-ENG-001 |  | T-P9-02 | |
| YG-GAP-ENG-002 |  | T-P9-02 | |
| YG-GAP-ENG-003 |  | T-P9-02 | |
| YG-GAP-FAM-001 |  | T-P1-05 | |
| YG-GAP-FAM-002 |  | T-P1-06 | |
| YG-GAP-FAM-003 |  | T-P1-02, T-P1-05 | |
| YG-GAP-FAM-004 |  | T-P1-06 | |
| YG-GAP-FAM-005 |  | T-P1-05, T-P8-01 | |
| YG-GAP-FAM-006 |  | T-P1-05, T-P7-07 | |
| YG-GAP-FAM-007 |  | T-P1-11 | |
| YG-GAP-FAM-008 |  | T-P1-11 | |
| YG-GAP-ID-001 |  | T-P1-02, T-P6-06 | |
| YG-GAP-ID-002 |  | T-P1-02 | |
| YG-GAP-INS-001 |  | T-P9-01 | |
| YG-GAP-INS-002 |  | T-P9-01 | |
| YG-GAP-INS-003 |  | T-P9-01 | |
| YG-GAP-INS-004 |  | T-P4-10 | |
| YG-GAP-LRN-001 |  | T-P4-09 | |
| YG-GAP-LRN-002 |  | T-P4-09 | |
| YG-GAP-LRN-003 |  | T-P3-14 | |
| YG-GAP-LRN-004 |  | T-P4-03 | |
| YG-GAP-LRN-005 |  | T-P4-10 | |
| YG-GAP-LRN-006 |  | T-P4-04 | |
| YG-GAP-MKT-001 |  | T-P9-03 | |
| YG-GAP-NTF-001 |  | T-P9-04 | |
| YG-GAP-NTF-002 |  | T-P11-04 | |
| YG-GAP-OPS-001 |  | T-P9-10 | |
| YG-GAP-OPS-002 |  | T-P9-10 | |
| YG-GAP-OPS-003 |  | T-P0-07, T-P9-11 | |
| YG-GAP-OPS-004 |  | T-P1-10 | |
| YG-GAP-OPS-005 |  | T-P0-07 | |
| YG-GAP-PAY-001 |  | T-P8-02 | |
| YG-GAP-PAY-002 |  | T-P8-03 | |
| YG-GAP-PAY-003 |  | T-P8-11 | |
| YG-GAP-PAY-004 |  | T-P8-04 | |
| YG-GAP-PAY-005 |  | T-P8-06 | |
| YG-GAP-PERF-001 |  | T-P2-03, T-P11-02 | |
| YG-GAP-PRV-001 |  | T-P9-08 | |
| YG-GAP-PRV-002 |  | T-P9-08 | |
| YG-GAP-PRV-003 |  | T-P9-08 | |
| YG-GAP-PRV-004 |  | T-P9-08, T-P12-01 | |
| YG-GAP-QA-001 |  | T-P0-03 | |
| YG-GAP-QA-002 |  | T-P0-03 | |
| YG-GAP-QA-003 |  | T-P1-09 | |
| YG-GAP-SEC-001 |  | T-P4-07 | |
| YG-GAP-SEC-002 | Sharing flags | T-P9-10; consumes T-P4-07 session/device facts | |
| YG-GAP-SEC-003 |  | T-P1-11 | |
| YG-GAP-SEC-004 |  | T-P3-08 | |
| YG-GAP-SRCH-001 |  | T-P0-06, T-P2-01, T-P2-04 | `tests/Feature/Filament/CategoriesTest.php` (category indexing and search) |
| YG-GAP-SUB-001 |  | T-P8-08 | |
| YG-GAP-SUB-002 |  | T-P8-08 | |
| YG-GAP-SUB-003 |  | T-P8-08 | |
| YG-GAP-SUB-004 |  | T-P8-08 | |
| YG-GAP-UI-001 |  | T-P0-04, T-P1-09 | |
| YG-GAP-UX-001 |  | T-P1-10, T-P2-03, T-P4-03 | |
| YG-GAP-VID-001 |  | T-P3-05 | |
| YG-GAP-VID-002 |  | T-P3-05, T-P10-05 | |
| YG-GAP-VID-003 |  | T-P4-07 | |
| YG-GAP-VID-004 |  | T-P3-05 | |
| YG-WEB-001 |  | T-P2-03 | |
| YG-WEB-002 |  | T-P2-02 | `tests/Feature/Pages/CmsPagesTest.php` (block order and visibility) |
| YG-WEB-003 |  | T-P2-01, T-P2-04 | `tests/Feature/Filament/CategoriesTest.php` (category hierarchy) |
| YG-WEB-004 |  | T-P2-05 | |
| YG-WEB-005 |  | T-P2-02 | `tests/Feature/Pages/CmsPagesTest.php` (publication, editor preview and draft seeds) |
| YG-WEB-006 |  | T-P2-07 | |
| YG-WEB-007 |  | T-P2-08 | |
| YG-WEB-008 |  | T-P2-08 | |
| YG-WEB-009 |  | T-P2-06 | |
| YG-WEB-010 |  | T-P2-03 | |
| D-001 | Owner decision | T-P0-02, T-P0-03, T-P0-05 | |
| D-002 | Owner decision | T-P0-04, T-P2-03 | |
| D-003 | Owner decision | T-P0-01, T-P11-06, T-P11-07 | |
| D-004 | Owner decision | T-P10-01, T-P10-04, T-P10-05 | |
| D-005 | Owner decision | T-P1-07 | |
| D-006 | Owner decision | T-P1-05, T-P1-06, T-P4-08 | |
| D-007 | Owner decision | T-P3-01, T-P3-03, T-P9-01 | `tests/Feature/Invariants/I02_CourseScopeTest.php` (course ownership) |
| D-008 | Owner decision | T-P8-01, T-P8-07, T-P8-08 | |
| D-009 | Owner decision | T-P0-06, T-P8-13 | |
| D-010 | Owner decision | T-P8-13, T-P12-01 | |
| D-011 | Owner decision | T-P1-01, T-P1-09 | |
| D-012 | Owner decision | T-P8-01, T-P8-09, T-P8-10 | |
| D-013 | Owner decision | T-P8-09 | |
| D-014 | Owner decision | T-P8-09 | |
| D-015 | Owner decision | T-P8-07, T-P8-09 | |
| D-016 | Owner decision | T-P8-08, T-P8-09 | |
| D-017 | Owner decision | T-P3-10, T-P7-01, T-P7-03 | |
| D-018 | Owner decision | T-P3-08, T-P3-09, T-P7-01 | |
| D-019 | Owner decision | T-P7-04 | |
| D-020 | Owner decision | T-P7-04 | |
| D-021 | Owner decision | T-P7-04 | |
| D-022 | Owner decision | T-P1-08 | |
| D-023 | Owner decision | AGENTS.md separation rule; T-P0-01 | |
| D-024 | Owner decision | T-P0-03, T-P11-01, T-P11-03; orchestrator workflow | |
| D-025 | Owner decision | T-P8-02, T-P8-03 | |
| ED-001 |  | conventions (T-P0-02/03) | |
| ED-002 |  | T-P3-11, T-P5-02 | |
| ED-003 |  | T-P3-15 | |
| ED-004 |  | T-P4-04 | |
| ED-005 |  | T-P4-09 | |
| ED-006 |  | T-P1-01 | |
| ED-007 |  | T-P3-05 | |
| ED-008 |  | T-P3-14 | |
| ED-009 |  | T-P7-01, T-P7-03 | |
| ED-010 |  | T-P7-02 | |
| ED-011 |  | T-P3-09 | |
| ED-012 |  | T-P8-01 | |
| ED-013 |  | T-P5-01 | |
| ED-014 |  | out-of-scope (prd-auditor) | |
| ED-015 |  | T-P0-07 | |
| ED-016 |  | T-P1-11 | |
| ED-017 |  | T-P1-11 | |
| ED-018 |  | T-P1-01, T-P2-06 | |
| ED-019 |  | T-P0-07 | |
| ED-020 |  | T-P0-07 | |
| ED-021 |  | T-P0-07 | |
| ED-022 |  | T-P0-07, T-P9-11 | |
| ED-023 |  | 15_UX + ux-reviewer | |
| ED-024 |  | T-P3-04, T-P4-10 | |
| ED-025 |  | T-P4-09 | |
| ED-026 |  | T-P3-13 | |
| ED-027 |  | T-P9-10 | |
| ED-028 |  | T-P0-07 | |
| ED-029 |  | ORCHESTRATOR §7 | |
| ED-030 |  | T-P2-03, T-P11-02 | |
| ED-031 |  | T-P3-08, T-P3-15 | |
| ED-032 |  | T-P0-06, T-P5-02 | |
| PRD §29.15 | Review corrections | See YG-REV owners below | |
| YG-REV-ACC-001 | Review requirement (PRD §29.15) | T-P4-01 | |
| YG-REV-ACC-002 | Review requirement (PRD §29.15) | T-P4-09 | |
| YG-REV-FAM-001 | Review requirement (PRD §29.15) | T-P1-05 | |
| YG-REV-FAM-002 | Review requirement (PRD §29.15) | T-P1-06 | |
| YG-REV-FIN-001 | Review requirement (PRD §29.15) | T-P8-09 | |
| YG-REV-LRN-001 | Review requirement (PRD §29.15) | T-P6-05 | |
| YG-REV-LRN-002 | Review requirement (PRD §29.15) | T-P6-02 | |
| YG-REV-LRN-003 | Review requirement (PRD §29.15) | T-P5-02 | |
| YG-REV-MIG-001 | Review requirement (PRD §29.15) | T-P10-03 | |
| YG-REV-MIG-002 | Review requirement (PRD §29.15) | T-P11-06 | |
| YG-REV-PAY-001 | Review requirement (PRD §29.15) | T-P8-01 | |
| YG-REV-PAY-002 | Review requirement (PRD §29.15) | T-P8-03 | |
| YG-REV-PAY-003 | Review requirement (PRD §29.15) | T-P5-01 | |
| YG-REV-PAY-004 | Review requirement (PRD §29.15) | T-P8-06 | |
| YG-REV-PRV-001 | Review requirement (PRD §29.15) | T-P9-08 | |
| YG-REV-SUB-001 | Review requirement (PRD §29.15) | T-P8-08 | |
| ED-033 | Review engineering decision | T-P0-05 | |
| ED-034 | Review engineering decision | T-P8-01, T-P8-03, T-P8-06, T-P8-09 | |
| ED-035 | Review engineering decision | T-P4-01, T-P4-09 | |
| ED-036 | Review engineering decision | T-P7-01, T-P7-02 | |
| ED-037 | Review engineering decision | T-P3-09 | |
