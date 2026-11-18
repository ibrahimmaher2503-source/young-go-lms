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

/** Staff (privacy.manage) accept a request. Nothing is exported or changed yet; CompleteDataRequest does the work. Approving twice is a no-op. */
final class ApproveDataRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, DataRequest $request, ?string $note = null): DataRequest
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('privacy.manage')) {
            throw new AuthorizationException;
        }
        $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 1000);

        return DB::transaction(function () use ($actor, $request, $note): DataRequest {
            $locked = DataRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($locked->status === DataRequestStatus::Approved) {
                return $locked;
            }
            if ($locked->status !== DataRequestStatus::Requested) {
                throw ValidationException::withMessages(['request' => __('privacy.error_not_requested')]);
            }

            $locked->forceFill(['status' => DataRequestStatus::Approved->value, 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $note])->save();

            if ($this->audit->record('privacy.request_approved', $locked, (int) $actor->getKey(), ['status' => 'requested'], ['status' => 'approved'], ['note' => $note]) === null) {
                throw new RuntimeException('Unable to record privacy request audit.');
            }

            return $locked;
        });
    }
}
