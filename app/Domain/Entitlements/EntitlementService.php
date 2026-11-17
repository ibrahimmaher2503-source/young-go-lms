<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;

/**
 * The one place that decides whether a learner may open protected course content (I01). Everything else asks this
 * service: lesson playback, files, PDFs and the AI assistant. Unknown states fail closed. Answers are cached for the
 * request and the cache is dropped whenever an entitlement is granted, revoked or expired.
 */
final class EntitlementService implements LessonAccessChecker
{
    /** @var array<string, array{current: Entitlement|null, any: Entitlement|null}> */
    private array $cache = [];

    public function canAccessCourse(User $learner, Course $course): AccessDecision
    {
        if (! $this->courseOpen($course)) {
            return AccessDecision::deny('course_unpublished');
        }

        return $this->entitlementDecision($learner, $course)[0];
    }

    public function canAccessItem(User $learner, CurriculumItem $item): AccessDecision
    {
        $course = $item->course;
        if (! $this->courseOpen($course)) {
            return AccessDecision::deny('course_unpublished');
        }

        $entity = $item->itemable;
        $archived = $entity !== null && $entity->getAttribute('archived_at') !== null;
        if ($item->status !== CurriculumItem::STATUS_PUBLISHED || $archived || ! $item->section->published) {
            return AccessDecision::deny('item_unpublished');
        }

        if ($entity instanceof Lesson && $entity->is_preview && $learner->getAttribute('status') === 'active') {
            return AccessDecision::allow();
        }

        [$decision, $entitlement] = $this->entitlementDecision($learner, $course);
        if (! $decision->allowed || $entitlement === null) {
            return $decision;
        }

        return $this->dripDecision($item, $entitlement);
    }

    public function canAccessLessonFiles(User $user, Lesson $lesson): bool
    {
        $item = $lesson->item()->with(['course', 'section'])->first();

        return $item !== null && $this->canAccessItem($user, $item)->allowed;
    }

    /** The learner's current entitlement for a course, or null. */
    public function currentFor(User $learner, Course $course): ?Entitlement
    {
        return $this->lookup($learner, $course)['current'];
    }

    /** Called by the grant, revoke and expire actions so a changed answer is never served from the cache. */
    public function flush(): void
    {
        $this->cache = [];
    }

    private function courseOpen(Course $course): bool
    {
        // Suspended, archived, upcoming and draft courses are closed to learners until the owner defines otherwise.
        return in_array($course->status, [CourseStatus::Published, CourseStatus::Private], true);
    }

    /** @return array{0: AccessDecision, 1: Entitlement|null} */
    private function entitlementDecision(User $learner, Course $course): array
    {
        if ($learner->getAttribute('status') !== 'active') {
            return [AccessDecision::deny('not_entitled'), null];
        }

        $found = $this->lookup($learner, $course);
        if ($found['current'] !== null) {
            return [AccessDecision::allow(), $found['current']];
        }

        return [AccessDecision::deny($found['any'] === null ? 'not_entitled' : 'entitlement_expired'), null];
    }

    private function dripDecision(CurriculumItem $item, Entitlement $entitlement): AccessDecision
    {
        return match ($item->unlock_rule) {
            'none' => AccessDecision::allow(),
            'date' => $item->unlock_at !== null && $item->unlock_at->isFuture() ? AccessDecision::deny('drip_locked', $item->unlock_at) : AccessDecision::allow(),
            'days' => ($at = $entitlement->starts_at->copy()->addDays((int) $item->unlock_days))->isFuture() ? AccessDecision::deny('drip_locked', $at) : AccessDecision::allow(),
            default => AccessDecision::deny('drip_locked'),
        };
    }

    /** @return array{current: Entitlement|null, any: Entitlement|null} */
    private function lookup(User $learner, Course $course): array
    {
        $key = $learner->getKey().'.'.$course->getKey();
        if (! isset($this->cache[$key])) {
            $rows = Entitlement::query()->where('learner_id', $learner->getKey())->where('course_id', $course->getKey())->orderByDesc('starts_at')->get();
            $this->cache[$key] = ['current' => $rows->first(fn (Entitlement $e): bool => $e->isCurrent()), 'any' => $rows->first()];
        }

        return $this->cache[$key];
    }
}
