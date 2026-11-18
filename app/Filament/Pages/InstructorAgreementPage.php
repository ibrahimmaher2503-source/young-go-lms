<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Instructors\Actions\AcceptInstructorAgreement;
use App\Domain\Instructors\Queries\InstructorAgreements;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * T-P9-01: the independent instructor reads and accepts the current agreement version (YG-GAP-INS-002). Until the newest
 * published version is accepted, course creation is refused by CoursePolicy. The page only collects the click; the Action stores
 * version, time and IP.
 */
final class InstructorAgreementPage extends Page
{
    protected static ?string $slug = 'instructor-agreement';

    protected string $view = 'filament.instructors.agreement';

    public static function getNavigationLabel(): string
    {
        return __('instructor_onboarding.agreement.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-check';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->hasRole('instructor_independent');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('instructor_onboarding.agreement.page_title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function acceptAction(): Action
    {
        return Action::make('accept')->label(__('instructor_onboarding.agreement.accept'))->requiresConfirmation()
            ->modalDescription(__('instructor_onboarding.agreement.accept_confirm'))
            ->action(function (Action $action): void {
                $user = Auth::user();
                abort_unless($user instanceof User, 401);
                $agreement = InstructorAgreements::current();
                if ($agreement === null) {
                    return;
                }

                try {
                    app(AcceptInstructorAgreement::class)($user, $agreement, request()->ip());
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title(__('instructor_onboarding.agreement.accepted_ok'))->send();
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $agreement = InstructorAgreements::current();

        return [
            'agreement' => $agreement,
            'accepted' => $agreement !== null && $user instanceof User && InstructorAgreements::hasAccepted($user, $agreement),
        ];
    }
}
