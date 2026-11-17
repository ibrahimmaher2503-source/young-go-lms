<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A learner's verdict on one assistant answer (AI spec 16). One row per answer and kind; it holds no text.
 *
 * @property int $message_id
 * @property string $type
 */
final class AiFeedback extends Model
{
    public const string UNDERSTOOD = 'understood';

    public const string NOT_UNDERSTOOD = 'not_understood';

    protected $table = 'ai_feedback';

    protected $fillable = ['message_id', 'type'];
}
