<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cost and latency of one assistant request. It carries an opaque hash of the student, never the student id.
 *
 * @property string $student_hash
 * @property int $lesson_id
 * @property string $provider
 * @property string|null $model
 * @property int $input_tokens
 * @property int $cached_tokens
 * @property int $output_tokens
 * @property int $cost_minor
 * @property int $latency_ms
 * @property string $result
 */
final class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_usage';

    protected $fillable = ['student_hash', 'lesson_id', 'provider', 'model', 'input_tokens', 'cached_tokens', 'output_tokens', 'cost_minor', 'latency_ms', 'result'];
}
