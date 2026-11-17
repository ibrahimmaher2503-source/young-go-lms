<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\AI\AssistantEligibility;
use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Domain\Media\Queries\LessonPlayback;
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
    ) {}

    public function course(Request $request, Course $course): RedirectResponse
    {
        $learner = $this->learner($request);
        $first = collect($this->curriculum->for($learner, $course))->flatMap(fn (array $s): array => $s['items'])->first(fn (array $i): bool => $i['state'] !== 'locked');
        abort_if($first === null || ! $this->access->canAccessCourse($learner, $course)->allowed && $first['state'] !== 'preview', 404);

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

        return view('learn.show', [
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

    private function learner(Request $request): User
    {
        $learner = $this->acting->current();
        abort_unless($learner instanceof User, 401);

        return $learner;
    }
}
