<?php

declare(strict_types=1);

namespace App\Domain\Completion;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;

/**
 * The completion rules of one course. A course without a saved row uses the working defaults in config/learning.php.
 * A rule that is null or false is off. With no rule on, nothing completes by itself.
 *
 * @property int $course_id
 * @property int|null $lesson_percent
 * @property bool $require_quizzes
 * @property bool $require_assignments
 * @property int|null $attendance_percent
 * @property bool $manual_approval
 */
final class CourseCompletionRule extends Model
{
    protected $table = 'course_completion_rules';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'lesson_percent' => 'integer', 'attendance_percent' => 'integer', 'require_quizzes' => 'boolean',
            'require_assignments' => 'boolean', 'manual_approval' => 'boolean',
        ];
    }

    /** The saved rules, or an unsaved instance holding the defaults. */
    public static function forCourse(Course $course): self
    {
        return self::query()->where('course_id', $course->getKey())->first()
            ?? new self(['course_id' => $course->getKey(), ...(array) config('learning.completion.defaults')]);
    }

    /** @return array{lesson_percent: int|null, require_quizzes: bool, require_assignments: bool, attendance_percent: int|null, manual_approval: bool} */
    public function values(): array
    {
        return [
            'lesson_percent' => $this->lesson_percent, 'require_quizzes' => $this->require_quizzes, 'require_assignments' => $this->require_assignments,
            'attendance_percent' => $this->attendance_percent, 'manual_approval' => $this->manual_approval,
        ];
    }

    /** Whether any measurable rule is on (manual approval alone does not measure anything). */
    public function hasCriteria(): bool
    {
        return $this->lesson_percent !== null || $this->require_quizzes || $this->require_assignments || $this->attendance_percent !== null;
    }
}
