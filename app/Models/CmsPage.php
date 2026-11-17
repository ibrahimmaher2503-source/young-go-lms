<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

final class CmsPage extends Model
{
    use HasTranslations;

    protected $table = 'pages';

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    /** @var list<string> */
    public array $translatable = ['title', 'body', 'seo_title', 'seo_description'];

    protected $fillable = ['slug', 'title', 'body', 'status', 'template', 'seo_title', 'seo_description'];
}
