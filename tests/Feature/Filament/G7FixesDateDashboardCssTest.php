<?php

declare(strict_types=1);

// T-G7-FIXES-E | B1 (LocalizedDateColumn LTR run), R1 (report table CSS), N3 (dashboard AI tiles and clear queues) | E5 E6 E7

use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Config::set('reports.dashboard_cache_seconds', 0);
    Carbon::setTestNow('2026-11-18 12:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/** The CSS rule bodies in a stylesheet whose selector contains the given text. */
function g7dRulesFor(string $css, string $selectorText): array
{
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
    $bodies = [];
    foreach ($matches as $match) {
        if (str_contains($match[1], $selectorText)) {
            $bodies[] = $match[2];
        }
    }

    return $bodies;
}

// ---------------------------------------------------------------------------
// E5: LocalizedDateColumn keeps the plain string and marks the cell as an LTR run
// ---------------------------------------------------------------------------

it('E5 the date column carries the yg-date class and dir ltr, and its state formats to the localized string', function (): void {
    $column = LocalizedDateColumn::make('created_at');

    expect($column->getExtraAttributes())->toMatchArray(['class' => 'yg-date', 'dir' => 'ltr'])
        // 09:00 UTC is 11:00 in the Egypt market timezone (UTC+2 in November).
        ->and($column->formatState('2026-11-18 09:00:00'))->toBeString()->toContain('11:00')
        ->and($column->formatState(null))->toBeNull();
})->group('T-G7-FIXES-E', 'E5');

it('E5 the theme isolates the yg-date cells as a bidi run', function (): void {
    $css = (string) file_get_contents(base_path('resources/css/filament-theme.css'));

    $bodies = g7dRulesFor($css, '.fi-ta .yg-date');
    expect($bodies)->not->toBeEmpty();
    expect(implode(' ', $bodies))->toContain('unicode-bidi: isolate');
})->group('T-G7-FIXES-E', 'E5');

// ---------------------------------------------------------------------------
// E6: report table scrolls inside its own container and keeps cells on one line
// ---------------------------------------------------------------------------

it('E6 the AI report table cells do not wrap and the table sits in a horizontally scrolling container', function (): void {
    $css = (string) file_get_contents(base_path('resources/css/filament-theme.css'));

    expect(implode(' ', g7dRulesFor($css, '.yg-ai-table')))->toContain('white-space: nowrap');
    expect(implode(' ', g7dRulesFor($css, '.yg-ai-scroll')))->toMatch('/overflow-x:\s*auto/');
})->group('T-G7-FIXES-E', 'E6');

// ---------------------------------------------------------------------------
// E7: dashboard hides empty AI tiles and collapses empty queues into one row
// ---------------------------------------------------------------------------

it('E7 an admin with no AI usage gets no AI request tile, and empty queues collapse into one clear row', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');

    $html = $this->actingAs($admin)->get('/admin')->assertOk()
        ->assertSee('data-role-dashboard', false)
        ->assertDontSee('data-kpi="ai_requests"', false)
        ->assertDontSee('data-kpi="ai_cost"', false)
        ->assertSee('data-queues-clear', false)
        ->assertSee('data-queue-empty', false)
        ->assertSee(__('dashboard.queues_all_clear', [], 'en'))
        ->getContent();

    expect($html)->not->toContain('data-queue="');
})->group('T-G7-FIXES-E', 'E7');
