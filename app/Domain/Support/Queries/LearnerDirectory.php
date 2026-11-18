<?php

declare(strict_types=1);

namespace App\Domain\Support\Queries;

use App\Domain\Support\Learner360Mask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds a learner for Learner 360 by name, exact email, exact username or id. Staff accounts are never listed. Email is
 * returned masked, so a search result alone does not disclose contact details.
 */
final class LearnerDirectory
{
    /** @return list<array{id: int, name: string, email: string|null, username: string|null}> */
    public function search(User $viewer, string $term): array
    {
        if (! $viewer->can('learner.record.view')) {
            return [];
        }

        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.addcslashes($term, '\%_').'%';

        return User::query()
            ->where(static fn (Builder $query): Builder => $query
                ->where('name', 'like', $like)
                ->orWhere('display_name', 'like', $like)
                ->orWhereRaw('lower(email) = ?', [mb_strtolower($term)])
                ->orWhereRaw('lower(username) = ?', [mb_strtolower($term)])
                ->when(ctype_digit($term), static fn (Builder $q): Builder => $q->orWhere('id', (int) $term)))
            ->whereDoesntHave('roles', static fn (Builder $roles): Builder => $roles->whereNotIn('name', ['student', 'parent']))
            ->orderBy('name')
            ->limit(config()->integer('support.learner_360.search_limit'))
            ->get(['id', 'name', 'display_name', 'email', 'username'])
            ->map(static fn (User $user): array => [
                'id' => (int) $user->getKey(),
                'name' => (string) ($user->getAttribute('display_name') ?: $user->name),
                'email' => Learner360Mask::email($user->email),
                'username' => $user->getAttribute('username') === null ? null : (string) $user->getAttribute('username'),
            ])->all();
    }
}
