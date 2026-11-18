<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonKeyTerm;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** The approver picks which proposed key terms learners may see as starter chips (AI spec 6.6). */
final class ApproveKeyTerms
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  list<int>  $termIds */
    public function __invoke(User $actor, LessonCurriculumDocument $document, array $termIds): int
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('lesson_document.approve')) {
            throw new AuthorizationException;
        }

        $max = (int) config('ai.ingestion.key_terms_max', 8);
        if (count(array_unique($termIds)) > $max) {
            throw ValidationException::withMessages(['terms' => __('media.key_terms_too_many', ['max' => $max])]);
        }

        return DB::transaction(function () use ($actor, $document, $termIds): int {
            $document = LessonCurriculumDocument::query()->lockForUpdate()->findOrFail($document->getKey());
            CurriculumAccess::authorizeLesson($actor, $document->lesson, 'lesson_document.approve');
            LessonKeyTerm::query()->where('document_id', $document->getKey())->update(['approved' => false]);
            $approved = LessonKeyTerm::query()->where('document_id', $document->getKey())->whereIn('id', $termIds)->update(['approved' => true]);

            if ($this->audit->record('curriculum_document.key_terms_approved', $document->lesson, (int) $actor->getKey(), null, ['approved' => $approved], ['version' => $document->version]) === null) {
                throw new RuntimeException('Unable to record curriculum document audit.');
            }

            return $approved;
        });
    }
}
