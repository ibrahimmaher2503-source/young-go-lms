<?php

declare(strict_types=1);

namespace App\Domain\Progress\Queries;

use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\LearnerCourses;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as Models;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @phpstan-type CourseRow array{course: Course, access: string, ends_at: Carbon|null, source: string, status: string, percent: int, done: int, total: int, continue: array{item_id: int, position_seconds: int}|null, outline: Collection<int, CourseSection>, lesson_rows: Collection<int, LessonProgress>}
 */
final class LearnerCourseRows
{
    public function __construct(private readonly EntitledCurriculumQuery $curriculum, private readonly ContinueLearning $continue, private readonly EntitlementService $access) {}

    /** @return Collection<int, CourseRow> */
    public function for(User $learner, bool $withResume = true): Collection
    {
        $rows = LearnerCourses::for($learner);
        $counts = CourseProgressSnapshot::counts($learner, $rows->where('access', 'active')->pluck('course.id')->all());
        $rows = $rows->map(static fn (array $row): array => self::baseRow($row, $counts));

        return $withResume ? $this->withResume($learner, $rows) : $rows;
    }

    /**
     * A course row with its progress counts and no resume data yet.
     *
     * @param  array{course: Course, access: string, ends_at: Carbon|null, source: string}  $row
     * @param  Collection<int, array{done: int, total: int}>  $counts
     * @return CourseRow
     */
    private static function baseRow(array $row, Collection $counts): array
    {
        $active = $row['access'] === 'active';
        ['done' => $done, 'total' => $total] = $counts->get($row['course']->id, ['done' => 0, 'total' => 0]);

        return $row + [
            'status' => ! $active ? 'expired' : ($total > 0 && $done === $total ? 'completed' : 'in_progress'),
            'percent' => $total === 0 ? 0 : intdiv($done * 100, $total),
            'done' => $done,
            'total' => $total,
            'continue' => null,
            'outline' => self::noSections(),
            'lesson_rows' => self::noLessonRows(),
        ];
    }

    /**
     * @param  Collection<int, CourseRow>  $rows
     * @return Collection<int, CourseRow>
     */
    public function withResume(User $learner, Collection $rows): Collection
    {
        $active = CurriculumOutline::coursesFrom($rows->where('access', 'active')->pluck('course'));
        $outline = CurriculumOutline::forStudentCourses($active);
        $this->access->preloadItemAccess($learner, $outline->flatMap(fn (CourseSection $section) => $section->items)->values());
        $sections = $outline->groupBy('course_id');
        $lessonRows = CourseProgressSnapshot::lessonRows($learner, $active->modelKeys());

        return $rows->map(function (array $row) use ($learner, $sections, $lessonRows): array {
            $course = $row['course'];
            $row['outline'] = $sections->get($course->id, self::noSections());
            $row['lesson_rows'] = $lessonRows->get($course->id, self::noLessonRows());
            if ($row['outline']->isNotEmpty()) {
                $curriculum = $this->curriculum->for($learner, $course, $row['outline']);
                $row['continue'] = $this->continue->for($learner, $course, $curriculum, $row['lesson_rows']);
            }

            return $row;
        });
    }

    /** @return Collection<int, CourseSection> */
    private static function noSections(): Collection
    {
        return new Collection;
    }

    /** @return Collection<int, LessonProgress> */
    private static function noLessonRows(): Collection
    {
        return new Collection;
    }

    /** @param Collection<int, CourseRow> $rows */
    public static function loadCards(Collection $rows): void
    {
        (new Models($rows->pluck('course')->all()))->load(['category', 'instructors']);
    }

    /**
     * @param  Collection<int, CourseRow>  $rows
     * @return LengthAwarePaginator<int, CourseRow>
     */
    public static function paginate(Collection $rows): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 12;

        return (new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]))->withQueryString();
    }
}
