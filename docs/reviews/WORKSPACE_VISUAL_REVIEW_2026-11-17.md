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
