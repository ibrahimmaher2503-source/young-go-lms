<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $course_id
 * @property int $sort
 * @property bool $published
 * @property Carbon|null $archived_at
 */
final class CourseSection extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title'];

    protected $fillable = ['course_id', 'title', 'sort', 'published', 'archived_at'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'sort' => 'integer', 'archived_at' => 'datetime'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<CurriculumItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CurriculumItem::class, 'section_id')->orderBy('sort');
    }
}
