<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

/** The only shapes of vendor IDs we ever print into a page (they end up inside inline script, so nothing else is accepted). */
final class MarketingTagIds
{
    public const string GA4_PATTERN = '/^G-[A-Z0-9]{4,20}$/';

    public const string META_PIXEL_PATTERN = '/^\d{6,20}$/';

    public static function ga4(?string $value): ?string
    {
        return $value !== null && preg_match(self::GA4_PATTERN, $value) === 1 ? $value : null;
    }

    public static function metaPixel(?string $value): ?string
    {
        return $value !== null && preg_match(self::META_PIXEL_PATTERN, $value) === 1 ? $value : null;
    }
}
