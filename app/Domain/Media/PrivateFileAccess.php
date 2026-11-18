<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Catalog\Queries\CourseDirectory;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Entitlements\EntitlementService;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonResource;
use App\Models\MediaAsset;
use App\Models\SubtitleTrack;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One authorization callback per private file type. Fails closed: staff who manage the course may open its files,
 * explicit public previews are open to any signed-in user, everyone else needs the lesson entitlement.
 */
final class PrivateFileAccess
{
    public function __construct(private readonly LessonAccessChecker $entitlements, private readonly EntitlementService $previewAccess) {}

    public function allows(User $user, Model $file): bool
    {
        if ($user->getAttribute('status') !== 'active') {
            return false;
        }

        $lesson = match (true) {
            $file instanceof LessonResource, $file instanceof LessonCurriculumDocument, $file instanceof SubtitleTrack, $file instanceof MediaAsset => $file->lesson,
            default => null,
        };
        if (! $lesson instanceof Lesson) {
            return false;
        }

        $courseId = $lesson->item()->value('course_id');
        if ($courseId !== null && CourseDirectory::for($user)->whereKey($courseId)->exists()) {
            return true; // authors and admins of this course
        }

        if ($file instanceof LessonCurriculumDocument && (! $file->active || $file->status !== LessonCurriculumDocument::STATUS_READY)) {
            return false;
        }

        if ($file instanceof MediaAsset) {
            $item = $lesson->item()->with(['course', 'section'])->first();

            return $item !== null && $this->previewAccess->canAccessItem($user, $item)->allowed;
        }

        if ($file instanceof LessonResource) {
            if ($file->type === LessonResource::TYPE_LINK || ! $file->downloadable) {
                return false;
            }
            if ($file->isPublicPreview() && $this->previewAccess->canAccessPublicResource($user, $lesson)) {
                return true;
            }
        }

        return $this->entitlements->canAccessLessonFiles($user, $lesson);
    }
}
