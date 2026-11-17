<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $lesson_id
 * @property string $type
 * @property string|null $path
 * @property string|null $url
 * @property string|null $mime
 * @property bool $downloadable
 * @property string $visibility
 * @property bool $is_preview
 * @property int $sort
 */
final class LessonResource extends Model
{
    use HasTranslations;

    public const TYPE_FILE = 'file';

    public const TYPE_IMAGE = 'image';

    public const TYPE_LINK = 'link';

    /** @var list<string> */
    public array $translatable = ['title'];

    protected $fillable = [
        'lesson_id', 'type', 'title', 'path', 'url', 'mime', 'size', 'sha256', 'downloadable', 'visibility', 'is_preview', 'sort', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['downloadable' => 'boolean', 'is_preview' => 'boolean', 'sort' => 'integer', 'size' => 'integer'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isPublicPreview(): bool
    {
        return $this->is_preview && $this->visibility === 'public_preview';
    }
}
