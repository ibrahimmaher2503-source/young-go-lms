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
The browser never gets the full font. It gets woff2 subsets with `unicode-range`, a preload of the primary one and a metric-matched fallback, so the swap does not shift the layout (CLS) and the page stays small (about 175 KB of font instead of 845 KB).
1. Put the full font file in `resources/fonts/<name>/` (self-hosted, no third-party font requests). PDFs embed this file, so keep it: `config/brand.php` `font.file`.
2. Make the web subsets with fonttools (`pip install fonttools brotli`): pin the axes you need (`fontTools.varLib.instancer`, for example `wdth=100`, `wght=300:800`), then `python -m fontTools.subset <font> --unicodes=<range> --flavor=woff2 --layout-features='*' --no-hinting --output-file=<name>-arabic.woff2`. One Arabic subset (U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF and the bidi marks) and one Latin subset (U+0000-00FF plus general punctuation, currency, arrows).
3. In `tokens.css`: one `@font-face` per subset (same family, `font-weight` range matching what you kept, `unicode-range`), `--yg-font-family-brand` (must equal the @font-face family), `--yg-font-family-fallback` and the stacks `--yg-font-stack-*` (brand, then the fallback family, then system fonts).
4. Fallback faces: the two `@font-face` rules named by `--yg-font-family-fallback` use `local()` system fonts with `size-adjust` (brand average advance / system font average advance), `ascent-override`, `descent-override` (brand metrics divided by `size-adjust`) and `line-gap-override: 0%`. Recompute them for a new font (measure the average advance of lowercase Latin letters and of the Arabic letters U+0621-064A in both fonts with fonttools).
5. In `config/brand.php` `font`: `family`, `file` (full file for PDFs), `web_files` (every woff2 url used by tokens.css; `arabic` and `latin` keys), `fallback_family`, `stack`, `pdf_stack`. Filament reads the family from here.
6. `resources/views/components/layouts/base.blade.php` preloads the Arabic subset on Arabic pages and the Latin subset otherwise (`Vite::asset`, `crossorigin`). If you rename the files, update that path.
7. Nothing else changes: all text inherits `--font-sans`. `php artisan app:check-brand-tokens` fails when the tokens, `config/brand.php` and the files on disk disagree.

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
