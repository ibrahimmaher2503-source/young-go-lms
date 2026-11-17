<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Media\Queries\LessonPlayback;
use App\Models\CurriculumItem;
use App\Models\Lesson;
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

        return view('catalog.preview', [
            'course' => $found,
            'key' => PublicCourseDetail::key($found),
            'lesson' => $lesson,
            'descriptor' => LessonPlayback::for($lesson),
        ]);
    }
}
