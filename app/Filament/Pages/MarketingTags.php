<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Privacy\CookieConsent;
use App\Domain\Settings\Actions\SaveMarketingTags;
use App\Domain\Settings\MarketingSettings;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/** T-P9-03: GA4 and Meta Pixel IDs. The page only collects input; SaveMarketingTags authorizes, validates and audits. */
final class MarketingTags extends Page
{
    protected static ?string $slug = 'marketing-tags';

    protected string $view = 'filament.marketing.tags';

    public static function getNavigationLabel(): string
    {
        return __('consent.admin.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->can('content.seo.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('consent.admin.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('consent.admin.edit'))->modalHeading(__('consent.admin.title'))->modalDescription(__('consent.admin.intro'))
            ->schema([
                TextInput::make('ga4_measurement_id')->label(__('consent.admin.fields.ga4_measurement_id'))->helperText(__('consent.admin.ga4_help'))->maxLength(24),
                TextInput::make('meta_pixel_id')->label(__('consent.admin.fields.meta_pixel_id'))->helperText(__('consent.admin.pixel_help'))->maxLength(20),
            ])
            ->fillForm(fn (): array => ['ga4_measurement_id' => app(MarketingSettings::class)->ga4_measurement_id, 'meta_pixel_id' => app(MarketingSettings::class)->meta_pixel_id])
            ->action(function (array $data, Action $action): void {
                $user = Auth::user();
                abort_unless($user instanceof User, 401);

                try {
                    app(SaveMarketingTags::class)($user, $data);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title(__('consent.admin.denied'))->send();
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->danger()->title(__('consent.admin.failed'))->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title(__('consent.admin.saved'))->send();
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $settings = app(MarketingSettings::class);

        return ['ga4' => $settings->ga4_measurement_id, 'pixel' => $settings->meta_pixel_id, 'version' => CookieConsent::version()];
    }
}
