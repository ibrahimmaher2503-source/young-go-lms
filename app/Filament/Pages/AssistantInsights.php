<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\AI\Console\TestConsoleAccess;
use App\Domain\AI\Queries\LessonInsights;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * T-P7-06, AI spec 14: per-lesson insights for the lesson's instructor and admins. Aggregated and anonymized: topics, cited pages,
 * questions the PDF did not cover and a not-understood count. No student name, no thread, no answer text. The lesson list is the
 * one the test console uses, so an instructor only ever sees lessons of courses they manage.
 */
final class AssistantInsights extends Page
{
    protected static ?string $slug = 'assistant-insights';

    protected string $view = 'filament.ai.insights';

    public ?int $lessonId = null;

    public string $from = '';

    public string $to = '';

    public static function getNavigationLabel(): string
    {
        return __('ai_admin.insights.nav');
    }

    public static function getNavigationGroup(): string
    {
        return __('ai_admin.group');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-light-bulb';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && AssistantAdminAccess::canOpenCoursePages($user);
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
        return __('ai_admin.insights.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $lessons = [];
        $found = TestConsoleAccess::lessonsFor($user);
        foreach ($found as $lesson) {
            $lessons[$lesson->getKey()] = $lesson->item?->course?->title.' · '.$lesson->title;
        }

        // The id comes from the browser: only a lesson of the permitted list is ever read.
        $selected = $found->firstWhere('id', $this->lessonId);
        $insights = null;
        if ($selected !== null) {
            [$from, $to] = $this->range();
            $insights = app(LessonInsights::class)->for($selected, $from, $to);
        }

        return ['lessons' => $lessons, 'insights' => $insights];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(): array
    {
        try {
            $from = CarbonImmutable::parse($this->from)->startOfDay();
            $to = CarbonImmutable::parse($this->to)->endOfDay();
        } catch (Throwable) {
            return [now()->toImmutable()->subDays(29)->startOfDay(), now()->toImmutable()->endOfDay()];
        }

        return $from->gt($to) ? [$to->startOfDay(), $from->endOfDay()] : [$from, $to];
    }
}
