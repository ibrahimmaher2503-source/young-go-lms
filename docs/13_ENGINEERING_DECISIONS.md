# YOUNG GO LMS --- ENGINEERING DECISIONS (ED log)

**Version:** 1.0 · **Date:** 16 November 2026 · **Author:** technical
review (pack v3.2)\
**Status of every ED:** RECOMMENDED-DEFAULT — binding for agents, reversible,
does not override any owner decision (D-*) or OPEN item (O-*). Changing an ED
requires a new entry here (`ED-xxx supersedes ED-yyy`) and a Decision Register
note.

Format: **Context → Decision → Alternatives rejected → Consequences →
Verification.**

---

### ED-001 — Laravel modular monolith, single tenant
- **Context:** PRD R-001/R-003; LearnHouse uses separate Next.js/FastAPI/collab apps.
- **Decision:** One Laravel app with domain modules (`app/Domain/*`), Filament panels, Livewire student UI, versioned JSON API only where needed (AI assistant, webhooks, future mobile). Single tenant (Young Go). Users may hold multiple roles.
- **Rejected:** SPA + separate API (two deploys, duplicated validation); multi-tenant org model (PRD §3.2).
- **Consequences:** One codebase, shared Actions for every entry point; API added deliberately per feature.
- **Verification:** arch tests (Conventions §4).

### ED-002 — Content model: Course → Section → CurriculumItem
- **Context:** PRD §9.5 and Arch §5.1 say "learning items may be lesson, live session, quiz, assignment or resource"; v3.1 tasks ordered lessons and quizzes separately (ambiguous ordering).
- **Decision:** `curriculum_items` (course_id, section_id, sort, itemable_type, itemable_id, status draft/published, visibility, unlock rule) is the **only** ordering source. Typed records: `lessons`, `quizzes`, `assignments`, `live_sessions`. Lessons have no own sort column.
- **Rejected:** separate sort columns per type (ordering drift); one giant table for all types (sparse columns).
- **Consequences:** player, progress, completion and migration iterate one ordered list. Supersedes the curriculum parts of T-P3-04/T-P3-11 in v3.1.
- **Verification:** test that player order == curriculum manager order for mixed item types.

### ED-003 — Lesson rich content as restricted structured blocks
- **Context:** text lessons need richer content; LearnHouse uses a Tiptap JSON block editor with many blocks including AI-generated HTML.
- **Decision:** Use Filament's rich editor (Tiptap-based in current Filament; confirm in T-P0-02) storing JSON. Allowed blocks: paragraph, headings 2–4, lists, bold/italic/link, quote, callout (info/tip/warning), image (from library), divider, table, allowlisted embed. Rendered server-side through one sanitizer. No raw HTML, no script, no AI-generated blocks.
- **Rejected:** Markdown-only (weak for non-technical instructors); full block editor clone (scope).
- **Consequences:** one renderer component `x-learning.rich-content`; RTL handled by `dir="auto"` per block.
- **Verification:** XSS test fixtures; rendering snapshot AR/EN.

### ED-004 — Progress: course_progress (run) + lesson_progress (step)
- **Context:** LearnHouse Trail/Run/Step; v3.1 had lesson_progress only, course % computed.
- **Decision:** `course_progress` per learner-course (percent, completed_items, total_required, last_item_id, last_activity_at, completed_at) updated by events; `lesson_progress` per item. Both keyed by learner + course (entitlement-independent so progress survives renewal).
- **Consequences:** fast dashboards; resume uses `last_item_id`; YG-GAP-SUB-002 satisfied.
- **Verification:** invariant: progress recalculation is idempotent.

### ED-005 — Layered learning access in one place
- **Decision:** `EntitlementService::canAccessItem(learner, item)` evaluates in order: course published → item published → **entitlement** → cohort membership (if item targets cohort) → **sequence lock** → **drip date** → preview override. Returns `AccessDecision { allowed, reason_code, unlock_at? }` used by UI to show the reason.
- **Rejected:** checks scattered in controllers.
- **Verification:** dataset tests per layer; UI shows localized reason per `reason_code`.

