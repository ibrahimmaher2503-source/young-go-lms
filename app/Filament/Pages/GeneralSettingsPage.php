<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Settings\Actions\SaveGeneralSettings;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\MarketsSettings;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/** T-P9-09: site identity, contact, social links, default locale/market, maintenance banner and mail sender. The page only collects input; SaveGeneralSettings authorizes, validates and audits. */
final class GeneralSettingsPage extends Page
{
    protected static ?string $slug = 'general-settings';

    protected string $view = 'filament.settings.general';

    public static function getNavigationLabel(): string
    {
        return __('settings.general.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-building-storefront';
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
        return __('settings.general.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function editAction(): Action
    {
        $label = static fn (string $key): string => (string) __('settings.fields.'.$key);
        $text = static fn (string $key, int $max = 190): TextInput => TextInput::make($key)->label($label($key))->maxLength($max);

        return Action::make('edit')->label(__('settings.edit'))->modalHeading(__('settings.general.title'))->modalDescription(__('settings.general.intro'))->modalWidth('3xl')
            ->schema([
                Section::make(__('settings.sections.identity'))->columns(2)->schema([
                    $text('site_name_ar', 120)->extraInputAttributes(['dir' => 'rtl']), $text('site_name_en', 120)->extraInputAttributes(['dir' => 'ltr']),
                    $text('tagline_ar', 200)->extraInputAttributes(['dir' => 'rtl']), $text('tagline_en', 200)->extraInputAttributes(['dir' => 'ltr']),
                ]),
                Section::make(__('settings.sections.contact'))->columns(2)->schema([
                    $text('contact_email')->email(), $text('contact_phone', 24)->tel(),
                    Textarea::make('contact_address_ar')->label($label('contact_address_ar'))->maxLength(300)->rows(2), Textarea::make('contact_address_en')->label($label('contact_address_en'))->maxLength(300)->rows(2),
                ]),
                Section::make(__('settings.sections.social'))->columns(2)->schema([
                    $text('social_facebook', 255)->url(), $text('social_instagram', 255)->url(), $text('social_youtube', 255)->url(),
                    $text('social_tiktok', 255)->url(), $text('social_x', 255)->url(), $text('social_linkedin', 255)->url(),
                ]),
                Section::make(__('settings.sections.defaults'))->columns(2)->schema([
                    Select::make('default_locale')->label($label('default_locale'))->options(['ar' => __('settings.locale.ar'), 'en' => __('settings.locale.en')])->required()->native(false),
                    Select::make('default_market')->label($label('default_market'))->options(['EG' => __('settings.market.EG')])->required()->native(false)->helperText(__('settings.market_help')),
                ]),
                Section::make(__('settings.sections.banner'))->columns(2)->schema([
                    Toggle::make('banner_enabled')->label($label('banner_enabled'))->columnSpanFull(),
                    Textarea::make('banner_text_ar')->label($label('banner_text_ar'))->maxLength(300)->rows(2), Textarea::make('banner_text_en')->label($label('banner_text_en'))->maxLength(300)->rows(2),
                ]),
                Section::make(__('settings.sections.mail'))->columns(2)->description(__('settings.mail_help'))->schema([
                    $text('mail_from_name', 120), $text('mail_from_address')->email(), $text('mail_reply_to')->email(),
                ]),
                Section::make(__('settings.sections.registration'))->schema([
                    Toggle::make('require_email_verification')->label($label('require_email_verification'))->helperText(__('settings.email_verification_help')),
                ]),
            ])
            ->fillForm(fn (): array => $this->values())
            ->action(function (array $data, Action $action): void {
                $user = Auth::user();
                abort_unless($user instanceof User, 401);

                try {
                    app(SaveGeneralSettings::class)($user, $data);
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
    private function values(): array
    {
        $general = app(GeneralSettings::class);
        $values = ['default_locale' => $general->default_locale, 'default_market' => app(MarketsSettings::class)->default_market, 'banner_enabled' => $general->banner_enabled];
        $values['require_email_verification'] = $general->require_email_verification;
        foreach (SaveGeneralSettings::TEXT_FIELDS as $field) {
            $values[$field] = $general->{$field};
        }

        return $values;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $values = $this->values();
        $sections = [
            'identity' => ['site_name_ar', 'site_name_en', 'tagline_ar', 'tagline_en'],
            'contact' => ['contact_email', 'contact_phone', 'contact_address_ar', 'contact_address_en'],
            'social' => ['social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_x', 'social_linkedin'],
            'defaults' => ['default_locale', 'default_market'],
            'banner' => ['banner_enabled', 'banner_text_ar', 'banner_text_en'],
            'mail' => ['mail_from_name', 'mail_from_address', 'mail_reply_to'],
            'registration' => ['require_email_verification'],
        ];
        $rows = [];
        foreach ($sections as $section => $keys) {
            foreach ($keys as $key) {
                $value = $values[$key];
                $rows[$section][] = [
                    'key' => $key,
                    'label' => __('settings.fields.'.$key),
                    'value' => match (true) {
                        is_bool($value) => __($value ? 'settings.on' : 'settings.off'),
                        $key === 'default_locale' => __('settings.locale.'.$value),
                        $key === 'default_market' => __('settings.market.'.$value),
                        default => is_string($value) && $value !== '' ? $value : null,
                    },
                ];
            }
        }

        return ['rows' => $rows];
    }
}
