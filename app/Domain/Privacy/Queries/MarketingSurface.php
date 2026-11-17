<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Queries;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * R-021 / YG-GAP-MKT-001: may marketing tags (and the consent banner that goes with them) appear on this page at all? Only the
 * public shell qualifies. Learning, parent, child, auth, error, payment and assistant screens never do, nor does a page whose
 * route is on the exclusion list (for example the public lesson preview, which shows lesson content).
 */
final class MarketingSurface
{
    public function __invoke(string $variant, bool $minimal, ?string $routeName, bool $childAccount, bool $parentContext): bool
    {
        if ($variant !== 'public' || $minimal || $childAccount || $parentContext || $routeName === null) {
            return false;
        }

        return ! Str::is(array_values(array_map('strval', Config::array('consent.excluded_routes'))), $routeName);
    }
}
