<?php

declare(strict_types=1);

namespace App\Domain\AI\Help\Actions;

use App\Domain\AI\Help\HelpRequestNotices;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\AI\Help\HelpRequestTrail;
use App\Models\HelpRequest;
use Illuminate\Support\Facades\DB;

/**
 * SLA escalation (AI spec 12, default 24 hours): a request still waiting for the instructor after its due time is marked
 * overdue and the admins (and the assigned instructor) are told. Each overdue cycle fires once: a second run finds
 * nothing, and the notice key holds the due time. Returns how many requests were escalated.
 */
final class EscalateOverdueHelpRequests
{
    public function __construct(private readonly HelpRequestTrail $trail, private readonly HelpRequestNotices $notices) {}

    public function __invoke(): int
    {
        $count = 0;
        $ids = HelpRequest::query()->whereIn('status', HelpRequestStatus::awaitingValues())
            ->whereNull('overdue_at')->whereNotNull('due_at')->where('due_at', '<', now())->pluck('id');

        foreach ($ids as $id) {
            $request = DB::transaction(function () use ($id): ?HelpRequest {
                $locked = HelpRequest::query()->whereKey($id)->lockForUpdate()->first();
                if ($locked === null || $locked->overdue_at !== null || ! $locked->status->awaitsInstructor() || $locked->due_at === null || $locked->due_at->isFuture()) {
                    return null;
                }
                $locked->forceFill(['overdue_at' => now()])->save();
                $this->trail->record($locked, 'overdue', null, $locked->status, $locked->status, ['due_at' => $locked->due_at->toIso8601String()]);

                return $locked;
            });

            if ($request !== null) {
                $this->notices->overdue($request->loadMissing(['lesson', 'instructor']));
                $count++;
            }
        }

        return $count;
    }
}
