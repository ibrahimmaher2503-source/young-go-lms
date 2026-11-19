<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\AccessNotice;
use Carbon\CarbonInterface;
use Throwable;

final class SendAccessExpiryNotices
{
    private const array OFFSETS = [1, 3, 7];

    public function __construct(private readonly EntitlementService $access, private readonly NotificationDispatcher $dispatcher) {}

    public function __invoke(): int
    {
        $now = now();
        $candidates = Entitlement::query()->select(['learner_id', 'course_id'])->distinct()
            ->withAccessState(Entitlement::ACTIVE)
            ->where('ends_at', '<=', $now->copy()->addDays(max(self::OFFSETS)))
            ->with(['learner', 'course'])->orderBy('learner_id')->orderBy('course_id')->lazy();

        $sent = 0;
        foreach ($candidates as $candidate) {
            try {
                if ($this->remind($candidate, $now)) {
                    $sent++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $sent;
    }

    private function remind(Entitlement $candidate, CarbonInterface $now): bool
    {
        $this->access->flush();
        if (! $this->access->canAccessCourse($candidate->learner, $candidate->course)->allowed) {
            return false;
        }

        $grants = Entitlement::query()
            ->where('learner_id', $candidate->learner_id)->where('course_id', $candidate->course_id)
            ->where('status', Entitlement::ACTIVE)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('starts_at')->get();
        $startsAt = $grants->first()?->starts_at;
        if ($startsAt === null || $startsAt->gt($now)) {
            return false;
        }

        $endsAt = $grants->first()->ends_at;
        // Merge consecutive grants until there is a gap. A renewal can extend the window before it starts.
        foreach ($grants->skip(1) as $grant) {
            if ($endsAt === null || $grant->starts_at->gt($endsAt)) {
                break;
            }
            if ($grant->ends_at === null || $grant->ends_at->gt($endsAt)) {
                $endsAt = $grant->ends_at;
            }
        }
        if ($endsAt === null) {
            return false;
        }

        foreach (self::OFFSETS as $offset) {
            $point = $endsAt->copy()->subDays($offset);
            if ($point->lte($now) && $now->lt($point->copy()->addDay()) && $point->gte($startsAt)) {
                $key = $candidate->course_id.':'.$endsAt->timestamp.':'.$offset;

                return $this->dispatcher->send($candidate->learner, new AccessNotice('access.expiring', $candidate->course, $key, $endsAt));
            }
        }

        return false;
    }
}
