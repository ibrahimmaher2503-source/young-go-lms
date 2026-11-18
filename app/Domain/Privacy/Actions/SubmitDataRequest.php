<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestStatus;
use App\Domain\Privacy\DataRequestType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A learner (or the verified guardian of a child) asks for an export, a correction or deletion/anonymization (YG-GAP-PRV-001).
 * One open request per subject and type; nothing is changed until staff approve and complete it.
 */
final class SubmitDataRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $requester, User $subject, DataRequestType $type, ?string $details = null): DataRequest
    {
        if ($requester->getAttribute('status') !== 'active') {
            throw new AuthorizationException;
        }
        if ((int) $requester->getKey() !== (int) $subject->getKey() && ! GuardianAccess::parentCanAccessChild($requester, $subject)->allowed()) {
            throw new AuthorizationException;
        }
        if ($subject->getAttribute('status') !== 'active') {
            throw ValidationException::withMessages(['type' => __('privacy.error_subject_inactive')]);
        }

        $details = $details === null || trim($details) === '' ? null : mb_substr(trim($details), 0, 2000);
        if ($type === DataRequestType::Correction && ($details === null || mb_strlen($details) < 10)) {
            throw ValidationException::withMessages(['details' => __('privacy.error_correction_details')]);
        }
        if ($type === DataRequestType::Deletion) {
            $allowed = Config::array('privacy.self_service_roles');
            if ($subject->getRoleNames()->contains(static fn (string $role): bool => ! in_array($role, $allowed, true))) {
                throw ValidationException::withMessages(['type' => __('privacy.error_staff_account')]);
            }
        }

        return DB::transaction(function () use ($requester, $subject, $type, $details): DataRequest {
            User::query()->lockForUpdate()->findOrFail($subject->getKey());
            $open = DataRequest::query()->where('subject_id', $subject->getKey())->where('type', $type->value)
                ->whereIn('status', [DataRequestStatus::Requested->value, DataRequestStatus::Approved->value])->exists();
            if ($open) {
                throw ValidationException::withMessages(['type' => __('privacy.error_already_open')]);
            }

            $request = DataRequest::query()->create([
                'subject_id' => $subject->getKey(),
                'requested_by' => $requester->getKey(),
                'type' => $type->value,
                'status' => DataRequestStatus::Requested->value,
                'details' => $details,
            ]);

            if ($this->audit->record('privacy.request_submitted', $request, (int) $requester->getKey(), null, ['type' => $type->value, 'status' => 'requested'], ['subject_id' => (int) $subject->getKey()]) === null) {
                throw new RuntimeException('Unable to record privacy request audit.');
            }

            return $request;
        });
    }
}
