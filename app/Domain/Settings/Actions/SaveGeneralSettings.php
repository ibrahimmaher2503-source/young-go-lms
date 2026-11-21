<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\MarketsSettings;
use App\Domain\Shared\Markets\Market;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Saves site identity, contact, social links, default locale/market, maintenance banner and mail sender identity. Needs system.site_settings.manage (super_admin only by default); audited with before/after values. */
final class SaveGeneralSettings
{
    /** @var list<string> */
    public const array TEXT_FIELDS = [
        'site_name_ar', 'site_name_en', 'tagline_ar', 'tagline_en', 'contact_email', 'contact_phone', 'contact_address_ar', 'contact_address_en',
        'social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_x', 'social_linkedin',
        'banner_text_ar', 'banner_text_en', 'mail_from_name', 'mail_from_address', 'mail_reply_to',
    ];

    public function __construct(private readonly GeneralSettings $general, private readonly MarketsSettings $markets, private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, array $data): void
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('system.site_settings.manage')) {
            throw new AuthorizationException;
        }

        $url = ['nullable', 'string', 'max:255', 'url:http,https'];
        $markets = array_values(array_map(
            static fn (Market $market): string => $market->value,
            array_filter(Market::cases(), static fn (Market $market): bool => $market->isActive()),
        ));

        $clean = Validator::make($data, [
            'default_locale' => ['required', Rule::in(['ar', 'en'])],
            'default_market' => ['required', Rule::in($markets)],
            'site_name_ar' => ['nullable', 'string', 'max:120'],
            'site_name_en' => ['nullable', 'string', 'max:120'],
            'tagline_ar' => ['nullable', 'string', 'max:200'],
            'tagline_en' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'string', 'email:rfc', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ()-]{6,24}$/'],
            'contact_address_ar' => ['nullable', 'string', 'max:300'],
            'contact_address_en' => ['nullable', 'string', 'max:300'],
            'social_facebook' => $url,
            'social_instagram' => $url,
            'social_youtube' => $url,
            'social_tiktok' => $url,
            'social_x' => $url,
            'social_linkedin' => $url,
            'banner_enabled' => ['boolean'],
            'require_email_verification' => ['boolean'],
            'banner_text_ar' => ['nullable', 'string', 'max:300'],
            'banner_text_en' => ['nullable', 'string', 'max:300'],
            'mail_from_name' => ['nullable', 'string', 'max:120', 'regex:/^[^\r\n<>]*$/'],
            'mail_from_address' => ['nullable', 'string', 'email:rfc', 'max:190'],
            'mail_reply_to' => ['nullable', 'string', 'email:rfc', 'max:190'],
        ], [], (array) __('settings.fields'))->validate();

        $after = [
            'default_locale' => (string) $clean['default_locale'],
            'default_market' => (string) $clean['default_market'],
            'banner_enabled' => (bool) ($clean['banner_enabled'] ?? false),
            'require_email_verification' => (bool) ($clean['require_email_verification'] ?? $this->general->require_email_verification),
        ];
        foreach (self::TEXT_FIELDS as $field) {
            $value = $clean[$field] ?? null;
            $after[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        $before = [
            'default_locale' => $this->general->default_locale,
            'default_market' => $this->markets->default_market,
            'banner_enabled' => $this->general->banner_enabled,
            'require_email_verification' => $this->general->require_email_verification,
        ];
        foreach (self::TEXT_FIELDS as $field) {
            $before[$field] = $this->general->{$field};
        }

        if ($before === $after) {
            return;
        }

        DB::transaction(function () use ($actor, $before, $after): void {
            $this->general->default_locale = $after['default_locale'];
            $this->general->banner_enabled = $after['banner_enabled'];
            $this->general->require_email_verification = $after['require_email_verification'];
            foreach (self::TEXT_FIELDS as $field) {
                $this->general->{$field} = $after[$field];
            }
            $this->markets->default_market = $after['default_market'];
            $this->general->save();
            $this->markets->save();

            if ($this->audit->record('settings.general_saved', $actor, (int) $actor->getKey(), $before, $after) === null) {
                throw new RuntimeException('Unable to record general settings audit.');
            }
        });
    }
}
