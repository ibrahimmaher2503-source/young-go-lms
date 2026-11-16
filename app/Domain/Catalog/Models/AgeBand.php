<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/** @property array<string, string>|string $label */
#[Fillable(['label', 'min', 'max', 'sort'])]
class AgeBand extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['label'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min' => 'integer',
            'max' => 'integer',
            'sort' => 'integer',
        ];
    }
}
