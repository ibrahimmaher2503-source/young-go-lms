<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Queries\CatalogQuery;
use App\Domain\Catalog\Queries\PublicCategory;
use App\Domain\Seo\CategorySeo;
use Illuminate\Contracts\View\View;

/** Public category landing page (YG-GAP-CMS-002): intro text, sub-categories and the published courses of the category. Inactive or unknown = 404. */
final class PublicCategoryController
{
    public function __invoke(string $category, PublicCategory $categories, CatalogQuery $catalog): View
    {
        $found = $categories->find($category);
        abort_if($found === null, 404);

        $courses = $catalog->matching(['category' => $found->getKey()], null)->paginate(12);

        return view('catalog.category', [
            'category' => $found,
            'children' => $categories->children($found),
            'courses' => $courses,
            'seo' => CategorySeo::for($found, $courses),
        ]);
    }
}
