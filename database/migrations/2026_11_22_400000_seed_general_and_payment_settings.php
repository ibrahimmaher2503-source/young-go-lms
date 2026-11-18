<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** T-P9-09: general site settings and the manual payment payee move into the settings store. Everything starts empty so existing behaviour (translated defaults, MANUAL_PAYMENT_PAYEE, mail config) is unchanged. */
return new class extends SettingsMigration
{
    /** @var list<string> */
    private const array NULLABLE_GENERAL = [
        'site_name_ar', 'site_name_en', 'tagline_ar', 'tagline_en', 'contact_email', 'contact_phone', 'contact_address_ar', 'contact_address_en',
        'social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_x', 'social_linkedin',
        'banner_text_ar', 'banner_text_en', 'mail_from_name', 'mail_from_address', 'mail_reply_to',
    ];

    public function up(): void
    {
        foreach (self::NULLABLE_GENERAL as $key) {
            $this->migrator->add('general.'.$key, null);
        }

        $this->migrator->add('general.banner_enabled', false);
        $this->migrator->add('payments.manual_payee', null);
    }

    public function down(): void
    {
        foreach (self::NULLABLE_GENERAL as $key) {
            $this->migrator->delete('general.'.$key);
        }

        $this->migrator->delete('general.banner_enabled');
        $this->migrator->delete('payments.manual_payee');
    }
};
