<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Settings\Actions\SaveManualPaymentPayee;
use App\Domain\Settings\PaymentsSettings;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/** T-P9-09: where payers send manual (Instapay/bank) transfers. Stored in settings, falls back to MANUAL_PAYMENT_PAYEE. Details are never audited by value. */
final class PaymentPayeeSettings extends Page
{
    protected static ?string $slug = 'payment-payee';

    protected string $view = 'filament.settings.payee';

    public static function getNavigationLabel(): string
    {
        return __('settings.payee.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-banknotes';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->can('system.site_settings.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('settings.payee.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('settings.edit'))->modalHeading(__('settings.payee.title'))->modalDescription(__('settings.payee.intro'))
            ->schema([Textarea::make('manual_payee')->label(__('settings.fields.manual_payee'))->helperText(__('settings.payee.help'))->maxLength(500)->rows(3)])
            ->fillForm(fn (): array => ['manual_payee' => app(PaymentsSettings::class)->manual_payee])
            ->action(function (array $data, Action $action): void {
                $user = Auth::user();
                abort_unless($user instanceof User, 401);

                try {
                    app(SaveManualPaymentPayee::class)($user, $data);
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
        $payee = app(PaymentsSettings::class)->manual_payee;

        return ['payee' => $payee, 'source' => $payee !== null ? 'settings' : (filled(config('manual_payments.payee')) ? 'environment' : 'none')];
    }
}
