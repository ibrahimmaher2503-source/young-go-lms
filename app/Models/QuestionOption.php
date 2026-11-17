<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $question_id
 * @property bool $is_correct
 * @property int $sort
 */
final class QuestionOption extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['text'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'sort' => 'integer'];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
