<?php

declare(strict_types=1);

namespace App\Domain\Migration\Models;

use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $batch_id
 * @property string $source_system
 * @property string $entity
 * @property string $source_table
 * @property string $source_id
 * @property string $code
 * @property MigrationExceptionSeverity $severity
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property Carbon|null $resolved_at
 * @property string|null $resolution
 */
final class MigrationException extends Model
{
    protected $table = 'migration_exceptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'severity' => MigrationExceptionSeverity::class,
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MigrationBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MigrationBatch::class, 'batch_id');
    }
}
