<?php

declare(strict_types=1);

namespace App\Domain\Guardians\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * O-015: the OTP state of one guardian account. Only the keyed hash of the code is stored. `verified_at` set means the guardian confirmed
 * a code sent to their (verified) email.
 *
 * @property int $user_id
 * @property string|null $code_hash
 * @property Carbon|null $code_expires_at
 * @property int $attempts
 * @property Carbon|null $code_sent_at
 * @property Carbon|null $verified_at
 */
final class GuardianVerification extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashCode(int $userId, string $code): string
    {
        return hash_hmac('sha256', $userId.':'.trim($code), Config::string('app.key'));
    }

    public function hasPendingCode(): bool
    {
        return $this->code_hash !== null && $this->code_expires_at !== null && $this->code_expires_at->isFuture();
    }

    protected function casts(): array
    {
        return ['code_expires_at' => 'datetime', 'code_sent_at' => 'datetime', 'verified_at' => 'datetime', 'attempts' => 'integer'];
    }
}
