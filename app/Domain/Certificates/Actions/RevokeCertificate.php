<?php

declare(strict_types=1);

namespace App\Domain\Certificates\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Certificates\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Staff void a live certificate with a reason. The row stays (its link answers "revoked"); the completion is untouched.
 * Same gate as editing the course. The reason stays staff-side: it is audited and never shown on the public page.
 */
final class RevokeCertificate
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Certificate $certificate, string $reason): Certificate
    {
        $certificate->loadMissing('course');
        Gate::forUser($actor)->authorize('update', $certificate->course);
        $reason = self::cleanReason($reason);

        return DB::transaction(fn (): Certificate => $this->revoke($actor, $certificate, $reason));
    }

    /** Validates and normalises the staff reason (3 to 500 characters). */
    public static function cleanReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('certificates.reason_required')]);
        }

        return $reason;
    }

    /** Voids the row inside the caller's transaction. */
    public function revoke(User $actor, Certificate $certificate, string $reason): Certificate
    {
        $fresh = Certificate::query()->lockForUpdate()->find($certificate->getKey());
        if (! $fresh instanceof Certificate || ! $fresh->isValid()) {
            throw ValidationException::withMessages(['certificate' => __('certificates.not_active')]);
        }

        $fresh->forceFill(['revoked_at' => now(), 'revoked_reason' => $reason, 'revoked_by' => $actor->getKey(), 'active' => null])->save();
        $after = ['number' => $fresh->number, 'status' => 'revoked', 'reason' => $reason];
        if ($this->audit->record('certificate.revoked', $fresh, (int) $actor->getKey(), ['status' => 'valid'], $after) === null) {
            throw new RuntimeException('Unable to record certificate audit.');
        }

        return $fresh;
    }
}
