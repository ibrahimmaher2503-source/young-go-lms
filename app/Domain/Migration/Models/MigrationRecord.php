<?php

declare(strict_types=1);

namespace App\Domain\Migration\Models;

use App\Domain\Migration\Enums\MigrationRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $source_system
 * @property string $entity
 * @property string $source_table
 * @property string $source_id
 * @property string|null $target_type
 * @property int|null $target_id
 * @property string $checksum
 * @property MigrationRecordStatus $status
 * @property string|null $rejection_reason
 * @property int|null $last_batch_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $imported_at
 */
final class MigrationRecord extends Model
{
    protected $table = 'migration_records';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => MigrationRecordStatus::class,
            'target_id' => 'integer',
            'last_batch_id' => 'integer',
            'metadata' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MigrationBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MigrationBatch::class, 'last_batch_id');
    }
}
