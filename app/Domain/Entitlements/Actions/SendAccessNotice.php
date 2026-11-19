<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\AccessNotice;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SendAccessNotice
{
    public function __construct(private readonly EntitlementService $access, private readonly NotificationDispatcher $dispatcher) {}

    public function afterCommit(Entitlement $entitlement, bool $granted): void
    {
        DB::afterCommit(function () use ($entitlement, $granted): void {
            try {
                $entitlement = $entitlement->fresh(['learner', 'course']);
                if ($entitlement === null || $entitlement->learner->getAttribute('status') !== 'active') {
                    return;
                }

                $this->access->flush();
                if ($this->access->canAccessCourse($entitlement->learner, $entitlement->course)->allowed !== $granted
                    || ($granted && ! $entitlement->isCurrent())) {
                    return;
                }

                $notice = new AccessNotice($granted ? 'access.granted' : 'access.revoked', $entitlement->course, (string) $entitlement->getKey());
                $this->dispatcher->send($entitlement->learner, $notice);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
