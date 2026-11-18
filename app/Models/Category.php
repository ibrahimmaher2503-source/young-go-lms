<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Shared\Search\HasSearchText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

final class Category extends Model
{
    use HasSearchText;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'seo_title', 'seo_description', 'seo_keywords'];

    protected $fillable = ['parent_id', 'name', 'description', 'slug_ar', 'slug_en', 'icon', 'image', 'sort', 'status', 'seo_title', 'seo_description', 'seo_keywords'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected function searchTextAttributes(): array
    {
        return ['name', 'description'];
    }
}
