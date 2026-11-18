<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An admin override of one retention class. No row means the class default applies; `retention_days` null keeps the data.
 *
 * @property int $id
 * @property string $data_class
 * @property bool $overridden
 * @property int|null $retention_days
 * @property int|null $updated_by
 * @property Carbon|null $last_pruned_at
 * @property int $last_pruned_count
 */
final class RetentionPolicy extends Model
{
    protected $table = 'retention_policies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['overridden' => 'boolean', 'retention_days' => 'integer', 'last_pruned_at' => 'datetime', 'last_pruned_count' => 'integer'];
    }
}
