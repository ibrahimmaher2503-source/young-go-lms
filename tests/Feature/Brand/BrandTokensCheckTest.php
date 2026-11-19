<?php

declare(strict_types=1);

// T-BRAND-TOKENS | O-026, O-027 | A1

use Illuminate\Support\Facades\File;

const BRAND_PROBE_VIEW = 'resources/views/zz-brand-probe-decisions.blade.php';

function brandProbe(string $body): void
{
    File::put(base_path(BRAND_PROBE_VIEW), $body);
}

afterEach(function (): void {
    File::delete(base_path(BRAND_PROBE_VIEW));
});

it('passes the brand token check on the repository', function (): void {
    $this->artisan('app:check-brand-tokens')->assertExitCode(0);
})->group('T-BRAND-TOKENS', 'A1');

it('fails and names the file for hard-coded colours and fonts in a view', function (string $line): void {
    brandProbe("<div class=\"x\" style=\"{$line}\"></div>\n");

    $this->artisan('app:check-brand-tokens')
        ->expectsOutputToContain('zz-brand-probe-decisions.blade.php')
        ->assertExitCode(1);
})->with([
    'hex colour' => ['color: #123456;'],
    'short hex' => ['color: #fff;'],
    'rgb' => ['color: rgb(1 2 3);'],
    'oklch' => ['color: oklch(0.5 0.1 10);'],
    'font-family literal' => ['font-family: Arial;'],
])->group('T-BRAND-TOKENS', 'A1');

it('does not fail on anchors, Filament props or token references', function (): void {
    brandProbe('<a href="#add-child">x</a><span style="color: var(--yg-color-media); font-family: var(--yg-font-stack-latin)">y</span>'."\n");

    $this->artisan('app:check-brand-tokens')->assertExitCode(0);
})->group('T-BRAND-TOKENS', 'A1');

it('skips a line marked brand-check-ignore', function (): void {
    brandProbe("<div style=\"color: #123456;\"></div> {{-- brand-check-ignore --}}\n");

    $this->artisan('app:check-brand-tokens')->assertExitCode(0);
})->group('T-BRAND-TOKENS', 'A1');

it('fails when a PDF colour in config drifts from tokens.css', function (): void {
    config(['brand.pdf_colors.brand.value' => 'oklch(0.1 0 0)']);

    $this->artisan('app:check-brand-tokens')->assertExitCode(1);
})->group('T-BRAND-TOKENS', 'A1');

it('fails when the configured font family drifts', function (): void {
    config(['brand.font.family' => 'Other']);

    $this->artisan('app:check-brand-tokens')->assertExitCode(1);
})->group('T-BRAND-TOKENS', 'A1');
