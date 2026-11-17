<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A quiz placed in the curriculum. Only the shell exists here; questions and attempts come in P6.
 *
 * @property Carbon|null $archived_at
 */
final class Quiz extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    protected $fillable = ['title', 'description', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /** @return MorphOne<CurriculumItem, $this> */
    public function item(): MorphOne
    {
        return $this->morphOne(CurriculumItem::class, 'itemable');
    }
}
