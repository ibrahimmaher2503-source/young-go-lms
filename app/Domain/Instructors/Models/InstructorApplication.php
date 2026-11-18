<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Models;

use App\Domain\Instructors\ApplicationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An account's request to teach as an independent instructor (YG-GAP-INS-001). The ID document is private: its stored path
 * and checksum never leave through serialization. `pending_user_id` is owned by the application Actions.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $pending_user_id
 * @property string $full_name
 * @property string|null $phone
 * @property string $discipline
 * @property int|null $experience_years
 * @property string $bio
 * @property list<string> $portfolio_links
 * @property string $locale
 * @property string $id_document_path
 * @property string $id_document_mime
 * @property int $id_document_size
 * @property string $id_document_sha256
 * @property ApplicationStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon $created_at
 */
final class InstructorApplication extends Model
{
    protected $table = 'instructor_applications';

    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['id_document_path', 'id_document_sha256', 'phone'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'portfolio_links' => 'array',
            'experience_years' => 'integer',
            'id_document_size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::Pending;
    }
}
