<?php

declare(strict_types=1);

namespace Tests\Unit\Fixtures;

use App\Domain\Shared\Search\HasSearchText;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

final class SearchableRecord extends Model
{
    use HasSearchText;
    use HasTranslations;

    protected $table = 'shared_primitives_search';

    public $timestamps = false;

    protected function searchTextAttributes(): array
    {
        return ['title', 'summary'];
    }

    /** @var list<string> */
    public array $translatable = ['title'];
}
