<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Entitlements\Contracts\CompletionLookup;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\Quiz;
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

    /** @var array<string, list<int>> */
    private array $completed = [];

    public function __construct(private readonly CompletionLookup $completion) {}

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
        if (! $this->itemPublished($item)) {
            return AccessDecision::deny('item_unpublished');
        }

        if ($entity instanceof Lesson && $entity->is_preview && $learner->getAttribute('status') === 'active') {
            return AccessDecision::allow();
        }

        [$decision, $entitlement] = $this->entitlementDecision($learner, $course);
        if (! $decision->allowed || $entitlement === null) {
            return $decision;
        }

        $drip = $this->dripDecision($item, $entitlement);

        return $drip->allowed ? $this->sequenceDecision($learner, $item) : $drip;
    }

    public function canAccessLessonFiles(User $user, Lesson $lesson): bool
    {
        $item = $lesson->item()->with(['course', 'section'])->first();

        // A lesson preview opens playback; private attachments still require a course entitlement.
        return $item !== null
            && $this->canAccessCourse($user, $item->course)->allowed
            && $this->canAccessItem($user, $item)->allowed;
    }

    public function canAccessPublicResource(User $user, Lesson $lesson): bool
    {
        $item = $lesson->item()->with(['course', 'section'])->first();

        return $user->getAttribute('status') === 'active'
            && $item !== null
            && $item->course->status === CourseStatus::Published
            && $this->itemPublished($item);
    }

    /**
     * What a brand-new learner with a current entitlement would meet on this item: drip and sequence only, because staff
     * preview content that is still a draft or not yet open. Used by "view as learner"; it reads and writes nothing about real learners.
     */
    public function previewDecision(CurriculumItem $item): AccessDecision
    {
        $synthetic = new Entitlement(['starts_at' => now(), 'status' => Entitlement::ACTIVE]);
        $drip = $this->dripDecision($item, $synthetic);

        return $drip->allowed && $item->status === CurriculumItem::STATUS_PUBLISHED ? $this->sequenceAgainst($item, []) : $drip;
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
        $this->completed = [];
    }

    private function courseOpen(Course $course): bool
    {
        // Suspended, archived, upcoming and draft courses are closed to learners until the owner defines otherwise.
        return in_array($course->status, [CourseStatus::Published, CourseStatus::Private], true);
    }

    private function itemPublished(CurriculumItem $item): bool
    {
        return $item->status === CurriculumItem::STATUS_PUBLISHED
            && $item->itemable?->getAttribute('archived_at') === null
            && $item->section->published;
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

    /**
     * Sequential courses (ED-025): an item opens only once every required lesson before it in the curriculum is completed.
     * The first unfinished one is named so the learner knows what to do next.
     */
    private function sequenceDecision(User $learner, CurriculumItem $item): AccessDecision
    {
        $course = $item->course;
        if ($course->sequence_mode !== Course::SEQUENCE_SEQUENTIAL) {
            return AccessDecision::allow();
        }

        $key = $learner->getKey().'.'.$course->getKey();

        return $this->sequenceAgainst($item, $this->completed[$key] ??= $this->completion->completedItemIds($learner, $course));
    }

    /** @param  list<int>  $done */
    private function sequenceAgainst(CurriculumItem $item, array $done): AccessDecision
    {
        $course = $item->course;
        if ($course->sequence_mode !== Course::SEQUENCE_SEQUENTIAL) {
            return AccessDecision::allow();
        }

        $ordered = CurriculumOutline::forStudents($course)->flatMap(static fn ($section) => $section->items)->values();
        $position = $ordered->search(static fn (CurriculumItem $candidate): bool => $candidate->getKey() === $item->getKey());
        if ($position === false) {
            return AccessDecision::deny('item_unpublished');
        }

        foreach ($ordered->take($position) as $previous) {
            // Lessons always gate what follows; a quiz gates it only when it requires a pass (T-P6-02 progression).
            $gates = $previous->itemable instanceof Lesson || ($previous->itemable instanceof Quiz && $previous->itemable->progression === 'require_pass');
            if ($previous->required && $gates && ! in_array((int) $previous->getKey(), $done, true)) {
                return AccessDecision::deny('sequence_locked', null, (string) $previous->itemable->title);
            }
        }

        return AccessDecision::allow();
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
