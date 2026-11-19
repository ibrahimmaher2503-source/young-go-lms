<?php

declare(strict_types=1);

// T-G7-FIXES-F (fonts, preload, facet cache, brand guard) and T-G7-FIXES-E (compact staff tables) | F1 F2 F3 F5 E3 E4

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Cache::flush();
});

// ---------------------------------------------------------------------------
// F1: brand guard
// ---------------------------------------------------------------------------

it('F1 the brand token check passes on the committed brand and tokens', function (): void {
    expect(Artisan::call('app:check-brand-tokens'))->toBe(0);
})->group('T-G7-FIXES-F', 'F1');

it('F1 the brand token check fails when the fallback family differs from tokens.css', function (): void {
    Config::set('brand.font.fallback_family', 'Not The Fallback Face');

    expect(Artisan::call('app:check-brand-tokens'))->not->toBe(0);
})->group('T-G7-FIXES-F', 'F1');

it('F1 the brand token check fails when a listed web font file is missing on disk', function (): void {
    Config::set('brand.font.web_files.latin', 'fonts/noto-sans-arabic/does-not-exist.woff2');

    expect(Artisan::call('app:check-brand-tokens'))->not->toBe(0);
})->group('T-G7-FIXES-F', 'F1');

it('F1 the brand token check fails when a tokens.css font url is not listed in font.web_files', function (): void {
    Config::set('brand.font.web_files', ['arabic' => 'fonts/noto-sans-arabic/NotoSansArabic-arabic.woff2']);

    expect(Artisan::call('app:check-brand-tokens'))->not->toBe(0);
})->group('T-G7-FIXES-F', 'F1');

it('F1 the embedded TTF stays on disk for the PDFs', function (): void {
    expect(file_exists(resource_path((string) Config::get('brand.font.file'))))->toBeTrue();
})->group('T-G7-FIXES-F', 'F1');

// ---------------------------------------------------------------------------
// F2: web font files and what the browser can receive
// ---------------------------------------------------------------------------

it('F2 both woff2 subsets exist, each under 160 KB, and together under 200 KB', function (): void {
    $total = 0;
    foreach (['arabic', 'latin'] as $face) {
        $path = resource_path((string) Config::get('brand.font.web_files.'.$face));
        expect(file_exists($path))->toBeTrue();
        $size = (int) filesize($path);
        expect($size)->toBeLessThan(160 * 1024);
        $total += $size;
    }
    expect($total)->toBeLessThan(200 * 1024);
})->group('T-G7-FIXES-F', 'F2');

it('F2 no compiled stylesheet served to browsers references a TTF file', function (): void {
    $stylesheets = glob(public_path('build/assets/*.css')) ?: [];
    expect($stylesheets)->not->toBeEmpty();
    foreach ($stylesheets as $stylesheet) {
        expect(str_contains((string) file_get_contents($stylesheet), '.ttf'))->toBeFalse("TTF referenced in {$stylesheet}");
    }
})->group('T-G7-FIXES-F', 'F2');

// ---------------------------------------------------------------------------
// F3: one font preload per page language
// ---------------------------------------------------------------------------

it('F3 the English catalog preloads exactly one woff2 font, the latin subset', function (): void {
    $html = $this->get('/en/courses')->assertOk()->getContent();

    preg_match_all('/<link[^>]*rel="preload"[^>]*as="font"[^>]*>/', $html, $links);
    expect($links[0])->toHaveCount(1)
        ->and($links[0][0])->toContain('type="font/woff2"')->toContain('crossorigin')->toContain('NotoSansArabic-latin');
})->group('T-G7-FIXES-F', 'F3');

it('F3 the Arabic catalog preloads the arabic subset', function (): void {
    $html = $this->get('/ar/courses')->assertOk()->getContent();

    preg_match_all('/<link[^>]*rel="preload"[^>]*as="font"[^>]*>/', $html, $links);
    expect($links[0])->toHaveCount(1)
        ->and($links[0][0])->toContain('NotoSansArabic-arabic');
})->group('T-G7-FIXES-F', 'F3');

// ---------------------------------------------------------------------------
// F5: facet cache
// ---------------------------------------------------------------------------

it('F5 the second catalog request reads the facets from cache and runs none of the facet queries', function (): void {
    $this->get('/en/courses')->assertOk();

    $facetQueries = 0;
    DB::listen(function ($query) use (&$facetQueries): void {
        if (preg_match('/from `(categories|age_bands|course_instructors)`/', $query->sql)) {
            $facetQueries++;
        }
    });

    $this->get('/en/courses')->assertOk();

    expect($facetQueries)->toBe(0);
})->group('T-G7-FIXES-F', 'F5');

it('F5 the cached facets are plain arrays of strings under a per-locale key, with no models', function (): void {
    $this->get('/en/courses')->assertOk();

    $facets = Cache::get('catalog:facets:en:EG');
    expect($facets)->toBeArray();
    foreach ($facets as $values) {
        expect($values)->toBeArray();
        foreach ($values as $value) {
            expect(is_object($value))->toBeFalse();
        }
    }
    expect(Cache::get('catalog:facets:ar:EG'))->toBeNull();
})->group('T-G7-FIXES-F', 'F5');

// ---------------------------------------------------------------------------
// E3/E4: staff lists use the compact table and the ellipsis action group
// ---------------------------------------------------------------------------

// The ellipsis row-action group only renders for rows, so these empty lists assert the table class alone.
it('E3 the staff list tables carry the compact table class', function (string $slug): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('super_admin');

    $this->actingAs($admin)->get('/admin/'.$slug)->assertOk()->assertSee('yg-compact-table', false);
})->with(['coupons', 'bundles', 'subscription-plans', 'subscriptions', 'refunds', 'manual-payments', 'settlements'])
    ->group('T-G7-FIXES-E', 'E3');
