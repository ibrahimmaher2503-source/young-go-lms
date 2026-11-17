# YOUNG GO LMS --- DESIGN SYSTEM & VISUAL DIRECTION

**Document ID:** YG-DS-001 · **Version:** 1.0 · **Date:** 16 November 2026\
**Status:** RECOMMENDED (R-027). Brand values are filled from the current
Young Go site in task T-P0-04 (O-027). SKIT rules (D-022) apply to
admin/instructor/finance panels; this document provides the shared tokens
that both SKIT/Filament and the student UI consume.

------------------------------------------------------------------------

## 1. Principles

1. **Keep the brand, raise the craft.** Same logo, colors and identity;
   cleaner layout, better typography, real responsiveness (D-002).
2. **Creative, not childish.** Audience is all ages with a focus on young
   learners in acting, voice, drawing and animation. Use warmth, rounded
   shapes and expressive accents, but keep layouts calm and readable so
   adults and parents trust it.
3. **Arabic first, bilingual always.** Every screen is designed in RTL and
   LTR from the start. Never "flip later".
4. **One source of truth.** All colors, spacing, radii, shadows, type and
   motion come from tokens. No hard-coded values in views.
5. **Learning screens stay quiet.** The player and lesson views remove
   marketing noise; the content and next step lead.
6. **Accessible by default.** WCAG 2.1 AA (YG-GAP-A11Y-001).

## 2. Token architecture

Three layers. Views use only layer 3 (or layer 2 utility classes).

| Layer | Example | Rule |
|---|---|---|
| 1. Brand primitives | `--yg-brand-500: <extracted>` | Filled from current site; never used directly in views |
| 2. Semantic tokens | `--color-primary`, `--color-surface`, `--color-text-muted`, `--color-success` | Mapped to primitives per theme (light/dark) |
| 3. Component tokens | `--btn-primary-bg`, `--card-radius`, `--player-sidebar-width` | Defined per component, reference semantic tokens |

Implementation: CSS custom properties in `resources/css/tokens.css`,
exposed to Tailwind (v4 `@theme`) and to the Filament panel theme. One
file feeds both the student UI and SKIT/Filament.

### 2.1 Color

Fill in T-P0-04 from the live site (logo SVG, header, buttons, footer).

| Token | Purpose | Value |
|---|---|---|
| `brand-50 … brand-950` | Primary brand scale (11 steps, generated from the main brand color with OKLCH) | O-027 |
| `accent-50 … accent-950` | Secondary brand color (if the brand has one) for highlights, badges, illustrations | O-027 |
| `neutral-0 … neutral-950` | Text, borders, surfaces; slightly warm neutral | Recommended default warm gray |
| `success / warning / danger / info` | States | Standard accessible hues, each with 50/100/500/600/700 |

Rules:
- Text on any background must meet 4.5:1 (normal) or 3:1 (large/bold ≥ 18.66 px).
  The token build script checks every semantic pair.
- If the extracted brand color fails contrast for text/buttons, use a darker
  step of the same hue for interactive text and keep the original for large
  fills and illustrations. Never change the hue.
- Dark mode is required for SKIT (D-022); optional for the student UI
  (recommended later).

### 2.2 Typography

| Role | Arabic | Latin | Notes |
|---|---|---|---|
| UI + body | Brand font if it exists; otherwise **IBM Plex Sans Arabic** | **IBM Plex Sans** (matching family) | One family family across scripts keeps rhythm consistent |
| Display headings (public site) | Brand display font if any; otherwise same family, weight 700 | Same | Optional expressive font only for marketing hero |
| Numbers | Western digits `0-9` by default in both locales (configurable) | — | Consistent prices, dates, progress |

Scale (rem, base 16 px), line heights tuned for Arabic (taller):

| Token | Size | Line height AR / EN | Use |
|---|---|---|---|
| `text-xs` | 0.75 | 1.6 / 1.5 | captions, badges |
| `text-sm` | 0.875 | 1.7 / 1.5 | secondary text, tables |
| `text-base` | 1 | 1.8 / 1.6 | body |
| `text-lg` | 1.125 | 1.7 / 1.5 | lead |
| `text-xl` | 1.25 | 1.6 / 1.4 | card titles |
| `text-2xl` | 1.5 | 1.5 / 1.3 | section titles |
| `text-3xl` | 1.875 | 1.4 / 1.25 | page titles |
| `text-4xl` | 2.25–3 (fluid) | 1.3 / 1.15 | hero |

Rules: no letter-spacing on Arabic; no all-caps; avoid italic in Arabic;
max line length ~70 characters for body text.

### 2.3 Spacing, radius, elevation, layout

- Spacing scale (4 px base): 0, 1, 2, 3, 4, 5, 6, 8, 10, 12, 16, 20, 24 (×4 px).
- Radius: `sm 6px`, `md 10px`, `lg 16px`, `xl 24px`, `full`. Cards `lg`,
  buttons `md`, chips `full`. (Rounded = friendly; consistent with creative
  audience.)
