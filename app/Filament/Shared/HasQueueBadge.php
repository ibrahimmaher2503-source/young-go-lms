<?php

declare(strict_types=1);

namespace App\Filament\Shared;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Sidebar work-queue badge: how many records wait for staff. The count runs through the resource's own scoped query
 * (so an instructor only counts their own courses), is cached per user for a minute, and is never shown to a user who
 * cannot open the resource.
 */
trait HasQueueBadge
{
    /** Number of records waiting for action, from the resource's own (already scoped) query. */
    abstract protected static function queueCount(): int;

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::id();
        if ($user === null || ! static::canAccess()) {
            return null;
        }

        $count = Cache::remember('nav-badge:'.static::class.':'.$user, 60, static fn (): int => static::queueCount());

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('navigation.badge_waiting');
    }
}
