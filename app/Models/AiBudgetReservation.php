<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Short-lived reservation against a monthly AI budget ceiling while a provider call is in flight.
 *
 * @property string $scope
 * @property string $period
 * @property int $amount_minor
 * @property Carbon $expires_at
 */
final class AiBudgetReservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'expires_at' => 'datetime'];
    }
}
