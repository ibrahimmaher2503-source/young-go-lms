<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Models\UserSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Support action: forget every registered device of a learner and sign them out everywhere so they can register again. */
final class ResetUserDevices
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, User $learner): int
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('user.manage')
            || ! $learner->hasAnyRole(config()->array('security.sessions.roles'))) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $learner): int {
            UserSession::query()->where('user_id', $learner->getKey())->whereNull('signed_out_at')
                ->update(['signed_out_at' => now(), 'signed_out_reason' => UserSession::RESET]);
            UserSession::query()->where('user_id', $learner->getKey())->update(['device_id' => null]);
            $removed = UserDevice::query()->where('user_id', $learner->getKey())->delete();

            if ($this->audit->record('security.devices_reset', $learner, (int) $actor->getKey(), null, ['devices_removed' => $removed]) === null) {
                throw new RuntimeException('Unable to record security audit.');
            }

            return $removed;
        });
    }
}
