<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Makes a version READY and active and retires the previous one in one step (AI spec 6.7). The extraction pipeline
 * (T-P3-09) calls this when its last stage passes; until then it is how an approver activates a version.
 */
final class ApproveCurriculumDocument
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $approver, LessonCurriculumDocument $document): LessonCurriculumDocument
    {
        if ($approver->getAttribute('status') !== 'active' || ! $approver->can('lesson_document.approve')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($approver, $document): LessonCurriculumDocument {
            Lesson::query()->whereKey($document->lesson_id)->lockForUpdate()->first();
            $current = LessonCurriculumDocument::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($current->status === LessonCurriculumDocument::STATUS_FAILED) {
                throw ValidationException::withMessages(['document' => __('media.pdf_failed_version')]);
            }

            $retired = LessonCurriculumDocument::query()->where('lesson_id', $current->lesson_id)->where('active', true)->whereKeyNot($current->getKey())->pluck('id')->all();
            LessonCurriculumDocument::query()->whereKey($retired)->update(['active' => false]);
            $current->forceFill(['status' => LessonCurriculumDocument::STATUS_READY, 'active' => true, 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();

            if ($this->audit->record('curriculum_document.approved', $current->lesson, (int) $approver->getKey(), null, ['version' => $current->version, 'retired' => count($retired)]) === null) {
                throw new RuntimeException('Unable to record curriculum document audit.');
            }

            return $current;
        });
    }
}
