<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Certificates\Certificate;
use App\Domain\Identity\ActingLearner;
use App\Domain\Live\Queries\UpcomingSessions;
use App\Domain\Progress\Queries\LearnerCourseRows;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The learner's dashboard and "my courses". Everything is scoped to the acting learner: the child while a parent acts for them. */
final class LearnerHomeController
{
    public const array FILTERS = ['all', 'in_progress', 'completed', 'expired'];

    public function __construct(private readonly ActingLearner $acting, private readonly LearnerCourseRows $rows) {}

    public function dashboard(Request $request): View
    {
        $learner = $this->learner();
        $courses = $this->rows->for($learner, withResume: false);
        $allInProgress = $courses->where('status', 'in_progress')->values();
        $inProgress = $this->rows->withResume($learner, $allInProgress->take(3));
        LearnerCourseRows::loadCards($inProgress);
        $resume = $inProgress->first(fn (array $row): bool => $row['continue'] !== null);
        if ($resume === null) {
            $resume = $this->rows->withResume($learner, $allInProgress->slice(3))->first(fn (array $row): bool => $row['continue'] !== null);
        }
        $resumeId = $resume['continue']['item_id'] ?? null;
        $resumeItem = $resume === null ? null : $resume['outline']->flatMap(fn ($section) => $section->items)->firstWhere('id', $resumeId);
        $resumeProgress = $resume === null ? null : $resume['lesson_rows']->get($resumeId);

        return view('learner.dashboard', [
            'variant' => $this->variant($request),
            'learner' => $learner,
            'upcoming' => UpcomingSessions::for($learner, 3, $courses->where('access', 'active')->pluck('course')),
            'resume' => $resume,
            'resumeTitle' => (string) ($resumeItem?->itemable?->getAttribute('title') ?? ''),
            'resumeLessonPercent' => (int) ($resumeProgress->percent ?? 0),
            'courses' => $inProgress,
            'total' => $courses->count(),
            'certificates' => Certificate::query()->where('learner_id', $learner->getKey())->whereNull('revoked_at')->latest('issued_at')->limit(5)->get(),
            'notifications' => $learner->notifications()->latest()->limit(5)->get(),
        ]);
    }

    public function courses(Request $request): View
    {
        $filter = in_array($request->query('status'), self::FILTERS, true) ? (string) $request->query('status') : 'all';
        $learner = $this->learner();
        $all = $this->rows->for($learner, withResume: false);
        $courses = LearnerCourseRows::paginate($filter === 'all' ? $all : $all->where('status', $filter));
        $courses->setCollection($this->rows->withResume($learner, $courses->getCollection()));
        LearnerCourseRows::loadCards($courses->getCollection());

        return view('learner.courses', [
            'variant' => $this->variant($request),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'certificates' => Certificate::query()->where('learner_id', $learner->getKey())->whereIn('course_id', $courses->getCollection()->pluck('course.id'))->whereNull('revoked_at')->latest('issued_at')->get()->unique('course_id')->keyBy('course_id'),
            'courses' => $courses,
            'counts' => collect(self::FILTERS)->mapWithKeys(fn (string $f): array => [$f => $f === 'all' ? $all->count() : $all->where('status', $f)->count()])->all(),
        ]);
    }

    private function learner(): User
    {
        $learner = $this->acting->current();
        abort_unless($learner instanceof User, 401);

        return $learner;
    }

    private function variant(Request $request): string
    {
        return $request->user()?->hasRole('parent') ? 'parent' : 'student';
    }
}
