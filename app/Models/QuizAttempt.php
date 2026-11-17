<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One attempt of one learner. The snapshot holds the answer key, so it is hidden from serialization and never leaves the server.
 *
 * @property int $id
 * @property int $quiz_id
 * @property int $learner_id
 * @property int $attempt_no
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $submitted_at
 * @property string|null $submitted_by
 * @property array{questions: list<array<string, mixed>>} $snapshot
 * @property array<string, mixed> $settings
 * @property float|string|null $score
 * @property float|string $total
 * @property float|string|null $percent
 * @property bool|null $passed
 * @property bool $needs_review
 * @property Carbon|null $released_at
 */
final class QuizAttempt extends Model
{
    public const string IN_PROGRESS = 'in_progress';

    public const string SUBMITTED = 'submitted';

    protected $guarded = ['id'];

    protected $hidden = ['snapshot', 'settings'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'deadline_at' => 'datetime', 'submitted_at' => 'datetime', 'released_at' => 'datetime',
            'snapshot' => 'array', 'settings' => 'array', 'passed' => 'boolean', 'needs_review' => 'boolean', 'attempt_no' => 'integer',
        ];
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    /** @return HasMany<QuizAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::IN_PROGRESS;
    }

    /** Past the server deadline (a small grace for slow networks is applied by the Actions, not here). */
    public function isExpired(): bool
    {
        return $this->deadline_at !== null && now()->gt($this->deadline_at);
    }
}
