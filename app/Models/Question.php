<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * One question of a quiz. Options hold the choices (single, multiple, true_false) or the accepted answers (short_answer);
 * essay questions have none and are graded by a person.
 *
 * @property int $id
 * @property int $quiz_id
 * @property string $type
 * @property float|string $points
 * @property int $sort
 * @property bool $active
 * @property int $version
 */
final class Question extends Model
{
    use HasTranslations;

    public const string SINGLE = 'single';

    public const string MULTIPLE = 'multiple';

    public const string TRUE_FALSE = 'true_false';

    public const string SHORT_ANSWER = 'short_answer';

    public const string ESSAY = 'essay';

    public const array TYPES = [self::SINGLE, self::MULTIPLE, self::TRUE_FALSE, self::SHORT_ANSWER, self::ESSAY];

    /** Types whose answer is a pick from options. */
    public const array CHOICE_TYPES = [self::SINGLE, self::MULTIPLE, self::TRUE_FALSE];

    /** @var list<string> */
    public array $translatable = ['prompt', 'explanation'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['points' => 'decimal:2', 'sort' => 'integer', 'active' => 'boolean', 'version' => 'integer'];
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort')->orderBy('id');
    }
}
