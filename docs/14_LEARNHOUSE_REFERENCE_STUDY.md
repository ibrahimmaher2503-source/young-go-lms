# YOUNG GO LMS --- LEARNHOUSE REFERENCE STUDY

**Date:** 16 November 2026 · **Purpose:** use LearnHouse (open-source LMS,
github.com/learnhouse/learnhouse, docs.learnhouse.app) as a **design
reference**, not a code base. D-001 forbids building on a packaged LMS, and
LearnHouse is AGPL-3.0, so **no LearnHouse code is copied**. We study its
product and architecture patterns and decide, per pattern, to adopt, adapt or
reject. Every decision is recorded in `13_ENGINEERING_DECISIONS.md`.

## 1. What LearnHouse is (observed from its public repo and docs, Nov 2026)

| Area | LearnHouse |
|---|---|
| Apps | `apps/web` (Next.js, React, Tailwind, Tiptap), `apps/api` (FastAPI, SQLModel, Alembic), `apps/collab` (Hocuspocus/Yjs real-time), `apps/cli` (setup/dev/doctor/backup) |
| Data | PostgreSQL 16 + pgvector (embeddings), Redis (cache, sessions, rate limits, queues) |
| Background jobs | AI search indexing, HLS video transcoding, AI captions, backfills, demo org reset |
| Content model | Organization → Course → Chapter → Activity; activities are JSON block documents |
| Activity types | Dynamic page (block editor), Video (upload/YouTube, start/end, captions), Document (PDF viewer), Assignment (graded, stored submissions), Markdown, Embed, Resource (from Library), SCORM (enterprise) |
| Progress | User → Trail → Run (per course) → Step (per completed activity) |
| Access | Course access settings + per chapter/activity lock: public / signed-in / user groups; drip by day; per-activity draft/publish, bulk publish, "preview activity" |
| Editor | Notion-like block editor: text, media, quiz (ungraded), flipcards, code playground, math, callouts, embeds, tables, conditional blocks, templates, AI "magic blocks" |
| AI | "Ask AI" chat per activity (reads that activity), Copilot across courses with citations, teacher generation tools, course indexing, credits reserved then refunded on failure |
| Users | One role per org, custom roles, user groups, invite codes, signup fields, 2FA, learner analytics record, email unsubscribe |
| Payments | Products ("a price and everything it unlocks"), Stripe Checkout, grant/revoke/export access, discounts (enterprise) |
| Quality | Playwright e2e suite, Codecov coverage, Renovate, typed SDK |

## 2. Pattern decisions for Young Go

