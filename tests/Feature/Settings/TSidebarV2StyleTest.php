<?php

declare(strict_types=1);

// T-SIDEBAR-V2 | case 8 (CSS guard, file read) and sidebar token checks

/** @return list<string> the CSS lines that style the Filament sidebar */
function tsvStyleLines(): array
{
    $css = (string) file_get_contents(base_path('resources/css/filament-theme.css'));

    return array_values(array_filter(explode("\n", $css), static fn (string $line): bool => str_contains($line, '.fi-sidebar')));
}

it('sets the sidebar item and group font tokens to 0.875rem and 0.8125rem', function (): void {
    $tokens = (string) file_get_contents(base_path('resources/css/tokens.css'));

    expect($tokens)->toContain('--yg-font-sidebar: 0.875rem;')
        ->and($tokens)->toContain('--yg-font-sidebar-group: 0.8125rem;');
})->group('T-SIDEBAR-V2', 'case-8');

it('sizes the sidebar item label with the sidebar item token', function (): void {
    $rule = implode("\n", array_filter(tsvStyleLines(), static fn (string $line): bool => str_contains($line, '.fi-sidebar-item-label {')));

    expect($rule)->toContain('font-size: var(--yg-font-sidebar);');
})->group('T-SIDEBAR-V2', 'case-8');

it('never uses overflow-wrap: break-word in a sidebar rule', function (): void {
    $offending = array_values(array_filter(tsvStyleLines(), static fn (string $line): bool => str_contains($line, 'overflow-wrap: break-word')));

    expect($offending)->toBe([]);
})->group('T-SIDEBAR-V2', 'case-8');

it('sets text-transform none on the sidebar group label', function (): void {
    $rule = implode("\n", array_filter(tsvStyleLines(), static fn (string $line): bool => str_contains($line, '.fi-sidebar-group-label {')));

    expect($rule)->toContain('text-transform: none;');
})->group('T-SIDEBAR-V2', 'case-8');

it('passes the brand-token and RTL checks', function (): void {
    $this->artisan('app:check-rtl')->assertSuccessful();
    $this->artisan('app:check-brand-tokens')->assertSuccessful();
})->group('T-SIDEBAR-V2', 'case-8');
