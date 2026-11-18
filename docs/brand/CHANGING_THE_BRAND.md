# Changing the brand (colors, fonts, logo, name)

One place per concern. Never edit a color or font in a Blade view, a component or `filament-theme.css`.

## 1. Colors, radii, shadows
Edit `resources/css/tokens.css` only.
- Palettes: `--yg-brand-*` (primary), `--yg-accent-*`, `--yg-neutral-*`, `--yg-success|warning|danger|info-*`.
- Meaning (what a screen uses): the `--yg-color-*` aliases, light block `:root` and dark block `.dark, [data-theme="dark"]`.
- Radii: `--yg-radius-*` and `--radius-*`; shadows: `--yg-shadow-*`.
- The student site (Tailwind) and the three Filament panels (admin, instructor, finance) all read these. Filament's primary and gray ramps are mapped to brand and neutral in `resources/css/filament-theme.css`.

## 2. PDFs (certificate, receipt, statement) and avatar SVGs
They cannot read CSS variables. Copy each changed literal into `config/brand.php` (`pdf_colors`; each entry names its token). The PDF CSS is built from it by `App\Support\Brand`.

## 3. Fonts
1. Put the font file in `resources/fonts/<name>/` (self-hosted, no third-party font requests).
2. In `tokens.css`: update the `@font-face` (family and url), `--yg-font-family-brand` (must equal the @font-face family) and the stacks `--yg-font-stack-*`.
3. In `config/brand.php` `font`: `family`, `file`, `stack`, `pdf_stack`. Filament reads the family from here.
4. Nothing else changes: all text inherits `--font-sans`.

## 4. Logo and name
- Name: key `name` in `lang/ar/app.php` and `lang/en/app.php` (Filament brand name and public site).
- Staff panel wordmark: `resources/views/filament/shared/brand.blade.php` (styled by `.yg-brand-lockup`).
- Public site wordmark and favicon: the public layout components and `public/favicon.ico`.
- PDF wordmark: text in each `resources/views/*/pdf.blade.php`.

## 5. Check, build, re-screenshot
```
php artisan app:check-brand-tokens      # fails on hardcoded colors/fonts or tokens.css vs config/brand.php drift
node scripts/check-token-contrast.mjs   # WCAG AA pairs
npm run build
```
Re-screenshot AR and EN, light and dark, at 390 and 1440 wide: public home, catalog, learner dashboard and player, admin, instructor and finance panels (one list and one form each), and one PDF each (certificate, receipt, statement). Re-run the browser suites that save screenshots.

## Known gap
Filament's semantic colors (danger, success, warning, info) still use Filament's defaults, not the `--yg-*` semantic ramps. Mapping them would change today's look, so it was not done; do it as a separate, reviewed change if the PO wants those to follow the tokens.
