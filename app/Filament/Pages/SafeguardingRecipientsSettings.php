<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Settings\Actions\SaveSafeguardingRecipients;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/** O-028: extra email recipients of mandatory safeguarding notices. The page only collects input; SaveSafeguardingRecipients authorizes, validates and audits. */
final class SafeguardingRecipientsSettings extends Page
{
    protected static ?string $slug = 'safeguarding-recipients';

    protected string $view = 'filament.settings.safeguarding';

    public static function getNavigationLabel(): string
    {
        return __('settings.safeguarding.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shield-check';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->can('system.settings.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('settings.safeguarding.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('settings.edit'))->modalHeading(__('settings.safeguarding.title'))->modalDescription(__('settings.safeguarding.intro'))
            ->schema([
                TagsInput::make('emails')->label(__('settings.safeguarding.emails'))->helperText(__('settings.safeguarding.emails_help', ['max' => SaveSafeguardingRecipients::MAX_EMAILS]))
                    ->nestedRecursiveRules(['email', 'max:190'])->extraInputAttributes(['dir' => 'ltr']),
            ])
            ->fillForm(fn (): array => ['emails' => app(SaveSafeguardingRecipients::class)->emails()])
            ->action(function (array $data, Action $action): void {
                $user = Auth::user();
                abort_unless($user instanceof User, 401);

                try {
                    app(SaveSafeguardingRecipients::class)($user, is_array($data['emails'] ?? null) ? $data['emails'] : []);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('settings.denied'))->send();
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->danger()->title(__('settings.failed'))->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title(__('settings.saved'))->send();
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['emails' => app(SaveSafeguardingRecipients::class)->emails(), 'roles' => collect((array) config('safeguarding.recipient_roles', []))->map(static fn (mixed $role): string => trans()->has('roles.'.$role) ? (string) __('roles.'.$role) : (string) $role)->values()->all()];
    }
}
