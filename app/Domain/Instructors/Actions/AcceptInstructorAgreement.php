<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Instructors\Events\InstructorAgreementAccepted;
use App\Domain\Instructors\Models\InstructorAgreement;
use App\Domain\Instructors\Models\InstructorAgreementAcceptance;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * An instructor accepts one PUBLISHED agreement version for themselves: version snapshot, time and IP are stored (YG-GAP-INS-002)
 * and audited. Accepting twice is a no-op. The caller passes the request IP (the domain never sees the request).
 */
final class AcceptInstructorAgreement
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $instructor, InstructorAgreement $agreement, ?string $ip): InstructorAgreementAcceptance
    {
        if ($instructor->getAttribute('status') !== 'active' || ! $instructor->hasAnyRole(PublicInstructors::ROLES)) {
            throw new AuthorizationException;
        }

        $ip = $ip !== null && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;

        try {
            $created = DB::transaction(function () use ($instructor, $agreement, $ip): ?InstructorAgreementAcceptance {
                $fresh = InstructorAgreement::query()->findOrFail($agreement->getKey());
                if (! $fresh->isPublished()) {
                    throw ValidationException::withMessages(['agreement' => __('instructor_onboarding.agreement.not_published')]);
                }
                if (InstructorAgreementAcceptance::query()->where('user_id', $instructor->getKey())->where('agreement_id', $fresh->getKey())->exists()) {
                    return null;
                }

                $acceptance = InstructorAgreementAcceptance::query()->create([
                    'user_id' => $instructor->getKey(), 'agreement_id' => $fresh->getKey(), 'version' => $fresh->version,
                    'accepted_at' => now(), 'ip' => $ip,
                ]);
                if ($this->audit->record('instructor_agreement.accepted', $acceptance, (int) $instructor->getKey(), null, ['version' => $fresh->version], ['ip' => $ip]) === null) {
                    throw new RuntimeException('Unable to record agreement audit.');
                }
                DB::afterCommit(static fn () => event(new InstructorAgreementAccepted($acceptance)));

                return $acceptance;
            });
        } catch (UniqueConstraintViolationException) {
            $created = null; // a concurrent accept won the race
        }

        return $created ?? InstructorAgreementAcceptance::query()->where('user_id', $instructor->getKey())->where('agreement_id', $agreement->getKey())->firstOrFail();
    }
}
