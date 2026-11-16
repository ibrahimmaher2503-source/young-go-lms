<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('keeps loading and error states accessible', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.button :loading="true">Save</x-ui.button>
        <x-ui.input id="email" type="email" :invalid="true" />
        BLADE);
    expect($html)->toContain('aria-busy="true"', 'disabled', 'aria-invalid="true"', 'dir="ltr"');
})->group('T-P1-09', 'A1', 'YG-GAP-A11Y-001');

it('can preview all primitives in either locale without publishing them', function (string $locale, string $dir) {
    $this->app['env'] = 'staging';
    config(['design.token_preview' => true]);
    $this->withoutVite()->get('/_design/components?locale='.$locale)
        ->assertOk()->assertSee('dir="'.$dir.'"', false)
        ->assertSee('data-ui-tabs', false)
        ->assertSee('data-ui-file', false)
        ->assertSee('popover', false);
})->with([['ar', 'rtl'], ['en', 'ltr']])->group('T-P1-09', 'A3', 'YG-GAP-QA-003', 'YG-GAP-UI-001');

it('keeps the component gallery unavailable in production', function () {
    $this->app['env'] = 'production';
    config(['design.token_preview' => true]);
    $this->get('/_design/components')->assertNotFound();
})->group('T-P1-09', 'A3');
