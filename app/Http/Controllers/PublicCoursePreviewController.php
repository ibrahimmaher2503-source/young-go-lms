<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Domain\Media\Queries\LessonPlayback;
use App\Domain\Media\SignedFileUrl;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Free preview lesson of a published course, open without an account. The decision comes from the EntitlementService;
 * anything else (locked lesson, other item type, unpublished course) is a plain 404 so nothing is revealed.
 */
final class PublicCoursePreviewController
{
    public function __invoke(string $course, int $item, PublicCourseDetail $details, EntitlementService $access): View
    {
        $found = $details->find($course);
        abort_if($found === null, 404);

        $entry = CurriculumItem::query()->where('course_id', $found->getKey())->with(['course', 'section', 'itemable'])->find($item);
        abort_if($entry === null || ! $access->canPreviewPublicly($entry)->allowed, 404);

        $lesson = $entry->itemable;
        abort_unless($lesson instanceof Lesson, 404);
        $lesson->loadMissing('resources');

        $viewer = app(ActingLearner::class)->current();
        $urls = app(SignedFileUrl::class);
        $resources = $lesson->resources
            ->filter(static fn (LessonResource $resource): bool => $resource->isPublicPreview())
            ->map(static function (LessonResource $resource) use ($viewer, $urls): array {
                $url = $resource->type === LessonResource::TYPE_LINK
                    ? $resource->url
                    : ($viewer instanceof User ? $urls->for($viewer, $resource) : null);

                return ['title' => (string) $resource->title, 'url' => $url];
            })
            ->filter(static fn (array $resource): bool => filled($resource['url']))
            ->values()
            ->all();

        return view('catalog.preview', [
            'course' => $found,
            'key' => PublicCourseDetail::key($found),
            'lesson' => $lesson,
            'viewer' => $viewer,
            'resources' => $resources,
            'descriptor' => LessonPlayback::for($lesson, $viewer),
        ]);
    }
}
