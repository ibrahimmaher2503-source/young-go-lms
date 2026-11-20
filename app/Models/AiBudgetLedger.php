<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sentinel row per (scope, month) locked with FOR UPDATE to serialize monthly AI budget reservations.
 *
 * @property string $scope
 * @property string $period
 */
final class AiBudgetLedger extends Model
{
    protected $guarded = ['id'];
}