### ED-006 — Translations in JSON columns
- **Context:** Arch §4 listed `course_translations`; Conventions chose spatie translatable.
- **Decision:** JSON translatable columns for all bilingual content (supersedes `*_translations` tables in Arch §4). Locale slugs stored per locale.
- **Consequences:** simpler Filament forms (`TranslatableTabs`); adding a locale adds a JSON key.
- **Verification:** translation completeness check covers model fields marked required per locale.

### ED-007 — Video delivery
- **Decision:** T1 playback through `VideoDeliveryProvider`. Preferred: a private streaming provider with signed HLS (O-005). Fallback driver: S3-compatible storage + queued ffmpeg HLS transcoding + signed segment URLs. YouTube/Vimeo/Drive drivers for T2/T3 per tiers.
- **Consequences:** app servers never stream large MP4s directly for paid content at scale.
- **Verification:** provider contract tests with fakes; signed URL expiry test.

### ED-008 — Captions: manual VTT, no AI captions
- **Decision:** Optional subtitle tracks (`.vtt`, AR/EN) per video lesson; player shows CC toggle. No AI-generated captions (outside D-017..D-021).
- **Verification:** upload validation + player track render test.

### ED-009 — AI assistant scope vs LearnHouse "Ask AI"
- **Decision:** Keep LearnHouse's placement (panel beside the lesson) and starter chips idea; restrict source to the approved lesson PDF (D-018); no Copilot, no cross-course answers, no generation tools, no "generate flashcards/summary" prompts; starter and follow-up chips only from approved key terms.
- **Verification:** AI spec §21 acceptance + red-team set.

### ED-010 — AI quota reserve → commit → refund
- **Decision:** Before a provider call, reserve one unit of the learner's quota (atomic counter); commit on success; refund on provider error/timeout/guard rejection. Cache hits consume no quota.
- **Verification:** tests for each path.

### ED-011 — Vector storage only when needed
- **Decision:** Full-context default (AI spec §5). Chunked mode uses pgvector if PostgreSQL is chosen, else a vector table with embeddings stored as binary + cosine in PHP for small per-lesson sets (scope is one lesson, so brute force is acceptable).
- **Consequences:** DB choice (O-004) is not driven by AI.

### ED-012 — Sellables instead of a products table
- **Decision:** `Course`, `Bundle`, `SubscriptionPlan` implement `Sellable` (price for market, entitlement grants, ledger rule). Orders reference sellables polymorphically with price snapshots.
- **Verification:** one checkout flow handles all three (feature tests).

### ED-013 — Cohorts are the only learner groups
- **Decision:** No generic user groups. Cohorts handle live scheduling, capacity and targeted content/announcements.
- **Consequences:** simpler permissions; revisit only on owner request.

### ED-014 — Explicitly out of scope (from LearnHouse feature set)
Real-time collaborative editing/boards, playgrounds, code execution, podcasts, communities, SCORM/xAPI, MCP server, public SDK/embeds, invite codes, custom signup fields, ungraded inline quiz blocks, multi-organization. Any request to add them needs an owner decision.

### ED-015 — Feature flags and settings
- **Decision:** Laravel Pennant for flags (`ai.assistant`, `ai.minors`, `markets.sa_checkout`, `subscriptions.auto_renew`, `notifications.whatsapp`, `payments.paymob_live`, `ereceipt.enabled`); spatie/laravel-settings for typed admin settings. Gated flags require super admin + recorded approval reference.
- **Verification:** flag gate tests; doctor command lists flag states.

### ED-016 — Staff two-factor authentication
- **Decision:** TOTP 2FA mandatory for super_admin, admin, finance; optional for instructors and parents; recovery codes; enforcement middleware on panels.
- **Verification:** panel access denied without 2FA for those roles.

### ED-017 — Child notifications and recovery
- **Decision:** Child without email receives in-app notifications; email notifications for the child are sent to linked verified guardians (localized, child name in subject). Parent can reset a linked child's password (audited). Parent email must be verified before creating children.
- **Verification:** routing tests; audit test.

