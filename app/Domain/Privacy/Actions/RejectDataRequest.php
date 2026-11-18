<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Staff (privacy.manage) refuse a request that is still open; the reason is shown to the requester. */
final class RejectDataRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, DataRequest $request, string $reason): DataRequest
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('privacy.manage')) {
            throw new AuthorizationException;
        }
        $reason = mb_substr(trim($reason), 0, 1000);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['reason' => __('privacy.error_reason')]);
        }

        return DB::transaction(function () use ($actor, $request, $reason): DataRequest {
            $locked = DataRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages(['request' => __('privacy.error_not_open')]);
            }
            $from = $locked->status->value;
            $locked->forceFill(['status' => DataRequestStatus::Rejected->value, 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $reason])->save();

            if ($this->audit->record('privacy.request_rejected', $locked, (int) $actor->getKey(), ['status' => $from], ['status' => 'rejected'], ['reason' => $reason]) === null) {
                throw new RuntimeException('Unable to record privacy request audit.');
            }

            return $locked;
        });
    }
}
