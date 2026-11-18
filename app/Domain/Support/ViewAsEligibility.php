<?php

declare(strict_types=1);

namespace App\Domain\Support;

use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Identity\Models\ViewAsSession;
use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestStatus;
use App\Domain\Privacy\DataRequestType;
use App\Models\AiSafeguardingFlag;
use App\Models\User;

/**
 * The one place that decides whether staff may view as a learner (ED-027). Fails closed: the first reason found wins.
 * Never allowed on staff accounts or on anyone who is not a plain learner, on anonymized or inactive accounts, while a
 * deletion request or an open safeguarding flag exists, or for a child unless the person may see sensitive learner data.
 */
final class ViewAsEligibility
{
    /** Roles a viewable account may hold. Anything else (staff, parent) is not a learner. */
    private const array LEARNER_ROLES = ['student'];

    private const array STAFF_PERMISSIONS = ['panel.admin.access', 'panel.instructor.access', 'panel.finance.access'];

    public function __construct(private readonly ActingLearner $acting) {}

    public function refusal(User $actor, User $learner): ?ViewAsRefusal
    {
        if (! $actor->can('support.view_as') || ! $actor->can('learner.360.view')) {
            return ViewAsRefusal::Permission;
        }

        if ($actor->getKey() === $learner->getKey()) {
            return ViewAsRefusal::OwnAccount;
        }

        if ($this->isStaff($learner)) {
            return ViewAsRefusal::StaffAccount;
        }

        if ($learner->roles()->whereNotIn('name', self::LEARNER_ROLES)->exists()) {
            return ViewAsRefusal::NotLearner;
        }

        if ($learner->getAttribute('status') !== 'active') {
            return ViewAsRefusal::Inactive;
        }

        if ($learner->getAttribute('anonymized_at') !== null) {
            return ViewAsRefusal::Anonymized;
        }

        if (DataRequest::query()->where('subject_id', $learner->getKey())->where('type', DataRequestType::Deletion->value)
            ->whereIn('status', [DataRequestStatus::Requested->value, DataRequestStatus::Approved->value])->exists()) {
            return ViewAsRefusal::PrivacyRequest;
        }

        if (AiSafeguardingFlag::query()->where('student_id', $learner->getKey())->where('status', 'open')->exists()) {
            return ViewAsRefusal::Safeguarding;
        }

        if (GuardianAccess::isMinor($learner) && ! $actor->can('learner.360.sensitive')) {
            return ViewAsRefusal::MinorRestricted;
        }

        if ($this->acting->isParentContext()) {
            return ViewAsRefusal::ParentContext;
        }

        if (ViewAsSession::query()->open()->where('staff_id', $actor->getKey())->where('expires_at', '>', now())->exists()) {
            return ViewAsRefusal::AlreadyActive;
        }

        return null;
    }

    public function isStaff(User $user): bool
    {
        return $user->hasAnyPermission(self::STAFF_PERMISSIONS)
            || $user->roles()->whereNotIn('name', ['student', 'parent'])->exists();
    }
}
