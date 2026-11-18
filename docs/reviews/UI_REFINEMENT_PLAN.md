# UI refinement implementation plan — 2026-11-17

Goal: inspect all currently built screens, then refine presentation through shared tokens and components while preserving all routes, forms, bindings, authorization and data attributes. The owner's supplied eight-wave plan and reference pack govern implementation; no alternative visual direction is introduced.

Architecture: retain native Filament tables/forms inside the existing workspace, and dedicated public/learner shells with existing Blade primitives. Fix shared causes before page-specific composition. No dependency, backend, schema, route or configuration changes. No AI changes.

Sources: owner request; D-026; Design System §§2/4/6; ED-020/ED-023; reference-pack IMPLEMENTATION.md and COMMON_COMPONENTS.md. The newer explicit owner request permits documenting missing dedicated references using existing shared patterns. It does not authorize new product features.

## Execution and evidence

- [ ] T-UI-00: inventory built pages/components; run unchanged browser tests and capture baseline in `UI_BASELINE/`; document gaps rather than treating historical screenshots as fresh evidence.
- [ ] T-UI-01: tokens and typography. Preserve the established palette and exact staff geometry. Introduce missing semantic control/typography/state tokens only when inspection establishes a need. Test computed values, both scripts, long labels and overflow before broad application.
- [ ] T-UI-02: primitives. Align cards and controls to existing 16px/10px contracts, fix input/error boundaries, focus/hover/loading/disabled presentation. Write a failing browser acceptance check before implementation; preserve all existing assertions.
- [ ] T-UI-03: shell/navigation. Inspect and correct layout with dedicated public/learner navigation; preserve destinations and interactive behavior.
- [ ] T-UI-04: learning components. Inspect curriculum/media/course/progress/live states, including locked/error/ended. Record functional defects separately.
- [ ] T-UI-05: learner and parent pages. Correct composition through the shared system; no new data requirements implemented.
- [ ] T-UI-06: public and identity pages. Cover currently built routes only; do not create a public course-detail route merely because a raster depicts one.
- [ ] T-UI-07: Filament theme and staff screens. Preserve native queries/tables/forms and permission-scoped numbers.
- [ ] T-UI-08: error pages. Keep session-independent error handling intact.

Each implementation wave: failing targeted check where applicable → minimal UI edit → build → unchanged browser suite → same-view after captures → root comparison → independent PRD/QA/UX review → focused commit. Present the two most consequential before/after examples and wait for owner feedback before continuing when a wave makes a large visual change, as requested.

Baseline ruling for T-UI-01: include the critical correction of existing status background tokens incorrectly used as live-card text (measured approximately 1.2:1). Reuse the existing `on-success`/`on-danger` foregrounds; no palette or state behavior changes. This is part of the shared semantic color audit, ahead of further learning layout work.

## Review focus

1. Light/dark invalid field outlines must be distinguishable against input surfaces, without using status background tokens as foregrounds.
2. Arabic labels must not clip; inspect 360/390/768/1024/1440/1920 widths and keyboard focus.
3. Existing `data-*`, wire bindings, form actions, names and accessible relationships must survive markup changes.
4. Fake reference actions/counts/imagery must not enter the product.
5. Shared CSS must not regress approved Filament geometry or change disabled/loading interaction.

## Final verification and reporting

Run build, all existing Browser tests, complete non-browser suite, Pint, PHPStan, translation/RTL checks, and doctor. Record exit codes and baseline failures. Save after screenshots under `UI_AFTER/`, compare against original references at comparable viewports, and retain G7 open for unexplained differences. `UI_REFINEMENT_REPORT.md` records per-page before/after, modified shared pieces, deferred backend requests and functional findings. Commits use the owner's `style(ui): ... [T-UI-xx]` format and requested co-author trailer; explicitly stage only this task's allowed paths.

Current workspace contains unrelated modified handoff/state/acceptance/evidence documents, a deleted review document and untracked feature tests. Preserve and exclude all of them. The owner allowlist excludes `agents/PROGRESS.md`; for this task the requested report carries evidence, while existing business statuses remain untouched (no false `done`).
