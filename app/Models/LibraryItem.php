<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A reusable file owned by the academy or one instructor.
 *
 * @property string $owner_type
 * @property int|null $owner_id
 * @property string $kind
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property string $sha256
 * @property string $protection_tier
 * @property list<string>|null $tags
 */
final class LibraryItem extends Model
{
    use HasTranslations;

    public const OWNER_ACADEMY = 'academy';

    public const OWNER_INSTRUCTOR = 'instructor';

    /** @var list<string> */
    public array $translatable = ['title'];

    protected $fillable = ['owner_type', 'owner_id', 'kind', 'title', 'tags', 'path', 'mime', 'size', 'sha256', 'protection_tier', 'created_by'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'size' => 'integer'];
    }

    /** Lessons and materials currently using this item. */
    public function referenceCount(): int
    {
        return MediaAsset::query()->where('library_item_id', $this->getKey())->count()
            + LessonResource::query()->where('library_item_id', $this->getKey())->count();
    }
}
