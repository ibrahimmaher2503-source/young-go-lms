<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One uploaded version of a lesson's curriculum PDF. Only the active READY version is the AI source (AI spec 6.7).
 *
 * @property int $lesson_id
 * @property int $version
 * @property string $path
 * @property string $sha256
 * @property int $pages
 * @property string $status
 * @property bool $active
 * @property Carbon|null $approved_at
 */
final class LessonCurriculumDocument extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_EXTRACTING = 'extracting';

    public const STATUS_AWAITING_APPROVAL = 'awaiting_approval';

    public const STATUS_INDEXING = 'indexing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'lesson_id', 'version', 'path', 'sha256', 'size', 'pages', 'status', 'failure_reason', 'quality_score',
        'uploaded_by', 'approved_by', 'approved_at', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'version' => 'integer', 'pages' => 'integer', 'size' => 'integer', 'approved_at' => 'datetime'];
    }

    /** @return HasMany<LessonDocumentPage, $this> */
    public function documentPages(): HasMany
    {
        return $this->hasMany(LessonDocumentPage::class, 'document_id')->orderBy('page_no');
    }

    /** @return HasMany<LessonKeyTerm, $this> */
    public function keyTerms(): HasMany
    {
        return $this->hasMany(LessonKeyTerm::class, 'document_id')->orderBy('id');
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
