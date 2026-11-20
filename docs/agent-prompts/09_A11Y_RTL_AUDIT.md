# 09: Accessibility and RTL audit across the whole app

> Read `docs/agent-prompts/00_COMMON.md` first.

## Scope
Every page in `docs/reviews/UI_PAGE_INVENTORY.md`: public, learner, parent, instructor and admin/Filament. Check each one in Arabic and in English, at 390px and at 1440px, and in both light and dark themes if the page has both.

## Checks
- axe-core: no serious or critical issues.
- Keyboard: every page can be used by keyboard alone, focus is always visible, modals and menus do not trap focus, and there is a skip link.
- Contrast: the token pairs already pass (34 pairs). Check how the colours are actually used on the pages.
- RTL:
  - icon direction (arrows)
  - physical classes that `app:check-rtl` misses (inline styles, SVG)
  - alignment of mixed numbers and dates
  - `dir` on inputs that hold mixed text
- Forms: labels, readable error messages linked with `aria-describedby`, and `autocomplete`.
- Video and player: captions, keyboard controls, and `prefers-reduced-motion`.

## Fixes
Fix the clear problems directly, using design-system components, with one commit per area. Report anything that needs a design decision instead of fixing it.

## Output
- `docs/reviews/A11Y_RTL_AUDIT_2026-11-xx.md`.
- A test prompt with axe checks for the main pages: `docs/test-prompts/A11Y.md`.
- A summary in Arabic.
