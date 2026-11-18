<?php

declare(strict_types=1);

namespace App\Domain\Curriculum\Queries;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/** Ordered curriculum for authors (everything not archived) and for students (published only), from one source. */
final class CurriculumOutline
{
    /** Everything that can sit in a section. */
    public const array ITEM_TYPES = [Lesson::class, Quiz::class, Assignment::class, LiveSession::class];

    /**
     * Items that are not archived themselves. Extend here when quizzes, assignments and live sessions arrive.
     *
     * @param  Builder<CurriculumItem>|Relation<CurriculumItem, CourseSection, mixed>  $query
     * @return Builder<CurriculumItem>|Relation<CurriculumItem, CourseSection, mixed>
     */
    public static function activeItems(Builder|Relation $query): Builder|Relation
    {
        return $query->whereHasMorph('itemable', self::ITEM_TYPES, static fn (Builder $item) => $item->whereNull('archived_at'));
    }

    /** @return Collection<int, CourseSection> */
    public static function forManager(Course $course): Collection
    {
        return CourseSection::query()->where('course_id', $course->getKey())->whereNull('archived_at')->orderBy('sort')->orderBy('id')
            ->with(['items' => static fn ($items) => self::activeItems($items)->with(['itemable' => static fn (MorphTo $morph) => $morph->morphWith([Lesson::class => ['media', 'resources', 'curriculumDocuments']])])->orderBy('sort')->orderBy('id')])->get();
    }

    /** @return Collection<int, CourseSection> */
    public static function forStudents(Course $course): Collection
    {
        return self::forStudentCourses(new Collection([$course]));
    }

    /**
     * Course models picked out of plucked rows (pluck('course') loses the model type), ready for forStudentCourses().
     *
     * @param  iterable<mixed>  $items
     * @return Collection<int, Course>
     */
    public static function coursesFrom(iterable $items): Collection
    {
        $courses = [];
        foreach ($items as $item) {
            if ($item instanceof Course) {
                $courses[] = $item;
            }
        }

        return new Collection($courses);
    }

    /** @param Collection<int, Course> $courses
     * @return Collection<int, CourseSection>
     */
    public static function forStudentCourses(Collection $courses): Collection
    {
        if ($courses->isEmpty()) {
            return new Collection;
        }

        $byId = $courses->keyBy('id');
        $sections = CourseSection::query()->whereIn('course_id', $byId->keys())->whereNull('archived_at')->where('published', true)->orderBy('sort')->orderBy('id')
            ->with(['items' => static fn ($items) => self::activeItems($items)->where('status', CurriculumItem::STATUS_PUBLISHED)
                ->with(['itemable' => static fn (MorphTo $morph) => $morph->morphWith([Lesson::class => ['media'], LiveSession::class => ['cohort']])->morphWithCount([Quiz::class => ['activeQuestions']])])->orderBy('sort')->orderBy('id')])->get();

        foreach ($sections as $section) {
            foreach ($section->items as $item) {
                $item->setRelation('course', $byId->get($section->course_id))->setRelation('section', $section);
            }
        }

        return $sections;
    }

    /**
     * Item ids in the order a learner meets them.
     *
     * @return list<int>
     */
    public static function studentOrder(Course $course): array
    {
        $ids = [];
        foreach (self::forStudents($course) as $section) {
            foreach ($section->items as $item) {
                $ids[] = (int) $item->getKey();
            }
        }

        return $ids;
    }

    /**
     * Archived lessons stay in the database for history and are listed here for authors.
     *
     * @return Collection<int, CurriculumItem>
     */
    public static function archivedItems(Course $course): Collection
    {
        return CurriculumItem::query()->where('course_id', $course->getKey())
            ->whereHasMorph('itemable', self::ITEM_TYPES, static fn (Builder $item) => $item->whereNotNull('archived_at'))
            ->with('itemable')->orderBy('id')->get();
    }
}
