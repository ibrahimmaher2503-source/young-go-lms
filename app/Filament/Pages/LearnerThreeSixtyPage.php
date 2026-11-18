<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Support\Actions\RecordLearner360View;
use App\Domain\Support\Actions\StartViewAs;
use App\Domain\Support\Queries\Learner360Record;
use App\Domain\Support\Queries\LearnerDirectory;
use App\Domain\Support\ViewAsEligibility;
use App\Domain\Support\ViewAsRefusedException;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;

/**
 * T-P9-10 (ED-027, YG-GAP-OPS-001/002): Learner 360 for support and admins. Search for a learner, then read one record
 * across modules, each section gated by its own permission and contact data masked by role. "View as learner" starts a
 * read-only, time-limited, audited session (see `StartViewAs`); it never signs in as the learner. Everything is recomputed
 * and re-authorized on every render because Livewire properties are user input.
 */
final class LearnerThreeSixtyPage extends Page
{
    protected static ?string $slug = 'learner-360';

    protected string $view = 'filament.support.learner-360';

    public string $search = '';

    public ?int $learnerId = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/learner-360/{learner?}';
    }

    public static function getNavigationLabel(): string
    {
        return __('support.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-identification';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('learner.360.view');
    }

    public function mount(?string $learner = null): void
    {
        abort_unless(self::canAccess(), 403);

        if ($learner === null) {
            return;
        }

        abort_unless(ctype_digit($learner), 404);
        $target = $this->target((int) $learner);
        $this->learnerId = (int) $target->getKey();

        $record = app(Learner360Record::class)->for($this->viewer(), $target);
        app(RecordLearner360View::class)($this->viewer(), $target, $record['sections']);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('support.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function startViewAsAction(): Action
    {
        return Action::make('startViewAs')
            ->label(__('support.view_as_start'))
            ->icon('heroicon-o-eye')
            ->modalHeading(__('support.view_as_start'))
            ->modalDescription(__('support.view_as_explainer'))
            ->schema([
                Textarea::make('reason')->label(__('support.reason'))->helperText(__('support.reason_help', ['min' => config()->integer('support.view_as.reason_min')]))
                    ->required()->minLength(config()->integer('support.view_as.reason_min'))->maxLength(config()->integer('support.view_as.reason_max')),
            ])
            ->action(function (array $data) {
                $learner = $this->target((int) $this->learnerId);

                try {
                    app(StartViewAs::class)($this->viewer(), $learner, (string) $data['reason']);
                } catch (ViewAsRefusedException $refused) {
                    Notification::make()->danger()->title(__('support.refused'))->body($refused->refusal->label())->send();

                    return null;
                }

                return redirect()->route('support.view-as.home');
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);
        $viewer = $this->viewer();

        if ($this->learnerId === null) {
            return ['results' => app(LearnerDirectory::class)->search($viewer, $this->search), 'record' => null, 'refusal' => null, 'learnerId' => null];
        }

        $learner = $this->target($this->learnerId);

        return [
            'results' => [],
            'record' => app(Learner360Record::class)->for($viewer, $learner),
            'refusal' => app(ViewAsEligibility::class)->refusal($viewer, $learner),
            'learnerId' => $this->learnerId,
            'ttl' => config()->integer('support.view_as.ttl_minutes'),
        ];
    }

    private function target(int $id): User
    {
        $learner = User::query()->find($id);
        abort_if($learner === null || ! app(Learner360Record::class)->canView($this->viewer(), $learner), 404);

        return $learner;
    }

    private function viewer(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
