<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Queries;

use App\Domain\Subscriptions\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The subscriptions an account can see on its own subscription page: those it is the learner of and those it pays for (a
 * parent sees the plans of the children they pay for). Anyone else's subscription is not found here, so the page, renew and
 * cancel answer 404 for it.
 */
final class AccountSubscriptions
{
    /** @return Collection<int, Subscription> newest first, with plan (prices), learner and periods loaded */
    public function for(User $user): Collection
    {
        return $this->scope($user)->with(['plan.prices', 'learner', 'periods'])->latest('id')->get();
    }

    public function find(User $user, int $id): ?Subscription
    {
        return $this->scope($user)->with(['plan.prices', 'learner', 'periods'])->find($id);
    }

    /** @return Builder<Subscription> */
    private function scope(User $user): Builder
    {
        return Subscription::query()->where(static fn (Builder $q) => $q->where('learner_id', $user->getKey())->orWhere('payer_id', $user->getKey()));
    }
}
