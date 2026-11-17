<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A safeguarding flag raised by the assistant input guard (AI spec 8.1). Category and status only, no message text;
 * the conversation holds the context and is visible only to staff with ai.conversation.view. Recipients are OPEN (O-028).
 *
 * @property int $student_id
 * @property int|null $conversation_id
 * @property string $category
 * @property string $status
 * @property int|null $handled_by
 */
final class AiSafeguardingFlag extends Model
{
    protected $fillable = ['student_id', 'conversation_id', 'category', 'status', 'handled_by'];
}