### ED-018 — Slugs and URLs
- **Decision:** Locale-prefixed public URLs (`/ar/...`, `/en/...`); Arabic slugs in Arabic UTF-8 (percent-encoded in transport), English slugs ASCII; old URLs via redirect table (migration).
- **Verification:** hreflang/canonical tests.

### ED-019 — Phone numbers
- **Decision:** Store E.164 using libphonenumber (laravel-phone); default regions EG (active), SA (prepared); display national format per locale; `dir="ltr"`.

### ED-020 — Testing strategy: acceptance tests first
- **Decision:** For every task the `test-writer` writes failing acceptance tests from the task's acceptance list **before** implementation; implementation is done when they pass. Tests carry requirement tags (`->group('YG-GAP-FAM-001')`, task tag `->group('T-P1-05')`). CI reports coverage; Entitlements/Commerce/InstructorFinance/AI ≥ 90%.
- **Verification:** G10 requirement coverage.

### ED-021 — Observability
- **Decision:** Laravel Horizon (queues), Laravel Pulse (app metrics), structured JSON logs, error tracking through a reporter adapter (provider OPEN; Sentry-compatible recommended), uptime monitor external.

### ED-022 — Operability commands
- **Decision:** `app:doctor` (DB, Redis, queues, scheduler heartbeat, storage write/read, mail transport, provider configs present, flags, pending migrations) exits non-zero on failure; `app:demo-reset` restores seeded demo data on non-production environments only.

### ED-023 — One UX system
- **Decision:** All screens follow the page anatomy, interaction patterns and journey specs in `15_UX_FLOWS_AND_PATTERNS.md` and the tokens/components in `11_DESIGN_SYSTEM.md`. No screen-specific patterns without a registry entry.

### ED-024 — Authoring defaults
- **Decision:** New curriculum items start `draft`; bulk publish/unpublish per section and multi-select; "View as learner" preview for any item (no progress written).

### ED-025 — Sequential locking and drip
- **Decision:** Per course: `sequence_mode` free | sequential (must complete previous required item). Per item: unlock rule none | absolute date | relative days after entitlement start (or cohort start). Locked items show reason + unlock date.
- **Verification:** ED-005 dataset tests.

### ED-026 — Content library
- **Decision:** Library of reusable media/resources owned by academy or instructor; items can be attached to many lessons by reference; deletion blocked while referenced; tiers apply.

### ED-027 — Learner 360 and support view-as
- **Decision:** Staff with permission see a learner record (profile, guardians, entitlements, progress, orders, help requests status, devices). Support "view as learner" is **read-only**, audited, shows a persistent banner, blocks all writes and AI calls.

### ED-028 — Requirement traceability
- **Decision:** `docs/TRACEABILITY_MATRIX.md` (generated) maps every PRD section/ID, owner decision and ED to tasks and tests. Gate G10 fails if an in-scope requirement of a completed phase has no tagged test or evidence.

### ED-029 — Verification wave by independent subagents
- **Decision:** After implementation, the orchestrator runs `prd-auditor` (scope + coverage), `qa-verifier` (runs journeys in a browser, tries edge cases) and, for UI tasks, `ux-reviewer` (scores screenshots against design system and UX heuristics). High-risk tasks also get `reviewer`. Any FAIL returns to rework.

### ED-030 — Performance budgets
- **Decision:** Public/student pages on mid-range mobile over 4G: LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms; JS ≤ 120 KB gzip per page excluding player provider SDK; images responsive WebP/AVIF; dashboards paginate ≤ 25 rows default.

### ED-031 — Single sanitizer and file safety
- **Decision:** One HTML sanitizer service for rich content, AI output rendering (plain text + page chips only) and help-request messages; uploads stored privately with random names; images re-encoded on upload (strips EXIF/GPS — important for children's photos).

### ED-032 — Time and schedule rules
- **Decision:** Store UTC; learner timezone default Africa/Cairo for EG, Asia/Riyadh for SA; live sessions keep the instructor-entered timezone for display; reminders computed in UTC.
