<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/** Issuer wording printed on certificates. Final legal text is OPEN (O-019): null falls back to a neutral placeholder. */
final class CertificateSettings extends Settings
{
    public ?string $issuer_text_ar;

    public ?string $issuer_text_en;

    public static function group(): string
    {
        return 'certificates';
    }
}
