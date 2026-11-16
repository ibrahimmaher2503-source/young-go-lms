<?php

declare(strict_types=1);

it('previews tokens in both locales and themes on staging', function (string $locale, string $direction, string $theme) {
    $this->app['env'] = 'staging';
    config(['design.token_preview' => true]);
    $this->withoutVite()
        ->get('/_design/tokens?locale='.$locale.'&theme='.$theme)
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee('dir="'.$direction.'"', false)
        ->assertSee('data-theme="'.$theme.'"', false);
})->with([
    ['ar', 'rtl', 'light'],
    ['ar', 'rtl', 'dark'],
    ['en', 'ltr', 'light'],
    ['en', 'ltr', 'dark'],
])->group('T-P0-04', 'A4', 'YG-GAP-UI-001');

it('keeps the token preview unavailable in production', function () {
    $this->app['env'] = 'production';
    config(['design.token_preview' => true]);
    $this->get('/_design/tokens')->assertNotFound();
})->group('T-P0-04', 'A4', 'YG-GAP-UI-001');

it('requires explicit opt-in even on staging', function () {
    $this->app['env'] = 'staging';
    config(['design.token_preview' => false]);
    $this->get('/_design/tokens')->assertNotFound();
})->group('T-P0-04', 'A4', 'YG-GAP-UI-001');
