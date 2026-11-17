<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $live_session_id
 * @property int $learner_id
 * @property string $status
 * @property string $source
 * @property int|null $marked_by
 * @property Carbon $marked_at
 * @property string|null $note
 */
final class LiveAttendance extends Model
{
    public const string PRESENT = 'present';

    public const string LATE = 'late';

    public const string ABSENT = 'absent';

    public const string EXCUSED = 'excused';

    public const array STATUSES = [self::PRESENT, self::LATE, self::ABSENT, self::EXCUSED];

    public const string SOURCE_JOIN = 'join';

    public const string SOURCE_MANUAL = 'manual';

    protected $table = 'live_attendance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['marked_at' => 'datetime'];
    }

    /** @return BelongsTo<LiveSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }
}
