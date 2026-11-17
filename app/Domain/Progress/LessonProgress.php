<?php

declare(strict_types=1);

namespace App\Domain\Progress;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $learner_id
 * @property int $curriculum_item_id
 * @property string $status
 * @property int $position_seconds
 * @property int $furthest_seconds
 * @property int $percent
 * @property string|null $completion_source
 * @property Carbon|null $completed_at
 * @property Carbon|null $last_accessed_at
 */
final class LessonProgress extends Model
{
    public const string IN_PROGRESS = 'in_progress';

    public const string COMPLETED = 'completed';

    protected $table = 'lesson_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'last_accessed_at' => 'datetime'];
    }
}
