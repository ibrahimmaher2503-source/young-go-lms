<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One stored evaluation run (AI spec 13.4): provider, prompt version, metrics and pass/fail. Metrics hold counts and
 * scores only, never questions or answers.
 *
 * @property string $provider
 * @property int|null $prompt_version
 * @property array<string, mixed>|null $metrics
 * @property string|null $result
 */
final class AiEvalRun extends Model
{
    protected $fillable = ['provider', 'prompt_version', 'metrics', 'result'];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'prompt_version' => 'integer'];
    }
}
