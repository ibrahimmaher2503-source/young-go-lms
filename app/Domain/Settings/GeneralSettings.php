<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/** Site identity, contact, social links, default locale, maintenance banner and mail sender identity (T-P9-09). Null = not set, the app falls back to its translated defaults. */
final class GeneralSettings extends Settings
{
    public string $default_locale;

    public ?string $site_name_ar;

    public ?string $site_name_en;

    public ?string $tagline_ar;

    public ?string $tagline_en;

    public ?string $contact_email;

    public ?string $contact_phone;

    public ?string $contact_address_ar;

    public ?string $contact_address_en;

    public ?string $social_facebook;

    public ?string $social_instagram;

    public ?string $social_youtube;

    public ?string $social_tiktok;

    public ?string $social_x;

    public ?string $social_linkedin;

    public bool $banner_enabled;

    public ?string $banner_text_ar;

    public ?string $banner_text_en;

    public ?string $mail_from_name;

    public ?string $mail_from_address;

    public ?string $mail_reply_to;

    public static function group(): string
    {
        return 'general';
    }
}
