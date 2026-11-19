# D-026 visual acceptance and future pages

The owner explicitly rejected the first implementation despite passing tests. The approved reference is calm-workspace-approved-2026-11-17.png. The rejected implementation is preserved next to it as rejected-workspace-before-detail-pass-2026-11-17.png. Do not copy or bless the rejected typography, oversized sidebar, floating tabs, separate toolbar rows, or thin card details.

## Binding acceptance

Every user-facing change must be compared visually with its reference at the same viewport, locale, theme and representative data. A similar palette, cards existing, axe passing, or a green test suite is not visual acceptance. Any unexplained visible mismatch remains rework; do not report complete from technical checks alone. The original mockup caption is outside the product canvas. Sample counts, names and unsupported business actions are not permission to fabricate production data or add fake controls.

For new page archetypes, establish a reference consistent with D-026 before implementation; implement through shared tokens/native components, then inspect actual browser screenshots against it. Root owns final visual judgment. Do not turn the current output into its own reference just to pass. Record intentional design changes in the Decision Register before changing reference or baselines.

## Owner decision D-028, 2026-11-19

Tasks with verified functionality can support downstream implementation while visual fidelity remains open. Record `G7 VISUAL DEFERRED` in PROGRESS.md with technical evidence, screenshots and remaining visual differences. The deferred task stays in review. Keep functional browser and accessibility checks and use the unchanged reference at visual acceptance.

## Fixed shared measurements

See visual-contract.json: reference viewport1568x1003; operational sidebar268px; topbar70px; page inset30px; main/aside tracks2.22:1; table/card gap16px; heading36px; body18px / table16px; white cards16px radius, subtle boundaries; functional controls44px minimum. The users reference geometry is checked from actual browser bounding boxes, not just CSS variable values.

## Details that must match

- Arabic letterforms, weights, heading/body/metadata hierarchy and readable field labels.
- Brand lockup, sidebar width, icon stroke and placement, active tint, topbar/account rhythm.
- One summary strip with correctly grouped labels/values/icons and short centered separators.
- Tabs, search, role selector and filter action inside one table card, without floating controls or surplus rows.
- Table row height, circular initials, name weight, LTR emails, compact dates, meaningful status/role badges and quiet row menus.
- Side-card width, heading/action alignment, meters, activity text and timestamps.
- Dialogs, permission editor, dropdowns, empty/loading/error/success/focus states, login and panel landing pages share the same visual vocabulary.
- Mobile is designed structurally: no page overflow, contained table scroll, useful summary density, table before optional side cards, comfortable controls.
- Dark mode is designed with the same hierarchy; it must not alter the approved light default or leak into the other theme.

## Reuse and verification

Use WorkspaceListRecords for every resource list. Compose summaries and native Section/View cards, use native tables/filters/actions and schema forms. Existing global theme styles also cover default native Page/Section/form/modal components. Do not add page-specific palettes or default unstyled Filament screens. TABLE_WORKSPACE.md shows the exact API.

The architecture check rejects resource lists that bypass WorkspaceListRecords. The resource screenshot test discovers registered resources rather than keeping a closed hand-written list. The complete panel login/dashboard matrix, keyboard checks, local-font test, real summaries/permissions, and native action regressions run alongside visual comparison. These are guards, not a substitute for looking at the screenshots. Any newly registered page must add or join the applicable visual/interaction matrix, including empty and populated states.

## Owner clarification, 2026-11-17

Full visual matching is required for every existing and future page, including component details and interaction states. Match the reference, not merely its direction. Never describe partial matching as complete; list every remaining discrepancy with its screenshot evidence. The approved raster does not identify its source font; Noto Sans Arabic is the locally bundled rendering choice selected by direct comparison, not a claimed identification of the raster font.
