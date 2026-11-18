<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Illuminate\Support\Collection;

/** Who the payer may buy for: themselves, plus the verified children when the payer is a parent (YG-GAP-FAM-005). */
final class CheckoutLearners
{
    /** @return Collection<int, User> the payer first, then linked children */
    public function __invoke(User $payer): Collection
    {
        $learners = new Collection([$payer]);
        if (! $payer->hasRole('parent')) {
            return $learners;
        }

        $children = GuardianChildLink::query()->with('child')->where('guardian_id', $payer->getKey())
            ->where('status', GuardianChildLink::STATUS_VERIFIED)->orderBy('id')->get()
            ->map(static fn (GuardianChildLink $link): ?User => $link->child)
            ->filter(static fn (?User $child): bool => $child instanceof User && $child->getAttribute('status') === 'active');

        return $learners->concat($children->values())->values();
    }
}
