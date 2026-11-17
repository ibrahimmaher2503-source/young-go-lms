<?php

declare(strict_types=1);

namespace App\Domain\Library;

use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Who sees which library items: staff with media rights see everything, instructors see the academy's items and their own. */
final class LibraryAccess
{
    public static function managesAll(User $user): bool
    {
        return $user->getAttribute('status') === 'active' && $user->can('media.manage');
    }

    public static function mayUseLibrary(User $user): bool
    {
        return $user->getAttribute('status') === 'active' && ($user->can('media.manage') || $user->can('panel.instructor.access'));
    }

    /** @return Builder<LibraryItem> */
    public static function visibleTo(User $user): Builder
    {
        $query = LibraryItem::query();
        if (! self::mayUseLibrary($user)) {
            return $query->whereRaw('1 = 0');
        }
        if (self::managesAll($user)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('owner_type', LibraryItem::OWNER_ACADEMY)
            ->orWhere(fn (Builder $own) => $own->where('owner_type', LibraryItem::OWNER_INSTRUCTOR)->where('owner_id', $user->getKey())));
    }

    public static function canUse(User $user, LibraryItem $item): bool
    {
        return self::visibleTo($user)->whereKey($item->getKey())->exists();
    }

    /** Only the owner (or staff who manage all) may remove an item. Academy items need the media permission. */
    public static function canDelete(User $user, LibraryItem $item): bool
    {
        if (self::managesAll($user)) {
            return true;
        }

        return $item->owner_type === LibraryItem::OWNER_INSTRUCTOR && (int) $item->owner_id === (int) $user->getKey() && self::mayUseLibrary($user);
    }
}
