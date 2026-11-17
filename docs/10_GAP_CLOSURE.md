# YOUNG GO LMS --- v3.1 GAP CLOSURE LOG

**Date:** 16 November 2026 · **Applies to:** pack v3.0 → v3.1

Every gap found in the v3.0 review, how it was closed, and where.
Labels: APPROVED (owner/lead decision) · DEFAULT (reversible recommended
default, implement now) · OPEN (tracked, interim treatment defined).

| # | Gap | Closure | Label | Where |
|---|---|---|---|---|
| 1 | Child account creation / login undefined | Parent-created child with username/password, link codes, birth year/month, context switch audited | DEFAULT (R-011); verification O-015 | PRD §29.1, Arch §10.1, T-P1-05/06 |
| 2 | No cohort entity although live sessions reference cohorts | Cohorts, capacity, waitlist | DEFAULT (R-013) | PRD §29.3, T-P5-01 |
| 3 | Single name field; certificates need AR/EN | name_ar/name_en/display + certificate names with lock | DEFAULT (R-012) | PRD §29.2, T-P1-02, T-P6-06 |
| 4 | Video protection per source undefined | Tiers T1/T2/T3; paid requires T1/T2 or exception | DEFAULT (R-014); provider O-005 | PRD §29.4, T-P3-05 |
| 5 | Account sharing not addressed | Session limit 2, device limit 3, watermark, sharing flags | DEFAULT (R-015) | PRD §29.4, T-P4-07 |
| 6 | Edits to published independent courses | Revisions with review | DEFAULT (R-016) | PRD §29.5, T-P3-07 |
| 7 | Payment gateway not selected | **Paymob** | APPROVED (D-025) | PRD §17.3, §29.6, T-P8-02/03 |
| 8 | Instapay manual proof details | Fields, duplicate reference block, SLA | DEFAULT | PRD §29.6, T-P8-04 |
| 9 | E-receipt/e-invoice obligation unknown | ETA-ready data + disabled adapter; ask accountant/Paymob | OPEN (O-023) | PRD §29.6, T-P8-06 |
| 10 | Subscription renewal undefined | Manual renewal, reminders, grace, no proration; auto-renew flag off | DEFAULT (R-017); O-024 | PRD §29.7, T-P8-08 |
| 11 | Independent instructor onboarding missing | Application, versioned agreement, encrypted payout profile | DEFAULT (R-018) | PRD §29.8, T-P9-01 |
| 12 | Wishlist / reviews / discussions silent in v3.0 | Wishlist + moderated reviews carried; discussions not built | DEFAULT (R-019) | PRD §29.9, T-P9-02 |
| 13 | Analytics tags dropped | GA4/Pixel after consent, excluded from learning/child/AI | DEFAULT (R-021) | PRD §29.10, T-P9-03 |
| 14 | WhatsApp only P3 | Channel adapter in P9 for live reminders | DEFAULT (R-022); provider O-021 | PRD §29.10, T-P9-04 |
| 15 | Arabic search quality | Shared normalizer + search_text | DEFAULT (R-020) | PRD §29.10, T-P0-06, T-P2-04 |
| 16 | Privacy rights, retention, staging data | Export/delete workflow, retention defaults, anonymizer | DEFAULT (R-023); O-025 | PRD §29.11, T-P9-08 |
| 17 | Data protection laws for minors | Counsel review gate | OPEN | PRD §29.11 YG-GAP-PRV-004 |
| 18 | "One focused validation" risky for payments | Automated invariant suite = focused validation; CI gates | DEFAULT (R-024) | PRD §29.12, DMC §10A, Gates |
| 19 | No accessibility level, no RPO/RTO | WCAG 2.1 AA; RPO ≤ 24 h, RTO ≤ 4 h | DEFAULT (R-025) | PRD §29.12 |
| 20 | Email deliverability | SPF/DKIM/DMARC launch item | DEFAULT | PRD §29.10, T-P11-04 |
| 21 | Student UI stack open | Blade + Livewire + Alpine + Tailwind | DEFAULT (R-026) | Conventions §2 |
| 22 | No design system / visual direction | Token-based design system, page templates, visual gates | DEFAULT (R-027) | `11_DESIGN_SYSTEM.md` |
| 23 | No code organization / reuse rules | Domain Actions, shared blocks, arch tests, registry | DEFAULT (R-027) | `12_CODE_ARCHITECTURE_CONVENTIONS.md`, `REUSE_REGISTRY.md` |
| 24 | AI assistant needed production depth | v3.0 spec: full-PDF context, ingestion, quote verification, quiz guard, safety, eval gate, UX | DEFAULT (R-028); O-014/015/028 | `06_AI_LESSON_ASSISTANT_SPEC.md` v3.0, P3/P7 tasks |
| 25 | Arabic PDF rendering for certificates/receipts | Headless-Chromium renderer | DEFAULT (R-030) | Conventions §2, T-P6-06 |
| 26 | No executable task plan for agents | Orchestrator loop, subagents on lighter models, review protocol, gates, 113 tasks | DEFAULT (R-029) | `AGENTS.md`, `agents/` |
| 27 | Name inconsistency Yango/Young | Official name Young Go | — | PRD document control |
| 28 | `README_AR.md` referenced but missing | Created | — | `README_AR.md` |
| 29 | No effort sizing (O-022) | Relative S/M/L per task; dates still OPEN | OPEN (O-022) | `agents/PROGRESS.md` |
| 30 | SKIT source and brand assets not in pack | Tracked; token theme until supplied; brand extraction task | OPEN (O-026, O-027) | T-P0-04, T-P1-08 |

