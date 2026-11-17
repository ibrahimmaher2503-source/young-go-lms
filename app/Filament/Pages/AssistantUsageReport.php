<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\AI\Queries\UsageReport;
use App\Domain\Settings\AiSettings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * T-P7-06, AI spec 14: usage, cost and error report for admins (ai.view_usage). Counts, tokens, cost and latency per result,
 * day, model and lesson. It reads ai_usage only, so there is no message body, no question and no student on this page.
 */
final class AssistantUsageReport extends Page
{
    private const int MAX_DAYS = 366;

    protected static ?string $slug = 'assistant-usage';

    protected string $view = 'filament.ai.usage';

    public string $from = '';

    public string $to = '';

    public static function getNavigationLabel(): string
    {
        return __('ai_admin.usage.nav');
    }

    public static function getNavigationGroup(): string
    {
        return __('ai_admin.group');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && AssistantAdminAccess::canViewUsage($user);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->to = now()->toDateString();
        $this->from = now()->subDays(29)->toDateString();
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('ai_admin.usage.title');
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
        [$from, $to] = $this->range();

        return [
            'report' => app(UsageReport::class)->for($from, $to),
            'currency' => app(AiSettings::class)->budget_currency,
            'range' => [$from->toDateString(), $to->toDateString()],
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} an invalid or reversed range falls back to the last 30 days, and a range is capped at one year */
    private function range(): array
    {
        try {
            $from = CarbonImmutable::parse($this->from)->startOfDay();
            $to = CarbonImmutable::parse($this->to)->endOfDay();
        } catch (Throwable) {
            return [now()->toImmutable()->subDays(29)->startOfDay(), now()->toImmutable()->endOfDay()];
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$to->diffInDays($from, true) > self::MAX_DAYS ? $to->subDays(self::MAX_DAYS)->startOfDay() : $from, $to];
    }
}
