<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $type
 * @property array<string, mixed>|null $content
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

    protected $fillable = ['type', 'title', 'description', 'content', 'duration_seconds', 'is_preview', 'archived_at'];

    protected function casts(): array
    {
        return ['is_preview' => 'boolean', 'duration_seconds' => 'integer', 'archived_at' => 'datetime', 'content' => 'array'];
    }

    /** @return HasOne<MediaAsset, $this> */
    public function media(): HasOne
    {
        return $this->hasOne(MediaAsset::class);
    }

    /** @return HasMany<SubtitleTrack, $this> */
    public function subtitleTracks(): HasMany
    {
        return $this->hasMany(SubtitleTrack::class)->orderByDesc('is_default')->orderBy('locale');
    }

    /** @return HasMany<LessonResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<LessonCurriculumDocument, $this> */
    public function curriculumDocuments(): HasMany
    {
        return $this->hasMany(LessonCurriculumDocument::class)->orderByDesc('version');
    }

    /** The version learners and the AI use: active and READY. Null when nothing has been approved yet. */
    public function activeCurriculumDocument(): ?LessonCurriculumDocument
    {
        return $this->curriculumDocuments()->where('active', true)->where('status', LessonCurriculumDocument::STATUS_READY)->first();
    }

    /** @return MorphOne<CurriculumItem, $this> */
    public function item(): MorphOne
    {
        return $this->morphOne(CurriculumItem::class, 'itemable');
    }
}
