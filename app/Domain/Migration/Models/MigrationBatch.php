<?php

declare(strict_types=1);

namespace App\Domain\Migration\Models;

use App\Domain\Migration\Enums\MigrationBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $source_system
 * @property string $entity
 * @property string $mode
 * @property MigrationBatchStatus $status
 * @property int|null $resumed_from_batch_id
 * @property int $chunk_size
 * @property string|null $last_source_id
 * @property int $source_count
 * @property int $processed_count
 * @property int $imported_count
 * @property int $updated_count
 * @property int $unchanged_count
 * @property int $skipped_count
 * @property int $rejected_count
 * @property int $exceptions_count
 * @property array<string, mixed>|null $report
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
final class MigrationBatch extends Model
{
    protected $table = 'migration_batches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => MigrationBatchStatus::class,
            'chunk_size' => 'integer',
            'source_count' => 'integer',
            'processed_count' => 'integer',
            'imported_count' => 'integer',
            'updated_count' => 'integer',
            'unchanged_count' => 'integer',
            'skipped_count' => 'integer',
            'rejected_count' => 'integer',
            'exceptions_count' => 'integer',
            'report' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<self, $this> */
    public function resumedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resumed_from_batch_id');
    }

    /** @return HasMany<MigrationException, $this> */
    public function exceptions(): HasMany
    {
        return $this->hasMany(MigrationException::class, 'batch_id');
    }

    /** @return HasMany<MigrationRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(MigrationRecord::class, 'last_batch_id');
    }
}
