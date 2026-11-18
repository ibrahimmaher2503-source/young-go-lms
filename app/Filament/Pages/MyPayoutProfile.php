<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Instructors\Actions\SavePayoutProfile;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Domain\Instructors\PayoutMask;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * T-P9-01: the instructor's own payout destination (YG-GAP-INS-003). The form is WRITE-ONLY: stored values are never loaded
 * into form state (Livewire would ship them to the browser); the page shows a masked hint only. Saving replaces everything.
 */
final class MyPayoutProfile extends Page
{
    protected static ?string $slug = 'my-payout-profile';

    protected string $view = 'filament.instructors.payout';

    public static function getNavigationLabel(): string
    {
        return __('instructor_onboarding.payout.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-banknotes';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->hasAnyRole(PublicInstructors::ROLES);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('instructor_onboarding.payout.page_title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('instructor_onboarding.payout.edit'))->modalHeading(__('instructor_onboarding.payout.page_title'))
            ->modalDescription(__('instructor_onboarding.payout.write_only_note'))
            ->schema([
                Select::make('method')->label(__('instructor_onboarding.payout.method'))->required()->live()
                    ->options([PayoutProfile::BANK => __('instructor_onboarding.payout.method_bank'), PayoutProfile::WALLET => __('instructor_onboarding.payout.method_wallet')]),
                TextInput::make('holder_name')->label(__('instructor_onboarding.payout.field_holder_name'))->required()->maxLength(120),
                TextInput::make('bank_name')->label(__('instructor_onboarding.payout.field_bank_name'))->maxLength(120)
                    ->visible(static fn ($get): bool => $get('method') === PayoutProfile::BANK)->required(static fn ($get): bool => $get('method') === PayoutProfile::BANK),
                TextInput::make('account_number')->label(__('instructor_onboarding.payout.field_account_number'))->maxLength(48)->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => 'off'])
                    ->visible(static fn ($get): bool => $get('method') === PayoutProfile::BANK)->required(static fn ($get): bool => $get('method') === PayoutProfile::BANK),
                TextInput::make('wallet_number')->label(__('instructor_onboarding.payout.field_wallet_number'))->maxLength(24)->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => 'off'])
                    ->visible(static fn ($get): bool => $get('method') === PayoutProfile::WALLET)->required(static fn ($get): bool => $get('method') === PayoutProfile::WALLET),
            ])
            ->action(function (array $data, Action $action): void {
                $user = Auth::user();
                abort_unless($user instanceof User, 401);

                try {
                    app(SavePayoutProfile::class)($user, $data);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title(__('instructor_onboarding.payout.saved'))->send();
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $profile = $user instanceof User ? PayoutProfile::query()->where('user_id', $user->getKey())->first() : null;

        return [
            'method' => $profile?->method,
            'masked' => $profile !== null ? PayoutMask::identifier($profile) : null,
            'updatedAt' => $profile?->updated_at,
        ];
    }
}
