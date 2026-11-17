<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int|null $device_id
 * @property string $session_hash
 * @property Carbon $last_active_at
 * @property Carbon|null $signed_out_at
 * @property string|null $signed_out_reason
 */
final class UserSession extends Model
{
    public const string LIMIT = 'limit';

    public const string LOGOUT = 'logout';

    public const string RESET = 'reset';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_active_at' => 'datetime', 'signed_out_at' => 'datetime'];
    }
}
