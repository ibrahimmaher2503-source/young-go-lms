<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Content\ContactEnquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message sent through the public contact form (YG-WEB-007). Holds personal data: never log its fields.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $message
 * @property string $locale
 * @property ContactEnquiryStatus $status
 * @property int|null $assigned_to_id
 * @property Carbon|null $handled_at
 */
final class ContactEnquiry extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ContactEnquiryStatus::class, 'handled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
}
