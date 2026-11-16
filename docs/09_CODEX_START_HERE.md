# YOUNG GO LMS --- CODEX START HERE

> **v3.3:** the full execution loop is now defined in `AGENTS.md` (Codex) / `CLAUDE.md` (Claude Code) and the `agents/` folder. Start with `agents/README.md`. The instructions below remain valid and are implemented as tasks T-P0-01 ... in `agents/tasks/P00.md`. Paymob is the approved Egypt gateway (D-025).

**Use this as the first implementation instruction after the owner
supplies/authorizes the repository and environment.**

> Build **Young Go LMS** as a fully custom LMS from scratch. Do not
> install, fork, theme or base business logic on Academy LMS, EduEx,
> WordPress LMS or any packaged LMS. Read `README_AR.md` and all files
> in `docs/`, especially the Master PRD, DMC, Phases/Milestones Roadmap,
> Delivery Acceptance Checklist, Architecture/Data/API, AI Lesson
> Assistant Spec, Decision Register and Legacy Migration Plan. Inspect
> the actual repository and CodeGraph if available; if CodeGraph is
> unavailable, continue with fast repository inspection. Preserve the
> existing Young Go logo, brand identity and owner-stated
> `youngschool.com` domain; do not modify the live legacy site during
> development. Implement phases as functional vertical slices, beginning
> with the real application foundation, identity/RBAC, bilingual SKIT
> shell, course catalog, course builder, unified entitlement and an
> enrolled student learning path. Support all-age course suitability and
> parent-linked child accounts. Build recorded/live/hybrid learning and
> the lesson curriculum PDF into the course model. AI must remain
> restricted to short explanations from the current recorded lesson's
> PDF and unavailable during live sessions; unresolved requests go to
> the assigned instructor with an admin copy and an in-platform reply
> thread. Support individual sales, bundles, monthly/annual
> subscriptions and central collection by Young Go; use the simple
> approved instructor commission rules and keep Saudi checkout disabled.
> Migrate all legacy courses and course content after source inventory
> and staging reconciliation. Use the recommended stack only after
> runtime compatibility is verified. Keep implementation
> production-first, use one concise English instruction per coherent
> batch, one focused validation per milestone, and reserve final deep QA
> plus backup/restore verification for the final phase without skipping
> critical security/payment checks. Update project state, handoff,
> changelog and decision register after every milestone. Never invent
> repository, host, credentials, gateway, provider or deployment
> evidence.

## First milestone instruction (future implementation only)

> Inspect the authorized repository, runtime, current branch and
> CodeGraph (if available). Establish actual environment and
> source-of-truth docs. Then select dependency-ready tasks from the task graph; the first working custom
> application slice spans P0–P4, not a single P0/P1 task: bootable app, secure
> authentication, initial RBAC, bilingual locale foundation, SKIT admin
> shell, brand-preserving public shell and a real database-backed course
> catalog route. Do not touch legacy production. Use safe migrations and
> document verified environment facts. Complete one focused validation,
> update continuity files and report the exact next milestone.

## Documentation-only review

When asked to review this pack, validate requirement ownership, decision precedence, task dependencies,
acceptance evidence contracts and cross-document consistency. Do not create an application or treat missing
Laravel/hosting/credentials as a documentation defect. See docs 16 and the staged verification gates.

## Implementation gates

-   Do not activate payment collection until Egyptian legal
    seller/invoice details and gateway credentials are approved.
-   Keep Saudi checkout disabled.
-   Do not send minor learner data to an AI provider until provider
    terms, retention, data region and guardian-consent policy are
    approved.
-   Do not enable lesson AI without current lesson's approved curriculum
    PDF and entitlement checks.
-   Do not switch the live domain until migration, acceptance,
    backup/restore and rollback are signed off.
