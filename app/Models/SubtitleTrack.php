<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One WebVTT caption file for a lesson, kept private and served through signed account-bound links.
 *
 * @property int $lesson_id
 * @property string $locale
 * @property string $label
 * @property string $path
 * @property bool $is_default
 */
final class SubtitleTrack extends Model
{
    protected $fillable = ['lesson_id', 'locale', 'label', 'path', 'sha256', 'size', 'is_default', 'uploaded_by'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'size' => 'integer'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
