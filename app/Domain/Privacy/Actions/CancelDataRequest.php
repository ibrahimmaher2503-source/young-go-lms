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

/** The person who made a request can withdraw it while it is still waiting for staff. */
final class CancelDataRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, DataRequest $request): DataRequest
    {
        if ($actor->getAttribute('status') !== 'active' || (int) $request->requested_by !== (int) $actor->getKey()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $request): DataRequest {
            $locked = DataRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($locked->status !== DataRequestStatus::Requested) {
                throw ValidationException::withMessages(['request' => __('privacy.error_not_requested')]);
            }
            $locked->forceFill(['status' => DataRequestStatus::Cancelled->value])->save();

            if ($this->audit->record('privacy.request_cancelled', $locked, (int) $actor->getKey(), ['status' => 'requested'], ['status' => 'cancelled']) === null) {
                throw new RuntimeException('Unable to record privacy request audit.');
            }

            return $locked;
        });
    }
}
