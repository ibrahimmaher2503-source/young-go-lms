<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $guardian_id
 * @property int $child_id
 * @property string $status
 * @property string $method
 */
final class GuardianChildLink extends Model
{
    public const string STATUS_VERIFIED = 'verified';

    public const string METHOD_CREATED_BY_PARENT = 'created_by_parent';

    public const string METHOD_LINK_CODE = 'link_code';

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    /** @return BelongsTo<User, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_id');
    }

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'ai_consent_granted_at' => 'datetime',
        ];
    }
}
