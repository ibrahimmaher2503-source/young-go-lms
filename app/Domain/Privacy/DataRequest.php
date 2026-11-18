<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One data-subject request (YG-GAP-PRV-001): export, correction or deletion/anonymization. `subject_id` is the account the
 * data belongs to; `requested_by` is that account or a verified guardian acting for a child.
 *
 * @property int $id
 * @property int $subject_id
 * @property int $requested_by
 * @property DataRequestType $type
 * @property DataRequestStatus $status
 * @property string|null $details
 * @property string|null $review_note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property string|null $completion_note
 * @property string|null $archive_path
 * @property string|null $archive_sha256
 * @property int|null $archive_size
 * @property Carbon|null $archive_expires_at
 * @property Carbon|null $archive_downloaded_at
 * @property Carbon|null $created_at
 */
final class DataRequest extends Model
{
    protected $table = 'data_requests';

    protected $guarded = ['id'];

    /** The archive location never leaves through serialization. */
    protected $hidden = ['archive_path', 'archive_sha256'];

    protected function casts(): array
    {
        return [
            'type' => DataRequestType::class,
            'status' => DataRequestStatus::class,
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'archive_expires_at' => 'datetime',
            'archive_downloaded_at' => 'datetime',
            'archive_size' => 'integer',
        ];
    }

    public function hasDownloadableArchive(): bool
    {
        return $this->type === DataRequestType::Export
            && $this->status === DataRequestStatus::Completed
            && $this->archive_path !== null
            && $this->archive_expires_at !== null
            && $this->archive_expires_at->isFuture();
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
