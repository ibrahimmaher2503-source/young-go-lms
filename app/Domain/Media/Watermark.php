<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Str;

/** The text laid over protected playback: the learner id and a masked address, so a leaked recording points back to the account. */
final class Watermark
{
    /** Protected (T1) and restricted (T2) sources carry it; open sources (T3) are public anyway. */
    public static function applies(string $tier): bool
    {
        return in_array($tier, [MediaAsset::TIER_PROTECTED, MediaAsset::TIER_RESTRICTED], true);
    }

    public static function label(User $learner): string
    {
        $contact = (string) ($learner->getAttribute('email') ?? $learner->getAttribute('username') ?? '');
        $masked = '';
        if ($contact !== '') {
            [$local, $domain] = array_pad(explode('@', $contact, 2), 2, null);
            $masked = Str::mask($local, '*', min(2, max(0, mb_strlen($local) - 1))).($domain === null ? '' : '@'.Str::mask($domain, '*', min(2, max(0, mb_strlen($domain) - 1))));
        }

        return 'ID '.str_pad((string) $learner->getKey(), 6, '0', STR_PAD_LEFT).($masked === '' ? '' : ' · '.$masked);
    }
}
