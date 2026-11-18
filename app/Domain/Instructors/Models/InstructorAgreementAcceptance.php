<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Proof that an instructor accepted one agreement version: version snapshot, time and IP (YG-GAP-INS-002).
 *
 * @property int $id
 * @property int $user_id
 * @property int $agreement_id
 * @property string $version
 * @property Carbon $accepted_at
 * @property string|null $ip
 */
final class InstructorAgreementAcceptance extends Model
{
    public $timestamps = false;

    protected $table = 'instructor_agreement_acceptances';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<InstructorAgreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(InstructorAgreement::class, 'agreement_id');
    }
}
