<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One thread of questions a learner asked the lesson assistant about one lesson, tied to the document version it used.
 *
 * @property int $student_id
 * @property int $course_id
 * @property int $lesson_id
 * @property int $document_version
 * @property string $locale
 * @property string $style_tier
 * @property int|null $video_position
 * @property string $status
 */
final class AiConversation extends Model
{
    protected $fillable = ['student_id', 'course_id', 'lesson_id', 'document_version', 'locale', 'style_tier', 'video_position', 'status'];

    protected function casts(): array
    {
        return ['document_version' => 'integer', 'video_position' => 'integer'];
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }
}
