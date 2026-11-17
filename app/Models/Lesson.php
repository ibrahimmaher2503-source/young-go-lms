<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $type
 * @property int|null $duration_seconds
 * @property bool $is_preview
 * @property Carbon|null $archived_at
 */
final class Lesson extends Model
{
    use HasTranslations;

    public const TYPES = ['video', 'audio', 'document', 'text'];

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    protected $fillable = ['type', 'title', 'description', 'duration_seconds', 'is_preview', 'archived_at'];

    protected function casts(): array
    {
        return ['is_preview' => 'boolean', 'duration_seconds' => 'integer', 'archived_at' => 'datetime'];
    }

    /** @return HasOne<MediaAsset, $this> */
    public function media(): HasOne
    {
        return $this->hasOne(MediaAsset::class);
    }

    /** @return MorphOne<CurriculumItem, $this> */
    public function item(): MorphOne
    {
        return $this->morphOne(CurriculumItem::class, 'itemable');
    }
}
