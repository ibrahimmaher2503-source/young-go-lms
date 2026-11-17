<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One reply in a help request thread.
 *
 * @property int $help_request_id
 * @property int $author_id
 * @property string $role
 * @property string $body
 * @property Carbon|null $created_at
 */
final class HelpRequestMessage extends Model
{
    public const string ROLE_INSTRUCTOR = 'instructor';

    public const string ROLE_STUDENT = 'student';

    public const UPDATED_AT = null;

    protected $fillable = ['help_request_id', 'author_id', 'role', 'body'];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