| # | LearnHouse pattern | Decision | Why / how in Young Go | ED | Where |
|---|---|---|---|---|---|
| 1 | Multi-app (Next.js + FastAPI + collab) | **Reject** | Laravel modular monolith fits PRD R-001/R-003, team skill and one deploy; API v1 kept for AI/mobile later | ED-001 | Conventions §2 |
| 2 | Course → Chapter → Activity tree | **Adopt (adapted)** | Course → Section → **CurriculumItem** (single ordered table, typed items: lesson, quiz, assignment, live session) | ED-002, ED-024 | Arch §11.1, T-P3-04 |
| 3 | Activity as JSON block document | **Adapt** | Rich-text lessons stored as sanitized structured JSON with a **restricted block set** (no executable HTML, no AI magic blocks) | ED-003 | T-P3-06 |
| 4 | Trail → Run → Step | **Adopt (adapted)** | `course_progress` (run per learner-course) + `lesson_progress` (step) | ED-004 | Arch §11.1, T-P4-04 |
| 5 | Per-item access locks + drip | **Adopt (adapted)** | Layered access: publication → entitlement → cohort → sequence/drip → preview, all inside `EntitlementService` | ED-005, ED-025 | T-P4-09 |
| 6 | Draft by default, bulk publish, preview as learner | **Adopt** | New items default draft; bulk publish/unpublish; "view as learner" preview | ED-024 | T-P3-04, T-P4-10 |
| 7 | Library of reusable resources | **Adopt** | Instructor/academy content library reusable across lessons | ED-026 | T-P3-13 |
| 8 | Video: HLS transcoding job, captions | **Adapt** | T1 via provider adapter (HLS signed); local ffmpeg HLS job only if no provider; **manual VTT captions AR/EN**; no AI captions | ED-007, ED-008 | T-P3-14 |
| 9 | Ask AI per activity | **Adopt (restricted)** | Same placement idea (panel beside lesson) but source = **approved lesson PDF only**, short answers, cited pages, help escalation | ED-009 | AI spec v3 |
| 10 | Ask AI starter prompts ("Generate flashcards…") | **Adapt** | Starter chips = approved key terms; follow-up chips only from those terms; no generation prompts (D-018) | ED-009 | T-P7-03 |
| 11 | Copilot across all courses, teacher generation tools, magic blocks, AI course creator, AI captions | **Reject** | Outside owner-approved AI scope (D-017..D-021) | ED-009 | — |
| 12 | Credits reserved then refunded on failure | **Adopt** | Quota reserve/commit/refund so failures never consume learner quota | ED-010 | T-P7-02 |
| 13 | pgvector for embeddings | **Adapt** | Full-PDF context by default; vectors only for oversized PDFs; DB choice stays O-004 | ED-011 | AI spec §5 |
| 14 | Products = price + what it unlocks | **Adopt (adapted)** | No separate products table: course, bundle, plan implement a `Sellable` contract feeding orders and entitlements | ED-012 | T-P8-01 |
| 15 | User groups for access | **Adapt** | Cohorts are the only learner groups (access + scheduling); no generic groups | ED-013 | T-P5-01 |
| 16 | Real-time boards, playgrounds, code execution, podcasts, SCORM, MCP server, SDK/embeds, communities | **Reject** | Not in PRD; would add cost and risk | ED-014 | PRD §3.2 |
| 17 | Ungraded inline quiz block | **Reject (for now)** | Graded quizzes cover need; avoid two quiz systems | ED-014 | — |
| 18 | 2FA | **Adopt** | Mandatory for super admin, admin, finance; optional others | ED-016 | T-P1-11 |
| 19 | Invite codes, custom signup fields | **Reject** | Public academy signup + parent-created children cover the need | ED-014 | — |
| 20 | Learner analytics record | **Adopt** | "Learner 360" page for staff with permission | ED-027 | T-P9-10 |
| 21 | CLI doctor / backup | **Adopt (adapted)** | `php artisan app:doctor` (health of queues, storage, mail, providers, flags) | ED-022 | T-P0-07 |
| 22 | Demo organization reset job | **Adopt** | `app:demo-reset` seeded demo for staging/client demos | ED-022 | T-P9-11 |
| 23 | Playwright e2e + coverage reporting | **Adopt** | Pest browser tests + coverage report in CI; requirement-tagged tests | ED-020, ED-028 | Gates G7, G10 |
| 24 | One role per org, custom roles | **Adapt** | Single tenant (Young Go); multiple roles allowed per user via spatie permission; custom roles by super admin | ED-001 | T-P1-03 |
| 25 | Organization multi-tenancy | **Reject** | PRD excludes multi-tenant SaaS | ED-014 | PRD §3.2 |

## 3. UX lessons taken (adapted to Young Go)

- Learner sees **one clear next step**: curriculum sidebar + "next" action, like
  LearnHouse's activity viewer → Young Go player template (Design System §5).
- Editors work in **one course editor** with tabs (content, access, pricing,
  SEO) rather than scattered screens → course wizard + curriculum manager share
  one course shell (T-P3-02..04).
- Locked content always states **why** and **when** it unlocks ("Day 7",
  "complete previous lesson") → YG-GAP-LRN-001/002.
- AI panel opens **beside** the content, not over it → AI spec §15.
- AI answers carry a mistake disclaimer → our label "AI clarification —
  instructor material is official".

## 4. What Young Go has that LearnHouse docs do not describe

Parent-linked child accounts, Arabic-first RTL design, Egyptian payments
(Paymob + Instapay proof review), instructor commission ledger and
settlements, cohort capacity/waitlist, media protection tiers + watermark,
lesson-PDF-only AI with quote verification and quiz guard, legacy migration
reconciliation. These are Young Go-specific and fully specified in the PRD.
