<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;

/**
 * Single source for "which learner is this request acting as".
 * Learner-facing queries must scope by current(), never by Auth::user() directly.
 */
final class ActingLearner
{
    public const string SESSION_KEY = 'acting_child_id';

    public function current(): ?User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        return $this->child($user) ?? $user;
    }

    public function isParentContext(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $this->child($user) !== null;
    }

    /** The authenticated parent while acting for a child, otherwise null. */
    public function actingParent(): ?User
    {
        $user = Auth::user();

        return $user instanceof User && $this->child($user) !== null ? $user : null;
    }

    /** O-016: parent context never exposes a child's private AI conversations. */
    public function canViewPrivateLearnerConversations(): bool
    {
        return ! $this->isParentContext();
    }

    public function enter(User $child): void
    {
        Session::put(self::SESSION_KEY, (int) $child->getKey());
    }

    public function exit(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    private function child(User $parent): ?User
    {
        $childId = Session::get(self::SESSION_KEY);
        if (! is_int($childId)) {
            return null;
        }

        $child = User::query()->find($childId);
        if ($child === null || ! Gate::forUser($parent)->allows('guardian.child.access', $child)) {
            $this->exit();

            return null;
        }

        return $child;
    }
}
