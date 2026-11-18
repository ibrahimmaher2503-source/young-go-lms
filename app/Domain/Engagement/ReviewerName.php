<?php

declare(strict_types=1);

namespace App\Domain\Engagement;

use App\Domain\Identity\GuardianAccess;
use App\Models\User;

/**
 * The only identity a public review carries (no PII beyond a display name). Everyone is shown as first name plus the
 * initial of the last name; a child account is never shown any other way, whatever display name it set (YG-GAP-ENG-002).
 * An adult may use the display name they chose, shortened to its first word and initial the same way.
 */
final class ReviewerName
{
    private const int MAX = 80;

    public static function isChild(User $user): bool
    {
        return GuardianAccess::isMinor($user);
    }

    public static function for(User $user): string
    {
        $source = self::isChild($user) ? (string) $user->getAttribute('name') : (string) (($user->getAttribute('display_name') ?: null) ?? $user->getAttribute('name'));
        $words = preg_split('/\s+/u', trim($source), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return (string) __('engagement.anonymous');
        }

        $first = mb_substr($words[0], 0, 30);
        $last = count($words) > 1 ? mb_substr($words[array_key_last($words)], 0, 1) : '';
        $initial = $last === '' ? '' : ' '.mb_strtoupper($last).'.';

        return mb_substr($first.$initial, 0, self::MAX);
    }
}
