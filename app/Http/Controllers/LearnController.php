<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\AI\AssistantEligibility;
use App\Domain\Assessment\Queries\QuizOverview;
use App\Domain\Assignments\Queries\SubmissionView;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Domain\Live\JoinWindow;
use App\Domain\Media\Queries\LessonPlayback;
use App\Domain\Media\SignedFileUrl;
use App\Domain\Progress\LessonProgress;
use App\Domain\Progress\Queries\ContinueLearning;
use App\Domain\Progress\Queries\CourseProgressSnapshot;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\LiveSession;
use App\Models\Quiz;
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
        $sections = $this->curriculum->for($learner, $course);
        $first = collect($sections)->flatMap(fn (array $s): array => $s['items'])->first(fn (array $i): bool => $i['state'] !== 'locked');
        abort_if($first === null || ! $this->access->canAccessCourse($learner, $course)->allowed && $first['state'] !== 'preview', 404);

        // An enrolled learner goes back to where they stopped; anyone else (preview only) to the first open lesson.
        $resume = $this->access->currentFor($learner, $course) !== null ? $this->continue->for($learner, $course, $sections) : null;
        $first = $resume !== null ? ['id' => $resume['item_id']] : $first;

        return redirect()->route('learn.show', ['course' => $course->getKey(), 'item' => $first['id']]);
    }

    public function show(Request $request, Course $course, CurriculumItem $item): View
    {
        abort_unless((int) $item->course_id === (int) $course->getKey(), 404);
        $learner = $this->learner($request);
        $outline = CurriculumOutline::forStudents($course);
        $ordered = $outline->flatMap(fn (CourseSection $section) => $section->items)->values();
        $item = $ordered->firstWhere('id', $item->id) ?? $item->setRelation('course', $course);
        $decision = $this->access->canAccessItem($learner, $item, $ordered);

        // Only a learner who is entitled and merely early may see the lock reason; everyone else sees nothing.
        abort_unless($decision->allowed || in_array($decision->reason, ['drip_locked', 'sequence_locked'], true), 404);

        $sections = $this->curriculum->for($learner, $course, $outline);
        $entity = $item->itemable;
        $lesson = $entity instanceof Lesson ? $entity : null;
        $entitled = $this->access->currentFor($learner, $course) !== null;
        $lessonRows = $entitled ? CourseProgressSnapshot::lessonRows($learner, [$course->id])->get($course->id, collect()) : collect();
        $lessonProgress = $lessonRows->get($item->id);
        $courseProgress = $entitled ? CourseProgressSnapshot::for($learner, $course, $lessonRows) : null;
        $completed = $lessonRows->where('status', LessonProgress::COMPLETED)->keys()->map(fn ($id): int => (int) $id)->all();
        $openLesson = $decision->allowed ? $lesson : null;
        $urls = app(SignedFileUrl::class);
        $document = $entitled ? $openLesson?->activeCurriculumDocument() : null;
        $canPreview = ! $entitled && $openLesson !== null && $this->access->canAccessPublicResource($learner, $openLesson);
        $resources = $openLesson?->resources
            ->map(function (LessonResource $resource) use ($entitled, $learner, $urls, $canPreview): array {
                $url = $resource->type === LessonResource::TYPE_LINK
                    ? ($entitled || ($canPreview && $resource->isPublicPreview()) ? $resource->url : null)
                    : $urls->for($learner, $resource);

                return ['title' => (string) $resource->title, 'url' => $url];
            })
            ->filter(fn (array $resource): bool => filled($resource['url']))
            ->all() ?? [];

        return view('learn.show', [
            'curriculumPdfUrl' => $document === null ? null : $urls->for($learner, $document),
            'curriculumPdfSize' => $document === null ? null : (int) $document->getAttribute('size'),
            'resources' => $resources,
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
            'aiAvailable' => $decision->allowed && $lesson !== null && $this->acting->canViewPrivateLearnerConversations() && app(AssistantEligibility::class)->check($learner, $lesson)->isAvailable(),
            'learner' => $learner,
            'liveSession' => $decision->allowed && $entity instanceof LiveSession ? $entity : null,
            'liveState' => $entity instanceof LiveSession ? JoinWindow::state($entity) : null,
            'quizOverview' => $decision->allowed && $entity instanceof Quiz ? QuizOverview::for($learner, $entity) : null,
            'assignmentView' => $decision->allowed && $entity instanceof Assignment ? SubmissionView::for($learner, $entity) : null,
            'replayUrl' => $entity instanceof LiveSession ? $this->replayUrl($learner, $course, $entity) : null,
        ]);
    }

    /** The replay link, only when the replay lesson is published and this learner may open it (same decision as any recorded lesson). */
    private function replayUrl(User $learner, Course $course, LiveSession $session): ?string
    {
        $replay = $session->replay_item_id === null ? null : CurriculumItem::query()->whereKey($session->replay_item_id)->where('status', CurriculumItem::STATUS_PUBLISHED)->first();
        if ($replay === null || ! $this->access->canAccessItem($learner, $replay)->allowed) {
            return null;
        }

        return route('learn.show', ['course' => $course->getKey(), 'item' => $replay->getKey()]);
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
