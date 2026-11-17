<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\AI\Help\HelpRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A learner's request for a human after an assistant answer (AI spec 12, PRD 15.5). It carries the question, the answer
 * shown, the cited pages, the document version and the video position, plus a thread of replies and an event trail.
 *
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property int $lesson_id
 * @property int|null $assigned_instructor_id
 * @property int $conversation_id
 * @property int $ai_message_id
 * @property HelpRequestStatus $status
 * @property string $locale
 * @property int|null $video_position
 * @property int $document_version
 * @property string|null $ai_status
 * @property list<int>|null $source_pages
 * @property string $question
 * @property string $ai_answer
 * @property Carbon|null $due_at
 * @property Carbon|null $overdue_at
 * @property Carbon|null $first_replied_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 */
final class HelpRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => HelpRequestStatus::class, 'source_pages' => 'array', 'video_position' => 'integer', 'document_version' => 'integer',
            'due_at' => 'datetime', 'overdue_at' => 'datetime', 'first_replied_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_instructor_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return HasMany<HelpRequestMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(HelpRequestMessage::class)->orderBy('id');
    }

    /** @return HasMany<HelpRequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(HelpRequestEvent::class)->orderBy('id');
    }

    /** Waiting for the instructor and past the SLA clock. */
    public function isOverdue(): bool
    {
        return $this->status->awaitsInstructor() && $this->due_at !== null && $this->due_at->isPast();
    }
}
