<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A quiz placed in the curriculum, with its settings and questions. Attempts and grading are T-P6-02.
 * Total marks are derived from the active questions, never stored.
 *
 * @property int $id
 * @property string $title
 * @property int|null $time_limit_minutes
 * @property int|null $pass_mark_percent
 * @property int|null $max_attempts
 * @property string $result_visibility
 * @property bool $randomize
 * @property string $progression
 * @property Carbon|null $archived_at
 */
final class Quiz extends Model
{
    use HasTranslations;

    public const array RESULT_VISIBILITY = ['full', 'score_only', 'hidden'];

    public const array PROGRESSION = ['after_submission', 'require_pass'];

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    protected $fillable = [
        'title', 'description', 'archived_at', 'time_limit_minutes', 'pass_mark_percent', 'max_attempts', 'result_visibility', 'randomize', 'progression',
    ];

    protected $attributes = ['result_visibility' => 'full', 'randomize' => false, 'progression' => 'after_submission'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime', 'time_limit_minutes' => 'integer', 'pass_mark_percent' => 'integer', 'max_attempts' => 'integer', 'randomize' => 'boolean',
        ];
    }

    /** @return MorphOne<CurriculumItem, $this> */
    public function item(): MorphOne
    {
        return $this->morphOne(CurriculumItem::class, 'itemable');
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<Question, $this> */
    public function activeQuestions(): HasMany
    {
        return $this->questions()->where('active', true);
    }

    /** Sum of the points of the active questions. */
    public function totalMarks(): float
    {
        return (float) $this->activeQuestions()->sum('points');
    }
}
