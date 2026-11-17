<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion;

use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;

/** The readiness indicator in the curriculum manager (AI spec 14): none, working, needs approval, failed, ready. */
enum DocumentReadiness: string
{
    case None = 'none';
    case Processing = 'processing';
    case AwaitingApproval = 'awaiting_approval';
    case Failed = 'failed';
    case Ready = 'ready';

    public static function forLesson(Lesson $lesson): self
    {
        return self::fromStatus($lesson->curriculumDocuments()->first()?->status); // newest version first
    }

    public static function fromStatus(?string $status): self
    {
        return match ($status) {
            null => self::None,
            LessonCurriculumDocument::STATUS_READY => self::Ready,
            LessonCurriculumDocument::STATUS_AWAITING_APPROVAL => self::AwaitingApproval,
            LessonCurriculumDocument::STATUS_FAILED, LessonCurriculumDocument::STATUS_REJECTED => self::Failed,
            default => self::Processing,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::None => '⚪',
            self::Processing => '🟡',
            self::AwaitingApproval => '🟠',
            self::Failed => '🔴',
            self::Ready => '🟢',
        };
    }

    public function label(): string
    {
        return __('media.readiness.'.$this->value);
    }
}
