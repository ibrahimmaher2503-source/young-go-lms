<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Cohorts\Cohort;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A course announcement from staff to the learners of the course, or of one cohort. Draft until published; publishing
 * sends one notice per recipient and freezes the text.
 *
 * @property int $id
 * @property int $course_id
 * @property int|null $cohort_id
 * @property string $title_ar
 * @property string $title_en
 * @property string $body_ar
 * @property string $body_en
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $recipients_count
 * @property int|null $author_id
 */
final class Announcement extends Model
{
    public const string DRAFT = 'draft';

    public const string PUBLISHED = 'published';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'recipients_count' => 'integer'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Cohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function titleIn(string $locale): string
    {
        return $locale === 'ar' ? $this->title_ar : $this->title_en;
    }

    public function bodyIn(string $locale): string
    {
        return $locale === 'ar' ? $this->body_ar : $this->body_en;
    }
}
