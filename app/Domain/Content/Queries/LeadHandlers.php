<?php

declare(strict_types=1);

namespace App\Domain\Content\Queries;

use App\Models\User;

/** Active staff who may handle leads: the people an enquiry can be assigned to. */
final class LeadHandlers
{
    /** @return array<int, string> user id => name */
    public function options(): array
    {
        return User::query()->where('status', 'active')->orderBy('name')->get()
            ->filter(static fn (User $user): bool => $user->can('marketing.lead.view'))
            ->mapWithKeys(static fn (User $user): array => [(int) $user->getKey() => (string) $user->name])
            ->all();
    }
}
