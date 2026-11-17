<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Content\Queries\PublicBlog;
use App\Domain\Seo\BlogSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Public blog (YG-WEB-006): index with category, tag and text filters, and the article page. Drafts and future posts answer 404. */
final class BlogController
{
    public function index(Request $request, PublicBlog $blog): View
    {
        $filters = array_filter($request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:120'],
            'tag' => ['nullable', 'string', 'max:120'],
        ]), filled(...));

        $posts = $blog->posts($filters)->appends($filters);

        return view('blog.index', [
            'posts' => $posts,
            'filters' => $filters,
            'categories' => $blog->categories(),
            'tags' => $blog->tags(),
            'seo' => BlogSeo::index($filters !== [] || $posts->currentPage() > 1),
        ]);
    }

    public function show(string $post, PublicBlog $blog): View
    {
        $found = $blog->find($post);
        abort_if($found === null, 404);

        return view('blog.show', [
            'post' => $found,
            'related' => $blog->related($found),
            'seo' => BlogSeo::post($found),
        ]);
    }
}
