# Tests prompt: T-BRAND-TOKENS Global brand tokens and `app:check-brand-tokens`

You write the tests for a finished task. Do **not** change production code (`app/**`, `config/**`, `resources/**`, `lang/**`, `database/**`, `routes/**`). If a test exposes a bug, write it correctly, mark it `->skip('BUG: ...')` and list it in `docs/reviews/BUGS_FOUND.md`.

Stack: Laravel 12, Pest. Windows: `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest`. Header `// T-BRAND-TOKENS | O-026, O-027 | A1`, `->group('T-BRAND-TOKENS', 'A1')`.

## What was built
- `resources/css/tokens.css` is the single source for colors, radii, shadows and fonts. New tokens: `--yg-font-family-brand`, `--yg-font-stack-sans`, `--yg-font-stack-sans-ar`, `--yg-font-stack-latin`, `--yg-color-media`, `--yg-color-on-media`, `--yg-color-qr` (Tailwind theme entries `bg-media`, `text-on-media`, `bg-qr`). Computed values are unchanged.
- `config/brand.php` mirrors literals for PDFs and SVG (`font`, `pdf_colors`, `check_allow`); `App\Support\Brand` (`fontFamily`, `svgFontFamily`, `fontPath`, `fontDataUri`, `pdfCss`); `resources/views/partials/brand-pdf-css.blade.php` is included by the certificate, receipt and statement PDF views. `StaffPanelsServiceProvider` and `WorkspaceAvatarProvider` read `Brand`.
- `php artisan app:check-brand-tokens` (`App\Console\Commands\CheckBrandTokens`) scans `resources/views`, `resources/js`, `resources/css` (except tokens.css), `app`, and non-vendor `public` files for hex/rgb/hsl/oklch, `font-family`, `@font-face`, font CDN links, Tailwind arbitrary colors, `text-white|black`, palette utilities (`gray` allowed under Filament paths), `->colors([`, `->font('literal'`. A line with `brand-check-ignore` or a path in `config('brand.check_allow')` is skipped. It then checks config/brand.php against tokens.css (each `pdf_colors` value vs its token, font family, stack, @font-face family and file).

## Cover
- A1 the command passes on the repo (`$this->artisan('app:check-brand-tokens')->assertExitCode(0)`), like the check-rtl A1 in `CheckoutUiTest`.
- Detection: a temporary blade file under `resources/views` (delete in `afterEach`) containing each of `#123456`, `#fff`, `rgb(1 2 3)`, `oklch(0.5 0.1 10)`, `font-family: Arial`, `@font-face`, a `fonts.googleapis.com` link, `bg-[#fff]`, `text-red-500`, `bg-black`, `text-white/80`: the command fails and names file and line. `href="#add-child"`, `color="gray"` (Filament prop), `var(--x)` and `font-family: var(--yg-font-stack-latin)` do not fail. `brand-check-ignore` on the line passes.
- Drift: `config(['brand.pdf_colors.brand.value' => 'oklch(0.1 0 0)'])`, then `config(['brand.font.family' => 'Other'])`, then a wrong `brand.font.file`: each fails with a clear message; unchanged config passes.
- Parity: every `pdf_colors` token exists in tokens.css and resolves to an oklch literal; `Brand::pdfCss()` contains every `--name:` from config, the `@font-face` with a `data:font/ttf;base64,` source and `--font`.
- PDFs: certificate, receipt (sale and credit note) and statement HTML contain the css variables from config and no hex colors, and the same text as before (extend `CertificateVerifyAndPdfTest`; its `$data['font']` assertion still holds for certificate and receipt; `StatementPdf` still passes an unused `font`).
- Avatar: `WorkspaceAvatarProvider::get()` decodes to an SVG whose `font-family` equals `config('brand.font.svg_fallback')`.
- Browser (group `browser`): `WorkspaceFontTest` still passes; public home computed `font-family` on `body` for `ar` and `en` starts with `"Noto Sans Arabic"`; the lesson player stage background is `rgb(0, 0, 0)` and the certificate verify QR box background is white.
- `node scripts/check-token-contrast.mjs`, `app:check-translations` and `app:check-rtl` still pass.
- Known gap (do not test as working): Filament semantic colors (danger, success, warning, info) use Filament defaults, not `--yg-*`.