- Elevation: `shadow-xs` (borders), `shadow-sm` (cards), `shadow-md`
  (dropdowns), `shadow-lg` (modals). Prefer borders + subtle shadow over
  heavy shadows.
- Breakpoints: `sm 640`, `md 768`, `lg 1024`, `xl 1280`, `2xl 1536`.
- Containers: public max 1200 px; dashboard fluid with 24 px gutters;
  reading content max 720 px.
- Grid: 4 columns mobile, 8 tablet, 12 desktop.

### 2.4 Iconography and imagery

- One icon set only (recommended: Heroicons or Lucide, outline 1.5 px,
  solid for active states). Wrapped in a `<x-ui.icon>` component.
- **Mirror in RTL:** arrows, chevrons, back/next, progress direction,
  "send", undo/redo, list indentation.
- **Never mirror:** play/pause/media controls, clocks, checkmarks, logos,
  numbers, charts' time axis (keep left-to-right for time series in both
  locales unless brand decides otherwise).
- Course thumbnails 16:9; instructor avatars 1:1; fixed aspect ratios with
  `object-cover` and blurred placeholder.
- Illustrations (empty states, onboarding): one consistent style, drawn or
  commissioned; never stock clip-art mixes.

### 2.5 Motion

- Durations: `fast 120ms`, `base 200ms`, `slow 320ms`; easing
  `cubic-bezier(.2,.8,.2,1)`.
- Use for: hover/press feedback, panel open (AI panel, curriculum drawer),
  progress updates, celebratory moment on lesson/course completion (small
  confetti or badge animation, once).
- Respect `prefers-reduced-motion`: disable non-essential animation.

## 3. RTL / LTR rules (mandatory)

- Set `<html lang dir>` from the active locale.
- Use **logical** utilities only: `ms-*`/`me-*`, `ps-*`/`pe-*`, `start-*`/`end-*`,
  `text-start`/`text-end`, `rounded-s-*`/`rounded-e-*`, `border-s`/`border-e`.
  Physical `ml-/mr-/pl-/pr-/left-/right-/text-left/text-right` are forbidden
  in views (arch/lint check, see conventions §10).
- Mixed text: wrap user-generated or opposite-script fragments with
  `dir="auto"` or `<bdi>`; emails, URLs, codes and numbers in `<bdi dir="ltr">`.
- Tables: column order follows reading direction; numeric columns are
  end-aligned in both locales.
- Forms: labels above inputs (avoids alignment issues); phone and email
  inputs `dir="ltr"` with start-aligned placeholder in the locale.
- Dates: localized month names; numeric dates in a fixed format
  (`DD/MM/YYYY` for AR and EN-EG) configurable per market.
- Breadcrumbs and pagination arrows follow direction.

## 4. Component library

Build once, reuse everywhere. Each component lives in
`resources/views/components/ui/*` (Blade) with Livewire/Alpine behavior
where needed, and is registered in `docs/REUSE_REGISTRY.md`.

### 4.1 Primitives (P1, before any feature screen)

`button` (primary, secondary, ghost, danger; sm/md/lg; loading; icon),
`link`, `icon`, `badge`, `chip`, `avatar`, `card`, `input`, `textarea`,
`select`, `checkbox`, `radio`, `switch`, `file-upload` (drag/drop, progress,
type/size hints), `form-field` (label/help/error), `modal`, `drawer`,
`dropdown`, `tabs`, `accordion`, `tooltip`, `toast`, `alert`, `progress-bar`,
`progress-ring`, `skeleton`, `empty-state`, `pagination`, `breadcrumb`,
`stepper`, `rating`, `price` (currency + market aware), `date` (localized),
`locale-switcher`, `bdi-text`.

### 4.2 Learning components (P2--P7)

`course-card` (thumbnail, title, instructor, age badge, format badge,
price/access, rating, wishlist), `course-filter-panel`, `curriculum-list`
(sections, lessons, durations, lock/preview/complete states),
`lesson-player` (provider-agnostic shell + watermark slot),
`player-toolbar`, `resume-banner`, `next-lesson-card`, `live-session-card`
(countdown, join window), `quiz-question` (types), `quiz-timer`,
`submission-uploader`, `feedback-thread`, `certificate-card`,
`ai-assistant-panel` (see AI spec §15), `help-request-thread`,
`child-switcher`, `kpi-tile`, `parent-child-summary`.

### 4.3 SKIT / Filament

- Filament panel theme compiled from the same `tokens.css`.
- Shared Filament pieces in `app/Filament/Shared/` (table columns: status
  badge, money, locale-aware date, protection tier; filters: date range,
  status, course, instructor; actions: approve/reject with reason, export
  CSV; form components: translatable tabs AR/EN, slug, SEO group, media
  picker with tier).
- Until SKIT source files are supplied (O-026), implement SKIT contract
  rules from DMC §8 using this theme; replace visual details when SKIT
  arrives without changing resources.

