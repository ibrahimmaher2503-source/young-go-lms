<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Queries;

use App\Domain\Privacy\MarketingTagIds;
use App\Domain\Settings\MarketingSettings;

/** The vendor IDs configured by an administrator and valid in shape. Empty by default: nothing is enabled until settings say so. */
final class ActiveMarketingTags
{
    public function __construct(private readonly MarketingSettings $settings) {}

    /** @return array{ga4: ?string, meta_pixel: ?string} */
    public function __invoke(): array
    {
        return [
            'ga4' => MarketingTagIds::ga4($this->settings->ga4_measurement_id),
            'meta_pixel' => MarketingTagIds::metaPixel($this->settings->meta_pixel_id),
        ];
    }

    public function configured(): bool
    {
        return array_filter($this()) !== [];
    }
}
