<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Entitlements\LearnerCourses;
use App\Domain\Identity\ActingLearner;
use App\Domain\Progress\LessonProgress;
use App\Domain\Progress\Queries\ContinueLearning;
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

    public function __construct(private readonly ActingLearner $acting, private readonly ContinueLearning $continue) {}

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
            'resume' => $resume,
            'resumeTitle' => (string) ($resumeItem?->itemable?->getAttribute('title') ?? ''),
            'resumeLessonPercent' => (int) ($resumeProgress->percent ?? 0),
            'courses' => $courses->where('status', 'in_progress')->take(3)->values(),
            'total' => $courses->count(),
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
            'courses' => $filter === 'all' ? $all : $all->where('status', $filter)->values(),
            'counts' => collect(self::FILTERS)->mapWithKeys(fn (string $f): array => [$f => $f === 'all' ? $all->count() : $all->where('status', $f)->count()])->all(),
        ]);
    }

    /** @return Collection<int, array{course: Course, access: string, ends_at: Carbon|null, source: string, status: string, percent: int, done: int, total: int, continue: array{item_id: int, position_seconds: int}|null}> */
    private function rows(User $subject): Collection
    {
        // @phpstan-ignore return.type (the merged row shape is correct; Collection templates are invariant)
        return LearnerCourses::for($subject)->each(fn (array $row) => $row['course']->loadMissing(['instructors', 'category']))->map(function (array $row) use ($subject): array {
            $active = $row['access'] === 'active';
            $progress = $active ? $this->continue->progress($subject, $row['course']) : null;
            $status = match (true) {
                ! $active => 'expired',
                $progress?->completed_at !== null => 'completed',
                default => 'in_progress',
            };

            return $row + [
                'status' => $status,
                'percent' => $progress->percent ?? 0,
                'done' => $progress->completed_required ?? 0,
                'total' => $progress->total_required ?? 0,
                'continue' => $active ? $this->continue->for($subject, $row['course']) : null,
            ];
        });
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
