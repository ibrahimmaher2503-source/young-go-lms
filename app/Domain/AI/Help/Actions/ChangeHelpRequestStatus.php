<?php

declare(strict_types=1);

namespace App\Domain\AI\Help\Actions;

use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\AI\Help\HelpRequestAccess;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\AI\Help\HelpRequestTrail;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff moves a request they may see: New to In Progress, any open request to Resolved, an open or resolved one to Closed.
 * Resolving or closing stops the SLA clock. Every change is an event row and an audit entry.
 */
final class ChangeHelpRequestStatus
{
    public function __construct(private readonly HelpRequestTrail $trail) {}

    public function __invoke(User $staff, HelpRequest $request, HelpRequestStatus $to): HelpRequest
    {
        HelpRequestAccess::authorize($staff, $request);

        return DB::transaction(function () use ($staff, $request, $to): HelpRequest {
            $locked = HelpRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $allowed = match ($to) {
                HelpRequestStatus::InProgress => $from === HelpRequestStatus::New,
                HelpRequestStatus::Resolved => $from->isOpen(),
                HelpRequestStatus::Closed => $from->isOpen() || $from === HelpRequestStatus::Resolved,
                default => false,
            };
            if (! $allowed) {
                throw new HelpRefusedException(HelpRefusedException::BAD_TRANSITION);
            }

            $changes = ['status' => $to];
            if ($to === HelpRequestStatus::Resolved) {
                $changes += ['resolved_at' => now(), 'due_at' => null];
            }
            if ($to === HelpRequestStatus::Closed) {
                $changes += ['closed_at' => now(), 'due_at' => null];
            }
            $locked->forceFill($changes)->save();
            $this->trail->record($locked, 'status_changed', $staff, $from, $to);

            return $locked;
        });
    }
}
