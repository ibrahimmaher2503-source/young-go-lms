<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Instructors\ApplicationStatus;
use App\Domain\Instructors\Events\InstructorApplicationReviewed;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Admin approves a pending application (needs `instructor.manage`). In one transaction: the applicant gets the
 * `instructor_independent` role (a bounded direct grant, never a generic role editor), a DRAFT public profile is created when
 * none exists (it stays hidden until an admin approves it), and the role change is audited. Approving an approved application
 * is a no-op; a rejected one cannot be approved. The generic review audit entry is written by RecordReviewDecision.
 */
final class ApproveInstructorApplication
{
    public const string ROLE = 'instructor_independent';

    public function __construct(private readonly AuditLogger $audit, private readonly SaveInstructorProfile $saveProfile) {}

    public function __invoke(User $actor, InstructorApplication $application, ?string $note = null): InstructorApplication
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('instructor.manage')) {
            throw new AuthorizationException;
        }

        $reviewed = DB::transaction(function () use ($actor, $application, $note): ?InstructorApplication {
            $locked = InstructorApplication::query()->lockForUpdate()->findOrFail($application->getKey());
            if ($locked->status === ApplicationStatus::Approved) {
                return null;
            }
            if ($locked->status !== ApplicationStatus::Pending) {
                throw ValidationException::withMessages(['application' => __('instructor_onboarding.errors.not_pending')]);
            }

            $user = User::query()->lockForUpdate()->find($locked->user_id);
            if ($user === null || $user->getAttribute('status') !== 'active' || $user->getAttribute('email') === null) {
                throw ValidationException::withMessages(['application' => __('instructor_onboarding.errors.applicant_unavailable')]);
            }

            $before = $user->getRoleNames()->sort()->values()->all();
            if (! $user->hasAnyRole(PublicInstructors::ROLES)) {
                $user->assignRole(self::ROLE);
                $after = $user->getRoleNames()->sort()->values()->all();
                if ($this->audit->record('user.roles_changed', $user, (int) $actor->getKey(), ['roles' => $before], ['roles' => $after], ['application_id' => $locked->getKey()]) === null) {
                    throw new RuntimeException('Unable to record instructor role audit.');
                }
            }

            if (! InstructorProfile::query()->where('user_id', $user->getKey())->exists()) {
                $this->createDraftProfile($actor, $user, $locked);
            }

            $locked->forceFill([
                'status' => ApplicationStatus::Approved->value, 'pending_user_id' => null,
                'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $note,
            ])->save();

            return $locked;
        });

        if ($reviewed === null) {
            return InstructorApplication::query()->findOrFail($application->getKey());
        }

        DB::afterCommit(static fn () => event(new InstructorApplicationReviewed($reviewed)));

        return $reviewed;
    }

    private function createDraftProfile(User $actor, User $user, InstructorApplication $application): void
    {
        $slug = (Str::slug($application->full_name) ?: 'instructor').'-'.$user->getKey();
        $bioLocale = $application->locale === 'ar' ? 'ar' : 'en';

        ($this->saveProfile)($actor, null, [
            'user_id' => $user->getKey(), 'slug_ar' => $slug, 'slug_en' => $slug, 'status' => InstructorProfile::DRAFT,
            'display_name' => ['ar' => $application->full_name, 'en' => $application->full_name],
            'bio' => [$bioLocale => $application->bio], 'disciplines' => [],
        ]);
    }
}
