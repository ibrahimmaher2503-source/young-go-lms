<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One immutable version of the assistant's system prompt (AI spec 9). Answers record the version they used.
 *
 * @property int $version
 * @property string $template
 * @property array<string, string> $style_tiers
 * @property bool $active
 */
final class AiPromptVersion extends Model
{
    protected $fillable = ['version', 'template', 'style_tiers', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'style_tiers' => 'array', 'active' => 'boolean'];
    }

    public static function current(): ?self
    {
        return self::query()->where('active', true)->orderByDesc('version')->first();
    }
}
