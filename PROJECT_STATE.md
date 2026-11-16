# Young Go LMS --- PROJECT STATE

**Updated:** 2026-11-16 (pack v3.3)\
**Stage:** Product specification and developer handoff; no new
application development verified.

## Confirmed owner direction

-   Custom LMS from scratch.
-   Preserve current Young Go logo/brand and owner-stated
    `youngschool.com` domain; verify DNS before cutover.
-   Migrate all existing courses and related course content/assets.
-   All ages; configurable age suitability.
-   Parent/guardian account linked to child accounts.
-   Hybrid academy-owned and independent instructor course model.
-   Individual course purchases, bundles, monthly and annual
    subscriptions.
-   Egypt active; Saudi technically prepared but disabled.
-   Arabic and English from first release; extensible locales.
-   Central payment collection by Young Go; instructor settlements
    afterward.
-   Simple commission rules: percentage on individual sales, bundle
    course allocation + percentage, fixed amount per
    student/course/subscription period.
-   AI only for recorded lessons, based on current lesson curriculum
    PDF, concise answers, unavailable during live.
-   Unresolved AI request goes to instructor with admin copy; replies
    inside platform.
-   SKIT for admin/instructor panels; project separate from YFS.

## Reviewed methodology (v3.3)

This deliverable is a specification and execution-methodology pack, as clarified by the owner
on 2026-11-16. No application source is expected here. All implementation tasks remain `todo`.
The review addresses requirement ownership, task dependencies, policy gates, architecture boundaries,
AI grounding and independent verification. Planned coverage is separate from future acceptance evidence.

Read `docs/16_REVIEW_FINDINGS_AND_DECISIONS.md` for decisions, review evidence and remaining gates.
The incomplete audit-only Python validator and headless launcher were removed; neither is part of
this documentation deliverable. Interactive execution uses `agents/LOOP_PROMPT.md` when implementation
is requested. No provider, runtime, framework version, repository or deployment is asserted verified.

## Future implementation

Start with T-P0-01 inventory and dependency-ready tasks. Missing access is recorded accurately;
it does not make this specification review incomplete. Commercial/privacy/legal activation decisions
remain in `docs/07_DECISION_REGISTER.md`. Preserve legacy production until authorized cutover.

## Repository delivery — 2026-11-16

Private GitHub repository created at https://github.com/ibrahimmaher2503-source/young-go-lms.
The repository contains the reviewed specification pack; this is not application deployment.
