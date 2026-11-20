<?php

declare(strict_types=1);

use App\Filament\Resources\RolePermissions\RolePermissionResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

abstract class WorkspaceListRecordsTestFixture extends WorkspaceListRecords
{
    protected static string $resource = RolePermissionResource::class;

    public function getTabs(): array
    {
        return ['all' => Tab::make('All')];
    }
}

class EmptyWorkspaceListRecordsTestPage extends WorkspaceListRecordsTestFixture {}

class ConfiguredWorkspaceListRecordsTestPage extends WorkspaceListRecordsTestFixture
{
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => 'Open applications', 'value' => 3, 'icon' => 'heroicon-o-academic-cap'],
            ['label' => 'Completed', 'value' => '8 of 12'],
        ];
    }

    protected function getWorkspaceSidebar(): array
    {
        return [Section::make('Quick filters')];
    }
}

it('exposes an empty base layout and keeps the embedded resource table once', function () {
    $page = new EmptyWorkspaceListRecordsTestPage;
    $components = $page->content(Schema::make())->getComponents();
    $grid = $components[0];
    $main = $grid->getDefaultChildComponents()[0];
    $mainComponents = $main->getDefaultChildComponents();

    expect($page)->toBeInstanceOf(ListRecords::class)
        ->and($page->getPageClasses())->toContain('yg-workspace-page')
        ->and($grid)->toBeInstanceOf(Grid::class)
        ->and($grid->getColumns('xl'))->toBe(2)
        ->and($grid->getDefaultChildComponents())->toHaveCount(1)
        ->and($main)->toBeInstanceOf(Group::class)
        ->and($main->getColumnSpan('xl'))->toBe(2)
        ->and($main->getExtraAttributes()['class'])->toBe('yg-workspace-main')
        ->and(collect($mainComponents)->filter(fn ($component): bool => $component instanceof EmbeddedTable))->toHaveCount(1)
        ->and(collect($mainComponents)->filter(fn ($component): bool => $component instanceof Tabs))->toHaveCount(1)
        ->and(collect($mainComponents)->filter(fn ($component): bool => $component instanceof RenderHook))->toHaveCount(2);
})->group('T-P1-08', 'A1');

it('renders the optional summary and sidebar around the native list content', function () {
    $page = new ConfiguredWorkspaceListRecordsTestPage;
    $components = $page->content(Schema::make())->getComponents();
    $summary = $components[0];
    $grid = $components[1];
    [$main, $sidebar] = $grid->getDefaultChildComponents();
    $mainComponents = $main->getDefaultChildComponents();

    expect($summary)->toBeInstanceOf(View::class)
        ->and($summary->getView())->toBe('filament.shared.summary-strip')
        ->and($summary->getViewData()['items'])->toHaveCount(2)
        ->and($grid)->toBeInstanceOf(Grid::class)
        ->and($grid->getColumns('xl'))->toBe(2)
        ->and($main)->toBeInstanceOf(Group::class)
        ->and($main->getColumnSpan('xl'))->toBe(1)
        ->and($main->getExtraAttributes()['class'])->toBe('yg-workspace-main')
        ->and($sidebar)->toBeInstanceOf(Group::class)
        ->and($sidebar->getColumnSpan('xl'))->toBe(1)
        ->and($sidebar->getExtraAttributes()['class'])->toBe('yg-workspace-sidebar')
        ->and($sidebar->getDefaultChildComponents()[0])->toBeInstanceOf(Section::class)
        ->and(collect($mainComponents)->filter(fn ($component): bool => $component instanceof EmbeddedTable))->toHaveCount(1)
        ->and(collect($mainComponents)->filter(fn ($component): bool => $component instanceof Tabs))->toHaveCount(1)
        ->and(collect($mainComponents)->filter(fn ($component): bool => $component instanceof RenderHook))->toHaveCount(2);
})->group('T-P1-08', 'A1');

it('renders summary labels and values as accessible definition pairs', function () {
    $html = view('filament.shared.summary-strip', [
        'items' => [
            ['label' => 'Open applications', 'value' => 3, 'icon' => 'heroicon-o-academic-cap'],
            ['label' => 'Completed', 'value' => '8 of 12'],
        ],
    ])->render();

    expect($html)->toContain('<dl class="yg-summary-strip">')
        ->and($html)->toMatch('~<dt class="yg-summary-label" title="Open applications">\s*Open applications\s*</dt>~')
        ->and($html)->toMatch('~<dd class="yg-summary-value" data-workspace-value="3">\s*3\s*</dd>~')
        ->and($html)->toMatch('~<dd class="yg-summary-value" data-workspace-value="8 of 12">\s*8 of 12\s*</dd>~')
        ->and($html)->toContain('aria-hidden="true"');
})->group('T-P1-08', 'A1');

it('keeps the resource access guard for workspace list pages', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole('student'));

    expect(fn () => ConfiguredWorkspaceListRecordsTestPage::authorizeResourceAccess())
        ->toThrow(HttpException::class);
})->group('T-P1-08', 'A1');
