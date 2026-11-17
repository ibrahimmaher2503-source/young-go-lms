# D-026 workspace implementation and review

## Owner requirement

Every existing and future page must visually match its approved reference. The approved image and rejected implementation are preserved in docs/design-system/references; VISUAL_ACCEPTANCE.md is binding. Technical passes alone cannot establish complete visual matching. No fabricated production records, counts or unsupported actions were added to imitate the mockup.

## Implemented locally

Shared native Filament login/dashboard for all three panels; WorkspaceListRecords for all three current admin resources; one typography/token layer, locally served Noto Sans Arabic, local initial avatars; integrated tabs/search/role/status filters; actual permission-scoped summaries, role percentages and audit activity; inset rounded table header, compact dates, native action menus and human permission/event labels. Underlying authentication, authorization, audited Domain Actions and CSV permission checks remain in place.

The owner correction led to root-owned refinements throughout the existing staff surface. Read-only Luna reviews identified mobile clipped labels and absent scroll cues. These were repaired with wrapping/native helpers, complete tabs/actions and a translated table-scroll hint. A measured eight-pixel overflow came from native icon-button negative margins; the shared reset preserves a 44px target. Native modal descriptions now use the tested semantic muted token.

## Fresh verification

- composer qa: Pint and Larastan PASS; 204 tests / 1077 assertions PASS, plus one explicit downstream I02 TODO.
- Complete tests/Browser: 78 tests / 748 assertions PASS. Covers AR/EN, light/dark, 390px/1440px and the 1568px reference case; actual local font loading, login/dashboard, all registered resources, serious/critical axe checks, modal forward/reverse Tab, Escape and focus return.
- npm run build PASS; 34 semantic contrast pairs PASS.
- Translation parity, RTL utilities and T-P1-08 requirement-tag coverage PASS. Tag coverage does not establish missing business acceptance items.
- Actual reference geometry: sidebar268px, topbar70px, heading36px, summary102.375px, table843.875px, aside380.109px; desktop table fits without scrolling. Contract tolerance is2px.
- Local preview /admin/login returned HTTP200 with the shared layout and current font.

Reproducible logs are under agents/logs/workspace-{qa,all-browser,build}-current.txt; screenshots are in tests/Browser/screenshots/T-P1-08, including surfaces and permission-editor variants. Earlier failed layout diagnostics predate the successful final matrix and are not current acceptance evidence.

## Independent review and limits

Luna source/QA review found no remaining blocker in the reviewed auth/scoping/layout/modal criteria. Luna visual re-review confirmed the identified mobile blockers cleared. Its screenshot-only applicable score is4.0/5; the formal4.5 average threshold is NOT demonstrated. Do not rewrite this as full UX acceptance or claim100% raster equality. The exact source font cannot be identified from the raster. Native pagination follows actual data/pages, so its single-page footer differs from the mockup. Root reviewed refreshed desktop/mobile, login/dashboard and modal screenshots; formal visual closure stays in review.

Top-level staff lists omit breadcrumbs to match the newer owner reference, superseding the generic older breadcrumb guideline. No new unimplemented module links, notification controls or sample counts were added. The complete product PRD is not implemented by this UI work; T-P1-08 approval action, age cutoff approval and downstream LMS tasks retain their recorded status. No production deployment or live-provider verification was performed.

## P1 closure follow-up — 2026-11-17

- The reference remains `calm-workspace-approved-2026-11-17.png`. Compared the current Arabic light users screenshot in a 1568 × 1003 browser viewport, aligning the reference below its 46px presentation caption. The sidebar, top bar, heading, summary and table/aside widths align within the existing geometry tolerances. The 56px reference row measurement now has a browser assertion with a 4px tolerance. Compact native row actions keep their 44px target.
- Compared the English desktop table and Arabic and English light/dark mobile examples against D-026 and the shared component pack. The table actions and date can extend beyond the visible area at 1440px, especially in English. The bilingual scroll cue now appears at any size when the table overflows. The scroll region remains keyboard-reachable after Livewire search updates; the account control has room and a 44px target at 390px.
- The admin dashboard and native login use shared surfaces. The rendered dashboard has the permission-scoped P1 summary and resource links; the activity chart, queues and business KPIs illustrated in ref 01 belong to T-P9-07. The reference's notification and create-user controls do not authorize new functionality.
- The follow-up went through three independent screenshot reviews: 4.0, 4.2 and **4.33/5**. The final review confirmed the joined tab rail, status dots, alternate avatar tones, full fixture emails at 1568px, equal meter widths and inline “View all.” The final score is below the 4.5 threshold. Remaining differences include softer navigation/account colors and icon emphasis, lighter Arabic heading/sidebar weights, stronger field borders, role-card and pagination spacing, the wrapping English role-card heading at 1440px and partial email text in the initial mobile scroll position. Full visual inspection of interaction states remains open. The task reached the verification-cycle limit in ORCHESTRATOR §7.
- Verification at `cef263a` plus the scoped P1 diff in the managed worktree: the full staff matrix, surfaces and font suite passed 59 tests (1,029 assertions) before the final detail update. The final 10-case staff rerun passed 637 assertions, including direct measurement of 59–59.5px rows, no table overflow in the 1568 case, consistent role meter widths and visible fixture emails. The independent scoped QA source review passed after rechecking the resize and Livewire lifecycle. See [1568px reference capture](../../tests/Browser/screenshots/T-P1-08/reference-pass/staff-users-ar-light-1568.png), [mobile dark English](../../tests/Browser/screenshots/T-P1-08/reference-pass/staff-users-en-dark-390.png), [desktop light English](../../tests/Browser/screenshots/T-P1-08/reference-pass/staff-users-en-light-1440.png) and [measured geometry](../../tests/Browser/screenshots/T-P1-08/reference-pass/workspace-reference-metrics.json). Shared-checkout verification was interrupted by an in-progress ManualGrantResource registration, so the test results apply to the isolated snapshot.
- The independent PRD audit confirmed the scoped access, role and CSV evidence. It found a task step for shipped navigation grouping that needs reconciliation with the approved flat sidebar. The shared approve/reject action has since shipped and passed its feature tests. See [P1 milestone evidence](../evidence/p1-milestone/README.md#closure-follow-up-2026-11-17) for the remaining phase gates.
