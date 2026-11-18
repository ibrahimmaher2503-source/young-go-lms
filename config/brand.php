<?php

declare(strict_types=1);

/*
 * Literal brand values for places that cannot read CSS variables at runtime: standalone PDFs rendered by headless
 * Chromium (no Vite), SVG avatars and the Filament font name.
 *
 * SOURCE OF TRUTH: resources/css/tokens.css. Every value here mirrors a token, named in 'token'.
 * Change the token first, then copy the new literal here. `php artisan app:check-brand-tokens` fails on drift.
 */
return [
    'font' => [
        'token' => '--yg-font-family-brand',
        'family' => 'Noto Sans Arabic',
        'file' => 'fonts/noto-sans-arabic/NotoSansArabic-Variable.ttf', // under resources/, must match @font-face in tokens.css
        'stack_token' => '--yg-font-stack-sans',
        'stack' => '"Noto Sans Arabic", system-ui, "Segoe UI", Tahoma, Arial, sans-serif',
        'pdf_stack' => '"Noto Sans Arabic", sans-serif', // PDFs embed the font, so only a generic fallback is needed
        'svg_fallback' => 'sans-serif', // avatar SVGs are images and cannot load the web font
    ],

    // PDF CSS variable => token and its literal value.
    'pdf_colors' => [
        'brand' => ['token' => '--yg-brand-600', 'value' => 'oklch(0.484010 0.082313 193.660745)'],
        'brand-dark' => ['token' => '--yg-brand-800', 'value' => 'oklch(0.32 0.048 185)'],
        'brand-soft' => ['token' => '--yg-brand-50', 'value' => 'oklch(0.97 0.0093 185)'],
        'accent' => ['token' => '--yg-accent-500', 'value' => 'oklch(0.61 0.065 40)'],
        'body' => ['token' => '--yg-neutral-900', 'value' => 'oklch(0.24 0.013 250)'],
        'muted' => ['token' => '--yg-neutral-700', 'value' => 'oklch(0.41 0.02 250)'],
        'line' => ['token' => '--yg-neutral-200', 'value' => 'oklch(0.86 0.01 250)'],
        'paper' => ['token' => '--yg-neutral-0', 'value' => 'oklch(1 0 0)'],
    ],

    // Files the brand checker does not scan, path => reason. Keep this list as short as possible.
    'check_allow' => [
        'app/Support/Brand.php' => 'Builds the PDF @font-face/:root CSS from this config.',
        'app/Console/Commands/CheckBrandTokens.php' => 'Contains the detection patterns themselves.',
    ],
];
