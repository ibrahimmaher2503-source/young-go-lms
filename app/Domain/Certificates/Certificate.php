<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

use App\Domain\Completion\CourseCompletion;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An issued certificate. At most one live row (`active` = 1) per completion; a revoked row keeps its token so the link
 * still answers "revoked". Name, titles and date are a snapshot taken at issue time.
 *
 * @property int $id
 * @property int $course_completion_id
 * @property int $course_id
 * @property int $learner_id
 * @property string $number
 * @property string $token
 * @property int|null $active
 * @property Carbon $issued_at
 * @property string|null $learner_name_ar
 * @property string|null $learner_name_en
 * @property string $learner_name
 * @property string|null $course_title_ar
 * @property string|null $course_title_en
 * @property Carbon $completed_on
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property int|null $revoked_by
 */
final class Certificate extends Model
{
    protected $table = 'certificates';

    protected $guarded = ['id'];

    /** The token and the staff reason never leave through serialization. */
    protected $hidden = ['token', 'revoked_reason', 'revoked_by'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'completed_on' => 'date', 'revoked_at' => 'datetime', 'active' => 'integer'];
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null;
    }

    public function verifyUrl(): string
    {
        return route('certificates.verify', ['token' => $this->token]);
    }

    public function nameIn(string $locale): string
    {
        $primary = $locale === 'ar' ? $this->learner_name_ar : $this->learner_name_en;
        $other = $locale === 'ar' ? $this->learner_name_en : $this->learner_name_ar;

        return filled($primary) ? $primary : (filled($other) ? $other : $this->learner_name);
    }

    /** The course title in the wanted language, falling back to the other one. */
    public function titleIn(string $locale): string
    {
        $primary = $locale === 'ar' ? $this->course_title_ar : $this->course_title_en;
        $other = $locale === 'ar' ? $this->course_title_en : $this->course_title_ar;

        return (string) (filled($primary) ? $primary : $other);
    }

    /** @return BelongsTo<CourseCompletion, $this> */
    public function completion(): BelongsTo
    {
        return $this->belongsTo(CourseCompletion::class, 'course_completion_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }
}
