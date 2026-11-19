<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportScope;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

/**
 * T-P9-06 (PRD 21): the reports page of the admin, finance and instructor panels. One page, one report at a time: the list of
 * reports, the filters and the rows all come from `ReportCatalog` for the signed-in user (recomputed on every render, because
 * Livewire properties are user input). The export button is a link to the audited CSV route with the same filters.
 */
final class ReportsPage extends Page
{
    protected static ?string $slug = 'reports';

    protected string $view = 'filament.reports.index';

    public string $report = '';

    public string $from = '';

    public string $to = '';

    public string $course = '';

    public string $instructor = '';

    public string $market = '';

    public static function getNavigationLabel(): string
    {
        return __('reports.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-bar-square';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(ReportCatalog::class)->available($user) !== [];
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->to = ReportFilters::defaultTo();
        $this->from = ReportFilters::defaultFrom();
        $first = app(ReportCatalog::class)->available($this->user())[0] ?? null;
        $this->report = $first instanceof ReportKey ? $first->value : '';
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('reports.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);
        $user = $this->user();
        $catalog = app(ReportCatalog::class);
        $available = $catalog->available($user);

        $key = ReportKey::tryFrom($this->report);
        if ($key === null || ! $catalog->canView($user, $key)) {
            $key = $available[0];
        }

        $input = ['from' => $this->from, 'to' => $this->to, 'course' => $this->course, 'instructor' => $this->instructor, 'market' => $this->market];
        $filters = $catalog->scope($user, $key, ReportFilters::fromInput($input));
        $definition = $catalog->definition($key);

        $rows = [];
        $limit = Config::integer('reports.preview_rows');
        foreach ($definition->rows($filters) as $row) {
            $rows[] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }

        $count = $definition->count($filters);
        $cap = Config::integer('reports.max_rows');

        return [
            'available' => $available,
            'key' => $key,
            'columns' => $definition->columns(),
            'rows' => $rows,
            'count' => $count,
            'limit' => $limit,
            'totals' => $definition->totals($filters),
            'courses' => $catalog->courseOptions($user, $key),
            'instructors' => $catalog->instructorOptions($user, $key),
            'showMarket' => $key->supports(ReportKey::FILTER_MARKET) && $catalog->scopeOf($user, $key) === ReportScope::All,
            'ownOnly' => $catalog->scopeOf($user, $key) === ReportScope::Own,
            'tooLarge' => $count > $cap,
            'cap' => $cap,
            'exportUrl' => route('reports.export', ['report' => $key->value] + array_filter($input, static fn (string $value): bool => $value !== '')),
            'range' => [$filters->localFromDate(), $filters->localToDate()],
            'timezone' => $filters->timezoneName(),
        ];
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
