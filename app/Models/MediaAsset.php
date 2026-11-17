<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lesson's media source. Provider-neutral: every provider driver turns this row into a playback descriptor.
 *
 * @property int $lesson_id
 * @property string $provider
 * @property string $kind
 * @property string|null $external_id
 * @property string|null $url
 * @property string|null $path
 * @property string $protection_tier
 * @property string|null $exception_reason
 * @property Carbon|null $exception_at
 * @property int|null $duration_seconds
 * @property string $status
 * @property array<string, mixed>|null $metadata
 */
final class MediaAsset extends Model
{
    public const TIER_PROTECTED = 'T1';

    public const TIER_RESTRICTED = 'T2';

    public const TIER_OPEN = 'T3';

    protected $fillable = [
        'lesson_id', 'provider', 'kind', 'external_id', 'url', 'path', 'protection_tier', 'exception_reason', 'exception_by',
        'exception_at', 'duration_seconds', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'exception_at' => 'datetime', 'duration_seconds' => 'integer'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function hasException(): bool
    {
        return $this->exception_reason !== null && $this->exception_reason !== '';
    }
}
