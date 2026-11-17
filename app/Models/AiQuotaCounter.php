<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Questions used by one student in one scope on one day.
 *
 * @property int $student_id
 * @property string $scope
 * @property string $period
 * @property int $count
 */
final class AiQuotaCounter extends Model
{
    protected $fillable = ['student_id', 'scope', 'period', 'count'];

    protected function casts(): array
    {
        return ['count' => 'integer'];
    }
}
