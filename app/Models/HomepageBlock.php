<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

final class HomepageBlock extends Model
{
    use HasTranslations;

    public const TYPES = [
        'hero', 'categories', 'featured_courses', 'how_it_works', 'instructors', 'testimonials', 'parents', 'faq', 'cta',
    ];

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected $fillable = ['type', 'title', 'body', 'sort', 'visible'];

    protected function casts(): array
    {
        return ['visible' => 'boolean', 'sort' => 'integer'];
    }
}
