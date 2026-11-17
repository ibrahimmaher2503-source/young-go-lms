<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Seo\Queries\ResolveRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Fallback route: a URL nothing else matches is redirected when the redirect table knows it (ED-018), otherwise 404. */
final class LegacyRedirectController
{
    public function __invoke(Request $request, ResolveRedirect $redirects): RedirectResponse
    {
        $hit = $redirects->for($request->getPathInfo());
        abort_if($hit === null, 404);

        $query = $request->getQueryString();

        return redirect($hit['to'].($query !== null && ! str_contains($hit['to'], '?') ? '?'.$query : ''), $hit['status']);
    }
}
