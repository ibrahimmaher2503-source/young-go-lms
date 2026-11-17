<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\AI\AssistantEligibility;
use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Domain\Media\Queries\LessonPlayback;
use App\Domain\Progress\LessonProgress;
use App\Domain\Progress\Queries\ContinueLearning;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The course player. A learner without access gets 404, never 403, so the page does not reveal that a lesson exists.
 * An entitled learner whose lesson is not open yet sees why (a drip date) instead.
 */
final class LearnController
{
    public function __construct(
        private readonly ActingLearner $acting,
        private readonly EntitlementService $access,
        private readonly EntitledCurriculumQuery $curriculum,
        private readonly ContinueLearning $continue,
    ) {}

    public function course(Request $request, Course $course): RedirectResponse
    {
        $learner = $this->learner($request);
        $first = collect($this->curriculum->for($learner, $course))->flatMap(fn (array $s): array => $s['items'])->first(fn (array $i): bool => $i['state'] !== 'locked');
        abort_if($first === null || ! $this->access->canAccessCourse($learner, $course)->allowed && $first['state'] !== 'preview', 404);

        // An enrolled learner goes back to where they stopped; anyone else (preview only) to the first open lesson.
        $resume = $this->access->currentFor($learner, $course) !== null ? $this->continue->for($learner, $course) : null;
        $first = $resume !== null ? ['id' => $resume['item_id']] : $first;

        return redirect()->route('learn.show', ['course' => $course->getKey(), 'item' => $first['id']]);
    }

    public function show(Request $request, Course $course, CurriculumItem $item): View
    {
        abort_unless((int) $item->course_id === (int) $course->getKey(), 404);
        $learner = $this->learner($request);
        $decision = $this->access->canAccessItem($learner, $item);

        // Only a learner who is entitled and merely early may see the lock reason; everyone else sees nothing.
        abort_unless($decision->allowed || $decision->reason === 'drip_locked', 404);

        $sections = $this->curriculum->for($learner, $course);
        $entity = $item->itemable;
        $lesson = $entity instanceof Lesson ? $entity : null;
        $entitled = $this->access->currentFor($learner, $course) !== null;
        $lessonProgress = $entitled ? LessonProgress::query()->where('learner_id', $learner->getKey())->where('curriculum_item_id', $item->getKey())->first() : null;
        $courseProgress = $entitled ? $this->continue->progress($learner, $course) : null;
        $completed = $entitled ? LessonProgress::query()->where('learner_id', $learner->getKey())->where('status', LessonProgress::COMPLETED)->pluck('curriculum_item_id')->map(fn ($id): int => (int) $id)->all() : [];

        return view('learn.show', [
            'entitled' => $entitled,
            'lessonProgress' => $lessonProgress,
            'courseProgress' => $courseProgress,
            'completed' => $completed,
            'canMarkComplete' => $entitled && $decision->allowed && $lesson !== null && ($lessonProgress?->status !== LessonProgress::COMPLETED) && $this->manualAllowed($lesson),
            'course' => $course,
            'item' => $item,
            'entity' => $entity,
            'lesson' => $lesson,
            'sections' => $sections,
            'decision' => $decision,
            'neighbours' => EntitledCurriculumQuery::neighbours($sections, (int) $item->getKey()),
            'descriptor' => $decision->allowed && $lesson !== null ? LessonPlayback::for($lesson, $learner) : null,
            'aiAvailable' => $decision->allowed && $lesson !== null && app(AssistantEligibility::class)->check($learner, $lesson)->isAvailable(),
            'learner' => $learner,
        ]);
    }

    private function manualAllowed(Lesson $lesson): bool
    {
        $seconds = $lesson->media->duration_seconds ?? $lesson->duration_seconds;

        return in_array($lesson->type, (array) config('learning.progress.manual_complete_types'), true)
            || (in_array($lesson->type, ['video', 'audio'], true) && ($seconds === null || $seconds === 0) && (bool) config('learning.progress.manual_when_duration_unknown'));
    }

    private function learner(Request $request): User
    {
        $learner = $this->acting->current();
        abort_unless($learner instanceof User, 401);

        return $learner;
    }
}
