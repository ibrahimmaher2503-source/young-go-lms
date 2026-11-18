<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An instructor's payout destination (YG-GAP-INS-003). `details` is one encrypted payload (holder, bank/wallet/Instapay identifiers):
 * unreadable in a database dump and hidden from serialization. Read it only through PayoutMask (masked) or
 * RevealPayoutProfile (finance, audited). Never log or audit its values.
 *
 * @property int $id
 * @property int $user_id
 * @property string $method
 * @property array<string, string> $details
 */
final class PayoutProfile extends Model
{
    public const string BANK = 'bank';

    public const string WALLET = 'wallet';

    public const string INSTAPAY = 'instapay';

    protected $table = 'instructor_payout_profiles';

    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['details'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['details' => 'encrypted:array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
