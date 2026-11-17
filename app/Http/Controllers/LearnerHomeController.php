<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Certificates\Certificate;
use App\Domain\Identity\ActingLearner;
use App\Domain\Live\Queries\UpcomingSessions;
use App\Domain\Progress\LessonProgress;
use App\Domain\Progress\Queries\LearnerCourseRows;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** The learner's dashboard and "my courses". Everything is scoped to the acting learner: the child while a parent acts for them. */
final class LearnerHomeController
{
    public const array FILTERS = ['all', 'in_progress', 'completed', 'expired'];

    public function __construct(private readonly ActingLearner $acting, private readonly LearnerCourseRows $rows) {}

    public function dashboard(Request $request): View
    {
        $learner = $this->learner();
        $courses = $this->rows($learner);
        $resume = $courses->first(fn (array $c): bool => $c['status'] === 'in_progress' && $c['continue'] !== null);
        $resumeItem = $resume ? CurriculumItem::query()->with('itemable')->find($resume['continue']['item_id']) : null;
        $resumeProgress = $resumeItem ? LessonProgress::query()->where('learner_id', $learner->getKey())->where('curriculum_item_id', $resumeItem->getKey())->first() : null;

        return view('learner.dashboard', [
            'variant' => $this->variant($request),
            'learner' => $learner,
            'upcoming' => UpcomingSessions::for($learner, 3),
            'resume' => $resume,
            'resumeTitle' => (string) ($resumeItem?->itemable?->getAttribute('title') ?? ''),
            'resumeLessonPercent' => (int) ($resumeProgress->percent ?? 0),
            'courses' => $courses->where('status', 'in_progress')->take(3)->values(),
            'total' => $courses->count(),
            'certificates' => Certificate::query()->where('learner_id', $learner->getKey())->whereNull('revoked_at')->latest('issued_at')->limit(5)->get(),
            'notifications' => $learner->notifications()->latest()->limit(5)->get(),
        ]);
    }

    public function courses(Request $request): View
    {
        $filter = in_array($request->query('status'), self::FILTERS, true) ? (string) $request->query('status') : 'all';
        $all = $this->rows($this->learner());

        return view('learner.courses', [
            'variant' => $this->variant($request),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'certificates' => Certificate::query()->where('learner_id', $this->learner()->getKey())->whereNull('revoked_at')->latest('issued_at')->get()->unique('course_id')->keyBy('course_id'),
            'courses' => $filter === 'all' ? $all : $all->where('status', $filter)->values(),
            'counts' => collect(self::FILTERS)->mapWithKeys(fn (string $f): array => [$f => $f === 'all' ? $all->count() : $all->where('status', $f)->count()])->all(),
        ]);
    }

    /** @return Collection<int, array{course: Course, access: string, ends_at: Carbon|null, source: string, status: string, percent: int, done: int, total: int, continue: array{item_id: int, position_seconds: int}|null}> */
    private function rows(User $subject): Collection
    {
        return $this->rows->for($subject);
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
