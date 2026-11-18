<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Entitlements\Contracts\CompletionLookup;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Quiz;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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

    /** @var array<string, bool> */
    private array $seats = [];

    public function __construct(private readonly CompletionLookup $completion) {}

    public function canAccessCourse(User $learner, Course $course): AccessDecision
    {
        if (! $this->courseOpen($course)) {
            return AccessDecision::deny('course_unpublished');
        }

        return $this->entitlementDecision($learner, $course)[0];
    }

    /** @param iterable<CurriculumItem>|null $orderedItems */
    public function canAccessItem(User $learner, CurriculumItem $item, ?iterable $orderedItems = null): AccessDecision
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

        $cohort = $entity instanceof LiveSession && $entity->cohort_id !== null ? $entity->cohort : null;
        if (! $this->canSeeCohortItem($learner, $item)) {
            return AccessDecision::deny('cohort_locked');
        }

        $sequence = $this->sequenceDecision($learner, $item, $orderedItems);

        return $sequence->allowed ? $this->dripDecision($item, $entitlement, $cohort?->starts_on) : $sequence;
    }

    public function canSeeCohortItem(User $learner, CurriculumItem $item): bool
    {
        $session = $item->itemable;

        return ! $session instanceof LiveSession || $session->cohort_id === null
            || ($session->cohort?->course_id === $item->course_id && $this->hasSeat($learner, $session->cohort));
    }

    /**
     * Public lesson preview for anyone, signed in or not: only a published course, a published item in a published
     * section, and a lesson the author flagged as preview. Never covers protected attachments or any other item type.
     */
    public function canPreviewPublicly(CurriculumItem $item): AccessDecision
    {
        if ($item->course->status !== CourseStatus::Published) {
            return AccessDecision::deny('course_unpublished');
        }

        if (! $this->itemPublished($item)) {
            return AccessDecision::deny('item_unpublished');
        }

        return $item->itemable instanceof Lesson && $item->itemable->is_preview
            ? AccessDecision::allow()
            : AccessDecision::deny('not_entitled');
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
        $sequence = $item->status === CurriculumItem::STATUS_PUBLISHED ? $this->sequenceAgainst($item, []) : AccessDecision::allow();
        $entity = $item->itemable;

        return $sequence->allowed ? $this->dripDecision($item, $synthetic, $entity instanceof LiveSession ? $entity->cohort?->starts_on : null) : $sequence;
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
        $this->seats = [];
    }

    /** @param  Collection<int, CurriculumItem>  $items */
    public function preloadItemAccess(User $learner, Collection $items): void
    {
        // Every course is preloaded, not only sequential ones: require_pass quizzes gate items in any course, so
        // sequenceDecision reads completions for all of them and would otherwise query once per course.
        $courseIds = $items->pluck('course_id')->unique()->values()->all();
        if ($courseIds !== []) {
            $completed = $this->completion->completedItemIdsByCourse($learner, $courseIds);
            foreach ($courseIds as $courseId) {
                $this->completed[$learner->id.'.'.$courseId] = $completed[$courseId] ?? [];
            }
        }

        $cohortIds = $items->map(fn (CurriculumItem $item) => $item->itemable)
            ->filter(fn ($entity): bool => $entity instanceof LiveSession && $entity->cohort_id !== null)->pluck('cohort_id')->unique()->values()->all();
        if ($cohortIds !== []) {
            $seated = CohortMember::query()->where('learner_id', $learner->id)->where('status', CohortMember::SEATED)
                ->whereIn('cohort_id', $cohortIds)->pluck('cohort_id')->flip();
            foreach ($cohortIds as $cohortId) {
                $this->seats[$learner->id.'.'.$cohortId] = $seated->has($cohortId);
            }
        }
    }

    private function hasSeat(User $learner, Cohort $cohort): bool
    {
        return $this->seats[$learner->id.'.'.$cohort->id] ?? $cohort->hasSeat($learner);
    }

    private function courseOpen(Course $course): bool
    {
        // Suspended, archived, upcoming and draft courses are closed to learners until the owner defines otherwise.
        return in_array($course->status, [CourseStatus::Published, CourseStatus::Private], true);
    }

    private function itemPublished(CurriculumItem $item): bool
    {
        return $item->status === CurriculumItem::STATUS_PUBLISHED
            && $item->itemable !== null
            && $item->itemable->getAttribute('archived_at') === null
            && $item->section->published
            && $item->section->archived_at === null;
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

    private function dripDecision(CurriculumItem $item, Entitlement $entitlement, ?CarbonInterface $cohortStart = null): AccessDecision
    {
        return match ($item->unlock_rule) {
            'none' => AccessDecision::allow(),
            'date' => $item->unlock_at !== null && $item->unlock_at->isFuture() ? AccessDecision::deny('drip_locked', $item->unlock_at) : AccessDecision::allow(),
            'days' => ($at = ($cohortStart ?? $entitlement->starts_at)->copy()->addDays((int) $item->unlock_days))->isFuture() ? AccessDecision::deny('drip_locked', $at) : AccessDecision::allow(),
            default => AccessDecision::deny('drip_locked'),
        };
    }

    /**
     * Sequential courses (ED-025): an item opens only once every required lesson before it in the curriculum is completed.
     * The first unfinished one is named so the learner knows what to do next.
     */
    /** @param iterable<CurriculumItem>|null $orderedItems */
    private function sequenceDecision(User $learner, CurriculumItem $item, ?iterable $orderedItems): AccessDecision
    {
        $course = $item->course;
        $key = $learner->getKey().'.'.$course->getKey();

        return $this->sequenceAgainst($item, $this->completed[$key] ??= $this->completion->completedItemIds($learner, $course), $orderedItems);
    }

    /**
     * @param  list<int>  $done
     * @param  iterable<CurriculumItem>|null  $orderedItems
     */
    private function sequenceAgainst(CurriculumItem $item, array $done, ?iterable $orderedItems = null): AccessDecision
    {
        $course = $item->course;
        $ordered = $orderedItems ?? CurriculumOutline::forStudents($course)->flatMap(static fn ($section) => $section->items);

        foreach ($ordered as $previous) {
            if ($previous->getKey() === $item->getKey()) {
                return AccessDecision::allow();
            }
            $gates = ($previous->itemable instanceof Lesson && $course->sequence_mode === Course::SEQUENCE_SEQUENTIAL)
                || ($previous->itemable instanceof Quiz && $previous->itemable->progression === 'require_pass');
            if ($previous->required && $gates && ! in_array((int) $previous->getKey(), $done, true)) {
                return AccessDecision::deny('sequence_locked', null, (string) $previous->itemable->title);
            }
        }

        return AccessDecision::deny('item_unpublished');
    }

    /** @return array{current: Entitlement|null, any: Entitlement|null} */
    private function lookup(User $learner, Course $course): array
    {
        $key = $learner->getKey().'.'.$course->getKey();
        if (! isset($this->cache[$key])) {
            $rows = Entitlement::query()->where('learner_id', $learner->getKey())->where('course_id', $course->getKey())->orderByDesc('starts_at')->get();
            $this->cache[$key] = $this->summarize($rows);
        }

        return $this->cache[$key];
    }

    /** @param Collection<int, Entitlement> $rows */
    public function primeLearnerCourses(User $learner, Collection $rows): void
    {
        foreach ($rows->groupBy('course_id') as $courseId => $entitlements) {
            $this->cache[$learner->getKey().'.'.$courseId] = $this->summarize($entitlements);
        }
    }

    /**
     * @param  Collection<int, Entitlement>  $rows
     * @return array{current: Entitlement|null, any: Entitlement|null}
     */
    private function summarize(Collection $rows): array
    {
        return ['current' => $rows->first(fn (Entitlement $e): bool => $e->isCurrent()), 'any' => $rows->first()];
    }
}