### Approved calm staff table workspace (D-026)

For Filament staff list pages, use a pale slate canvas, white cards and sidebar, restrained teal emphasis, clear Arabic typography, thin borders, subtle shadows, and compact groups. This approved direction supersedes provisional blue backoffice styling for these workspaces.

Keep the light/dark theme, RTL/LTR behavior, and semantic-token rules. The reference mockup approves layout and tone only: its drawn logo, sample names, counts, and actions are not product facts or feature approval. Official logo, extracted brand colors, and fonts remain open under O-027. See [the table workspace cookbook](design-system/TABLE_WORKSPACE.md) and [the approved reference](design-system/references/calm-workspace-approved-2026-11-17.png).

## 5. Page templates (layout blueprints)

| Template | Key regions | Notes |
|---|---|---|
| Public home | header (logo, nav, locale, login), hero, categories by discipline, featured courses, how it works, instructors, testimonials, parents section, FAQ, footer | CMS-driven blocks |
| Catalog | filter panel (drawer on mobile), sort, result grid, pagination | Filters: discipline, age, level, format, language, price |
| Course detail | hero with media preview, price/CTA sticky card, outcomes, curriculum preview, instructor, age/requirements, reviews, FAQ | Sticky CTA bottom bar on mobile |
| Checkout | order summary, learner selector (parent → child), coupon, payment method, terms | Market-aware currency |
| Student dashboard | continue learning, my courses, upcoming live, pending work, certificates | Calm, no marketing |
| Course player | player (main), curriculum (side/drawer), toolbar (resources, PDF, AI, notes), next lesson | AI panel only when eligible |
| Parent dashboard | child switcher, per-child summary, purchases, help-request status | Never shows child's AI chat by default |
| Instructor/admin (SKIT) | role-aware sidebar, topbar, KPI row, tables | Filament theme |

Each template has a wireframe-level spec written in the corresponding task
before implementation (task step "UI spec") and screenshots after.

## 6. Content and tone

- Arabic: simple Modern Standard Arabic, warm, encouraging; avoid heavy
  formal phrasing. English: plain and friendly.
- Buttons are verbs: "ابدأ الدرس / Start lesson", "كمّل / Continue".
- Errors explain what to do next. Empty states invite the next action.
- All UI strings in `lang/ar/*.php` and `lang/en/*.php` (or JSON); no
  literal strings in views.

## 7. Visual quality gates (every UI task)

1. Screenshots via browser tests for each touched page: **AR + EN ×
   mobile (390×844) + desktop (1440×900)**; SKIT screens also **light +
   dark**. Saved to `tests/Browser/screenshots/<task-id>/`.
2. Automated checks: axe (no serious/critical), no horizontal scroll at
   390 px, no physical direction classes, no untranslated keys.
3. Reviewer checklist: alignment and spacing on the 4 px grid; token use
   only; RTL mirroring correct; truncation/ellipsis for long Arabic and
   English titles; loading/empty/error states present; focus visible;
   touch targets ≥ 44 px.
4. Milestone visual review: orchestrator compares screenshots with the
   page template and previous milestone; regressions block completion.

## 7A. Performance budgets (ED-030)
LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms (mid-range mobile, 4G) on public and
student pages; ≤ 120 KB gzip JS per page excluding the video provider SDK;
fonts subset (Arabic + Latin) with `font-display: swap`; images WebP/AVIF with
`srcset`. Measured at each milestone gate that touches public/student UI.

## 7B. Interaction patterns
Behavior rules (tables, forms, confirmations, feedback, locked states, child
context strip, AI entry point) live in `15_UX_FLOWS_AND_PATTERNS.md` and are
part of the visual gate.

## 8. Brand extraction procedure (T-P0-04)

1. Capture the current site (home, course page, login) screenshots in AR/EN.
2. Collect logo files (prefer SVG); record clear-space and minimum size.
3. Extract colors from CSS (not screenshots) where possible; record hex,
   convert to OKLCH, generate 11-step scales.
4. Identify fonts in use (CSS `font-family`); confirm license for web use.
5. Write values into `resources/css/tokens.css` and section 2.1 of this
   document; mark anything inferred as `inferred` for owner confirmation.
6. Produce a one-page **brand sheet** (`docs/brand/BRAND_SHEET.md` + image)
   for owner sign-off. Until signed off, UI tasks proceed with the inferred
   tokens (they are centralized, so changes are cheap).


### PRD-based visual master references

See [the reference gallery](design-system/reference-pack-2026-11-17/index.html), [canonical manifest](design-system/reference-pack-2026-11-17/manifest.json) and [shared component contract](design-system/reference-pack-2026-11-17/COMMON_COMPONENTS.md). The owner requested these derived screens to extend the approved direction across the product. They are visual references, not feature acceptance or approval of sample values/policies. Public/learner and staff families share primitives, not identical navigation.