Not changed: all v3.0 owner decisions D-001..D-024 and hard restrictions.

## v3.2 second review (LearnHouse reference + full re-audit)

| # | Gap | Closure | Where |
|---|---|---|---|
| 31 | Mixed ordering of lessons/quizzes/assignments/live | Single `curriculum_items` ordering (ED-002) | Arch §11.1, T-P3-04/11 |
| 32 | Sequential locking and drip not tasked | Layered access + reason codes (ED-005, ED-025) | T-P4-09 |
| 33 | Course-level progress model missing | `course_progress` (ED-004) | T-P4-04 |
| 34 | `course_translations` vs JSON conflict | JSON translatable (ED-006) | Arch §11.1 |
| 35 | Staff 2FA missing | ED-016, YG-GAP-SEC-003 | T-P1-11 |
| 36 | Child password recovery and child notifications | ED-017, YG-GAP-FAM-007/008 | T-P1-11 |
| 37 | Terms/privacy acceptance at signup | YG-GAP-ID-002 | T-P1-02 |
| 38 | Rich-text lessons undefined/unsafe | Restricted blocks + sanitizer (ED-003, ED-031) | T-P3-15 |
| 39 | Reusable content library | ED-026 | T-P3-13 |
| 40 | Captions/subtitles | Manual VTT (ED-008) | T-P3-14 |
| 41 | Player controls (speed, keyboard, captions) | YG-GAP-LRN-004 | T-P4-03 |
| 42 | Bulk publish, draft default, view as learner | ED-024 | T-P3-04, T-P4-10 |
| 43 | Instructor view of learners' progress | YG-GAP-INS-004 | T-P4-10 |
| 44 | Instructor public profiles, category pages | YG-GAP-CMS-001/002 | T-P2-09 |
| 45 | Learner 360 / support view-as | ED-027 | T-P9-10 |
| 46 | Health check, demo data | ED-022 | T-P0-07, T-P9-11 |
| 47 | Branded error/maintenance pages | YG-GAP-OPS-004 | T-P1-10 |
| 48 | Feature flags/settings | ED-015 | T-P0-07 |
| 49 | Image EXIF/GPS of children | ED-031 | T-P3-08 |
| 50 | Performance budgets | ED-030 | Design System §7A, T-P2-03, T-P11-02 |
| 51 | No proof every requirement is tasked/tested | Traceability matrix + G10 + prd-auditor | `TRACEABILITY_MATRIX.md` |
| 52 | Tests written after code | Acceptance tests first (ED-020) | ORCHESTRATOR §4 |
| 53 | No independent functional/UX verification | qa-verifier, ux-reviewer (ED-029) | ORCHESTRATOR §7 |
| 54 | AI not considered per task | `ai:` line on all 123 tasks; AI spec §23 | tasks, AI spec |
| 55 | UX consistency not specified | U1–U10, pattern table, journeys (ED-023) | `15_UX_FLOWS_AND_PATTERNS.md` |
| 56 | Error tracking provider, launch traffic unknown | O-029, O-030 | Decision Register |
