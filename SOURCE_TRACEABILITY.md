# Source Traceability --- Young Go LMS v3

  ----------------------------------------------------------------------------------------------------
  Source                                                           Use in this pack
  ---------------------------------------------------------------- -----------------------------------
  `Yango_Go_PRD.pdf` (June 2026, 21 pages)                         Product vision, personas, journeys,
                                                                   public website, accounts, course
                                                                   creation, recorded/live learning,
                                                                   student/instructor dashboards,
                                                                   assessments, payments,
                                                                   notifications, CMS, reports,
                                                                   acceptance and risks

  `Yango_Go_Full_Technical_PRD_Implementation_Specification.pdf`   RBAC, detailed functional
  (4 June 2026, 16 pages)                                          requirements, recorded/live
                                                                   requirements,
                                                                   quiz/assignment/certificate rules,
                                                                   commerce, events, database/API
                                                                   examples, security, deployment and
                                                                   phases

  November 2026 PRD v2                                             Stable requirement IDs, acceptance
                                                                   criteria, business invariants,
                                                                   reporting, non-functional
                                                                   requirements and AI-aware
                                                                   architecture baseline

  November 2026 DMC                                                Project separation, SKIT, modular
                                                                   monolith recommendation, Codex
                                                                   execution rules, continuity and
                                                                   deployment safety

  November 2026 AI specification                                   Security/retrieval concepts;
                                                                   feature breadth superseded by
                                                                   owner's simplified lesson-PDF AI
                                                                   decision

  November 2026 architecture/data/API                              Indicative domain model, provider
                                                                   adapters, entitlement, event and
                                                                   API concepts

  Legacy LMS screenshots (15 Nov 2026)                             Sidebar modules, multi-step course
                                                                   wizard, bilingual fields,
                                                                   pricing/access mode, media sources,
                                                                   sections/lessons, quiz form, manual
                                                                   grants, course list and student
                                                                   player

  Owner decisions (15 Nov 2026)                                    Binding scope for brand/domain,
                                                                   course migration, all-age audience,
                                                                   guardians, hybrid instructors,
                                                                   monetization, Egypt/Saudi,
                                                                   languages, central collections,
                                                                   commissions, simple AI, escalation
                                                                   and in-platform communication
  ----------------------------------------------------------------------------------------------------

## Preservation and supersession

-   June PRDs' LMS core capabilities remain carried forward unless
    explicitly changed.
-   Old PRD's AI-out-of-scope statement is superseded by approved lesson
    assistant.
-   Old PRD's future subscription/bundle recommendations are superseded
    by approved individual + bundle + monthly/annual model.
-   Old single-academy-only recommendation is superseded by approved
    hybrid academy/independent instructor model.
-   Earlier broad AI tutor proposal is narrowed: recorded lesson only,
    current curriculum PDF only, short clarification, unresolved-help
    escalation.
-   Earlier assumption that parent accounts are future scope is
    superseded by approved parent-linked child account model.
-   Owner confirmed all existing courses and related course content must
    migrate. Broader personal/financial history migration remains
    subject to inventory and scope sign-off.

## v3.1 additions (16 Nov 2026)

  Source                                   Use
  ---------------------------------------- -----------------------------------------------
  v3.0 pack review (gap analysis)          30 gaps closed or tracked (`docs/10_GAP_CLOSURE.md`)
  Project lead decision (16 Nov 2026)      Paymob as Egypt gateway (D-025)
  Engineering recommendations              R-011..R-030, design system, code conventions, agent loop

Missing source files noted in v3.0: `README_AR.md` (now created in v3.1) and `Yango_Go_PRD.pdf` (June 2026, 21 pages) — not supplied in this pack; its content is carried through the v3.0 PRD consolidation.
