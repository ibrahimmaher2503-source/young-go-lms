<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_attempt_id
 * @property int $question_id
 * @property int $question_version
 * @property array<string, mixed>|null $answer
 * @property bool|null $is_correct
 * @property float|string|null $points_awarded
 * @property string|null $feedback
 * @property Carbon|null $graded_at
 */
final class QuizAnswer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['answer' => 'array', 'is_correct' => 'boolean', 'graded_at' => 'datetime', 'question_version' => 'integer'];
    }

    /** @return BelongsTo<QuizAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }
}
