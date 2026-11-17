<?php

declare(strict_types=1);

namespace App\Domain\AI\Queries;

use App\Domain\AI\AssistantEligibility;
use App\Models\Lesson;
use App\Models\LessonKeyTerm;
use App\Models\User;

/** What the player needs to render the assistant entry (AI spec 17): availability with its reason, and the approved starter terms. */
final class AssistantStatus
{
    public function __construct(private readonly AssistantEligibility $eligibility) {}

    /** @return array{available: bool, reason: string|null, message: string|null, key_terms: list<string>} */
    public function for(User $student, Lesson $lesson): array
    {
        $availability = $this->eligibility->check($student, $lesson);
        $document = $availability->isAvailable() ? $lesson->activeCurriculumDocument() : null;
        $terms = $document === null ? [] : LessonKeyTerm::query()
            ->where('document_id', $document->getKey())->where('approved', true)->orderBy('id')->limit(5)->pluck('term')->map(strval(...))->all();

        return [
            'available' => $availability->isAvailable(),
            'reason' => $availability->reason?->value,
            'message' => $availability->message(),
            'key_terms' => array_values($terms),
        ];
    }
}
