<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * An assignment placed in the curriculum: brief, rules and the files an instructor attaches. Submissions are separate rows.
 *
 * @property int $id
 * @property Carbon|null $due_at
 * @property list<string>|null $allowed_types
 * @property int|null $max_size_kb
 * @property string $late_policy
 * @property int|null $late_penalty_percent
 * @property int $max_revisions
 * @property Carbon|null $archived_at
 */
final class Assignment extends Model
{
    use HasTranslations;

    public const string LATE_NOT_ALLOWED = 'not_allowed';

    public const string LATE_ALLOWED = 'allowed';

    public const string LATE_PENALTY = 'penalty';

    public const array LATE_POLICIES = [self::LATE_NOT_ALLOWED, self::LATE_ALLOWED, self::LATE_PENALTY];

    /** Kinds a learner may hand in; "text" is a written answer, the others are files. */
    public const array TYPES = ['text', 'image', 'video', 'audio', 'document'];

    /** @var list<string> */
    public array $translatable = ['title', 'description', 'instructions'];

    protected $fillable = [
        'title', 'description', 'archived_at', 'instructions', 'due_at', 'allowed_types', 'max_size_kb', 'late_policy', 'late_penalty_percent', 'max_revisions',
    ];

    protected $attributes = ['late_policy' => 'not_allowed', 'max_revisions' => 0];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'due_at' => 'datetime', 'allowed_types' => 'array', 'max_size_kb' => 'integer', 'late_penalty_percent' => 'integer', 'max_revisions' => 'integer'];
    }

    /** @return MorphOne<CurriculumItem, $this> */
    public function item(): MorphOne
    {
        return $this->morphOne(CurriculumItem::class, 'itemable');
    }

    /** @return HasMany<AssignmentAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentAttachment::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<AssignmentSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && now()->gt($this->due_at);
    }
}
