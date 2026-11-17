<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\AccessDecision;
use App\Domain\Live\JoinWindow;
use App\Domain\Media\Queries\LessonPlayback;
use App\Domain\Media\SignedFileUrl;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "View as learner" (ED-024, YG-GAP-LRN-005): the player as a learner would see it, for staff who can see the course.
 * It reads content and writes nothing: no progress, no progress endpoints, no assistant calls. Draft and not-yet-open
 * items are shown, with the reason a real learner would find them locked.
 */
final class PreviewController
{
    public function __construct(private readonly EntitledCurriculumQuery $curriculum) {}

    public function __invoke(Request $request, Course $course, CurriculumItem $item): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('curriculum.view'), 403);
        Gate::forUser($user)->authorize('view', $course); // a course outside the person's scope is a 404
        abort_unless((int) $item->course_id === (int) $course->getKey(), 404);

        $flat = collect($this->curriculum->forPreview($course));
        $sections = $flat->groupBy('section_id')->map(fn ($rows) => ['id' => (int) $rows->first()['section_id'], 'title' => (string) $rows->first()['section_title'], 'items' => $rows->all()])->values()->all();
        $entry = $flat->firstWhere('id', (int) $item->getKey());
        $entity = $item->itemable;
        $lesson = $entity instanceof Lesson ? $entity : null;
        $urls = app(SignedFileUrl::class);
        $document = $lesson?->activeCurriculumDocument();

        return view('learn.show', [
            'preview' => true,
            'previewReason' => $entry['reason'] ?? null,
            'previewDraft' => (bool) ($entry['draft'] ?? false),
            'backUrl' => CourseResource::getUrl('curriculum', ['record' => $course]),
            'curriculumPdfUrl' => $document === null ? null : $urls->for($user, $document),
            'curriculumPdfSize' => $document === null ? null : (int) $document->getAttribute('size'),
            'resources' => $lesson?->resources->map(fn (LessonResource $resource): array => [
                'title' => (string) $resource->title,
                'url' => $resource->type === LessonResource::TYPE_LINK ? $resource->url : $urls->for($user, $resource),
            ])->filter(fn (array $resource): bool => filled($resource['url']))->all() ?? [],
            'entitled' => false,
            'lessonProgress' => null,
            'courseProgress' => null,
            'completed' => [],
            'canMarkComplete' => false,
            'course' => $course,
            'item' => $item,
            'entity' => $entity,
            'lesson' => $lesson,
            'sections' => $sections,
            'decision' => AccessDecision::allow(),
            'neighbours' => EntitledCurriculumQuery::neighbours($sections, (int) $item->getKey()),
            'descriptor' => $lesson !== null ? LessonPlayback::for($lesson, $user) : null,
            'aiAvailable' => false,
            'learner' => $user,
            'quizOverview' => null,
            'liveSession' => $entity instanceof LiveSession ? $entity : null,
            'liveState' => $entity instanceof LiveSession ? JoinWindow::state($entity) : null,
        ]);
    }
}
