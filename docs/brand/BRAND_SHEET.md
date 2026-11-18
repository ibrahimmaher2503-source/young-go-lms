# Young Go brand sheet

**D-027 · Approved for the Young Go public site on 2026-11-18**

The owner approved the existing preview palette, bundled typography and
localized wordmark for the public site. The legacy hostname and original
legacy artwork, typefaces and usage rules remain UNKNOWN.

| Role | Proposed value |
|---|---|
| Primary | Owner-approved operational teal (D-026); primary sampled from approved reference RGB(2,109,108), OKLCH(0.484010 0.082313 193.660745). O-027 is DECIDED (2026-11-18): the current brand is accepted as final for now |
| Accent | Terracotta, OKLCH hue 40 |
| Neutral | Cool blue-gray, OKLCH hue 250 (`--yg-neutral-*`), as implemented in `resources/css/tokens.css` |
| Typography | Locally bundled Noto Sans Arabic variable font with retained OFL license; no runtime third-party font requests. |
| Logo | Plain localized Young Go wordmark until official assets and usage rules are confirmed. |
| Assistant | Tinted surface with labeled white, yellow, orange, red and green readiness states. |

Enable `DESIGN_TOKEN_PREVIEW=true` locally or on staging. Preview: `/_design/tokens?locale=ar&theme=dark`; set locale to `ar` or `en`
and theme to `light` or `dark`. Build checks semantic text/surface pairs at
WCAG AA 4.5:1. Student Tailwind and the Filament theme import
`resources/css/tokens.css`. The Filament theme maps the primary ramp and font
to Filament's CSS variables. All three Filament panels (admin, instructor, finance) use this theme.

Owner approval inputs: correct legacy hostname, approved logo files and usage
rules, brand color references, and approved font files with web usage rights.

## Source of truth and changes

`resources/css/tokens.css` is the only place colors, radii, shadows and fonts are defined. `config/brand.php` mirrors the few literals PDFs need, and `php artisan app:check-brand-tokens` guards both. To change the brand see [CHANGING_THE_BRAND.md](CHANGING_THE_BRAND.md).

## Changelog

- 2026-11-18: neutral now described as implemented (hue 250 cool blue-gray; it was documented as warm gray hue 78). The PO accepted the implemented tokens; tokens unchanged. O-026 and O-027 decided.
