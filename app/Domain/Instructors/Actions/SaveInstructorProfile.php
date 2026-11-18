<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates the public profile of an instructor (input format validated by the Filament form). Needs `instructor.manage`.
 * The account must hold an instructor role. Approving stamps who and when; every save is audited (no bio text in the log).
 */
final class SaveInstructorProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data  profile fields plus `disciplines` (list of category ids) */
    public function __invoke(User $actor, ?InstructorProfile $profile, array $data): InstructorProfile
    {
        Gate::forUser($actor)->authorize('instructor.manage');

        $disciplines = array_values(array_unique(array_map(intval(...), (array) ($data['disciplines'] ?? []))));
        unset($data['disciplines'], $data['approved_at'], $data['approved_by']);

        $userId = (int) ($profile !== null ? $profile->user_id : ($data['user_id'] ?? 0));
        $owner = User::query()->role(PublicInstructors::ROLES)->find($userId);
        if ($owner === null) {
            throw ValidationException::withMessages(['user_id' => __('instructors.not_instructor')]);
        }
        $data['user_id'] = $owner->getKey();

        return DB::transaction(function () use ($actor, $profile, $data, $disciplines): InstructorProfile {
            $profile ??= new InstructorProfile;
            $before = $profile->exists ? $profile->only(['slug_ar', 'slug_en', 'status']) : null;
            $wasApproved = $profile->status ?? null;
            $profile->fill($data);

            if ($profile->status === InstructorProfile::APPROVED && $wasApproved !== InstructorProfile::APPROVED) {
                $profile->approved_at = now();
                $profile->approved_by = $actor->getKey();
            }
            $profile->save();
            $profile->disciplines()->sync($disciplines);

            $event = match (true) {
                $profile->status === InstructorProfile::APPROVED && $wasApproved !== InstructorProfile::APPROVED => 'instructor_profile.approved',
                $wasApproved === InstructorProfile::APPROVED && $profile->status !== InstructorProfile::APPROVED => 'instructor_profile.hidden',
                default => 'instructor_profile.saved',
            };
            $this->audit->record($event, $profile, (int) $actor->getKey(), $before, $profile->only(['slug_ar', 'slug_en', 'status']));

            return $profile;
        });
    }
}
