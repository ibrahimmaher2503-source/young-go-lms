# YOUNG GO LMS --- LEARNHOUSE REFERENCE STUDY

**Date:** 16 November 2026 · **Scope:** documented product patterns, not a clone.
D-001 keeps Young Go independent of packaged LMS code. No LearnHouse code,
branding or assets are copied. The upstream core is AGPL-3.0; Enterprise
features have separate licensing. This project boundary is not a claim that
AGPL reuse is inherently prohibited. See the [upstream repository and license](https://github.com/learnhouse/learnhouse).

## 1. Evidence and limits

Official documentation was read on 16 November 2026. These are documented
behaviors, **not runtime observations**: no LearnHouse account, local build,
mobile interaction, accessibility audit or rendered screenshot comparison was
performed. Documentation images and captions do not establish visual acceptance.

| Source | Documented evidence | Young Go design decision (not an upstream fact) |
|---|---|---|
| [Architecture](https://docs.learnhouse.app/developers/architecture) | Separate Next.js, FastAPI, collaboration and CLI apps; Playwright tests | Keep the Laravel modular monolith and shared UI tokens |
| [Chapters and activities](https://docs.learnhouse.app/platform/courses/chapters-and-activities) | Content tab organizes course chapters and typed activities | Use section/item hierarchy with explicit publication and access states |
| [Editor](https://docs.learnhouse.app/platform/editor) | Block editor, activity switcher and save-before-switch prompt; editor is desktop-only | Keep one course context and restricted blocks; do not inherit a desktop-only assumption for Young Go tasks requiring mobile support |
| [Trails](https://docs.learnhouse.app/platform/courses/trails) | Course/activity progress and certificates; quitting permanently deletes progress | Adopt visible progress; reject destructive quit behavior and retain progress when leaving a screen |
| [AI for students](https://docs.learnhouse.app/platform/ai/for-students) | Activity-scoped assistant plus separate cross-course agents and starter questions | Use the placement idea only: current approved lesson PDF, validated answers and approved key terms; no cross-course assistant |
| [Payments](https://docs.learnhouse.app/platform/payments) | Products connect prices to access; paid access follows cleared payment; self-hosted payments require Enterprise | Explain price, learner and access together; use Young Go gateways and explicit pending/rejected/confirmed states |

The decision inventory below preserves Young Go planning references. Entries
beyond this evidence table are proposed adaptations or exclusions, not an
assertion that every upstream feature or implementation detail was audited.

## 2. Pattern decisions for Young Go

| # | LearnHouse pattern | Decision | Why / how in Young Go | ED | Where |
|---|---|---|---|---|---|
| 1 | Multi-app (Next.js + FastAPI + collab) | **Reject** | Laravel modular monolith fits PRD R-001/R-003, team skill and one deploy; API v1 kept for AI/mobile later | ED-001 | Conventions §2 |
| 2 | Course → Chapter → Activity tree | **Adopt (adapted)** | Course → Section → **CurriculumItem** (single ordered table, typed items: lesson, quiz, assignment, live session) | ED-002, ED-024 | Arch §11.1, T-P3-04 |
| 3 | Activity as JSON block document | **Adapt** | Rich-text lessons stored as sanitized structured JSON with a **restricted block set** (no executable HTML, no AI magic blocks) | ED-003 | T-P3-15 |
| 4 | Trail → Run → Step | **Adopt (adapted)** | `course_progress` (run per learner-course) + `lesson_progress` (step) | ED-004 | Arch §11.1, T-P4-04 |
| 5 | Per-item access locks + drip | **Adopt (adapted)** | Explicit learner/public-preview/author-preview/support-view-as modes; normal access checks publication, qualifying grant, cohort, sequence, drip and operation window. Preview is never a final override and cannot write progress or call AI | ED-005, ED-025, ED-035 | T-P4-09 |
| 6 | Draft by default, bulk publish, preview as learner | **Adopt** | New items default draft; bulk publish/unpublish; "view as learner" preview | ED-024 | T-P3-04, T-P4-10 |
| 7 | Library of reusable resources | **Adopt** | Instructor/academy content library reusable across lessons | ED-026 | T-P3-13 |
| 8 | Video: HLS transcoding job, captions | **Adapt** | T1 through the selected signed-HLS provider boundary (O-005); implement a local transcoding fallback only if the selected delivery approach requires it, not speculatively; **manual VTT captions AR/EN**; no AI captions | ED-007, ED-008 | T-P3-14 |
| 9 | Ask AI per activity | **Adopt (restricted)** | Same placement idea (panel beside lesson) but source = **approved lesson PDF only**, short answers, cited pages, help escalation | ED-009 | AI spec v3 |
| 10 | Ask AI starter prompts ("Generate flashcards…") | **Adapt** | Starter chips = approved key terms; follow-up chips only from those terms; no generation prompts (D-018) | ED-009 | T-P7-03 |
| 11 | Copilot across all courses, teacher generation tools, magic blocks, AI course creator, AI captions | **Reject** | Outside owner-approved AI scope (D-017..D-021) | ED-009 | — |
| 12 | Credits reserved then refunded on failure | **Adopt** | Quota reserve/commit/refund so failures never consume learner quota | ED-010 | T-P7-02 |
| 13 | pgvector for embeddings | **Adapt** | Full-PDF context by default; vectors only for oversized PDFs; DB choice stays O-004 | ED-011 | AI spec §5 |
| 14 | Products = price + what it unlocks | **Adopt (adapted)** | No separate products table: allowlisted course/bundle/plan order references and snapshots; checkout Actions call owning domains. `Sellable` is optional only for a demonstrated shared read boundary, never model-owned grants or ledger effects | ED-012, ED-033 | T-P8-01 |
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
- AI panel sits beside content on wide screens; on mobile use an accessible
  sheet with the lesson title, PDF context and a clear return action → AI spec §15.
- AI answers carry a mistake disclaimer → our label "AI clarification —
  instructor material is official".

## 4. Young Go requirements that take precedence

Parent-linked child accounts, Arabic-first RTL design, Egyptian payments
(Paymob + Instapay proof review), instructor commission ledger and
settlements, cohort capacity/waitlist, media protection tiers + watermark,
lesson-PDF-only AI with quote verification and quiz guard, legacy migration
reconciliation. These remain governed by the Young Go PRD; this study does not establish that other products lack them.
