<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A verified answer reusable by any learner of the same lesson, document version, reply language and style tier (AI spec 10.3).
 * It holds the answer, its pages and language only: never a question text, a student id or the supporting quote.
 *
 * @property int $lesson_id
 * @property int $document_version
 * @property string $question_hash
 * @property string $locale
 * @property string $style_tier
 * @property array<string, mixed> $answer_json
 * @property int $hits
 * @property Carbon|null $expires_at
 */
final class AiAnswerCache extends Model
{
    protected $table = 'ai_answer_cache';

    protected $fillable = ['lesson_id', 'document_version', 'question_hash', 'locale', 'style_tier', 'answer_json', 'hits', 'expires_at'];

    protected function casts(): array
    {
        return ['answer_json' => 'array', 'document_version' => 'integer', 'hits' => 'integer', 'expires_at' => 'datetime'];
    }
}
