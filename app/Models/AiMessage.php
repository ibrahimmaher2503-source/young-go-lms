<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One turn of a thread. An assistant row keeps what was shown (status, cited pages, whether the quote was verified, the
 * prompt version and model), never the lesson text or the supporting quote.
 *
 * @property int $conversation_id
 * @property string $role
 * @property string $content
 * @property string|null $status
 * @property list<int>|null $source_pages
 * @property bool|null $quote_verified
 * @property array<string, mixed>|null $guard_flags
 * @property int|null $prompt_version
 * @property string|null $model
 * @property bool $cache_hit
 * @property Carbon|null $created_at
 */
final class AiMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'role', 'content', 'status', 'source_pages', 'quote_verified', 'guard_flags', 'prompt_version', 'model', 'cache_hit'];

    protected function casts(): array
    {
        return ['source_pages' => 'array', 'quote_verified' => 'boolean', 'guard_flags' => 'array', 'cache_hit' => 'boolean', 'prompt_version' => 'integer'];
    }
}
