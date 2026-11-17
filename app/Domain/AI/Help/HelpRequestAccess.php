<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may see and answer a help request (AI spec 12, PRD 15.5): staff with help_request.manage (admins, support) see every
 * request; staff with only help_request.reply (instructors) see exactly the requests assigned to them. Everyone else sees none.
 */
final class HelpRequestAccess
{
    public static function isStaff(User $viewer): bool
    {
        return $viewer->getAttribute('status') === 'active' && ($viewer->can('help_request.manage') || $viewer->can('help_request.reply'));
    }

    public static function manages(User $viewer): bool
    {
        return $viewer->getAttribute('status') === 'active' && $viewer->can('help_request.manage');
    }

    public static function allows(User $viewer, HelpRequest $request): bool
    {
        if (self::manages($viewer)) {
            return true;
        }

        return $viewer->getAttribute('status') === 'active' && $viewer->can('help_request.reply')
            && $request->assigned_instructor_id !== null && (int) $request->assigned_instructor_id === (int) $viewer->getKey();
    }

    public static function authorize(User $actor, HelpRequest $request): void
    {
        if (! self::allows($actor, $request)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, User $viewer): Builder
    {
        if (self::manages($viewer)) {
            return $query;
        }
        if ($viewer->getAttribute('status') === 'active' && $viewer->can('help_request.reply')) {
            return $query->where('assigned_instructor_id', $viewer->getKey());
        }

        return $query->whereRaw('1 = 0');
    }
}
