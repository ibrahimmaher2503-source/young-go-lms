<?php

declare(strict_types=1);

return [
    /*
     * Master switch for crawling. Anything but production answers "Disallow: /" in robots.txt so staging and demo
     * copies never compete with the live site (the legacy site stays untouched, AGENTS rule 1).
     */
    'indexing' => (bool) env('SEO_INDEXING', env('APP_ENV') === 'production'),

    /** Language of x-default hreflang. Not config('app.locale'): the framework overwrites that with the active locale on every request. */
    'default_locale' => env('APP_LOCALE', 'ar'),

    /** Private, learner, staff and technical paths that crawlers must not request (YG-WEB-009). */
    'disallow' => [
        '/admin', '/instructor', '/finance', '/my', '/learn', '/family', '/profile', '/link', '/security', '/guardian',
        '/files', '/media', '/api', '/assistant', '/login', '/register', '/forgot-password', '/reset-password', '/email',
        '/verify', '/locale', '/livewire', '/_design', '/up',
    ],

    /** Cache key and lifetime (seconds) of the rendered sitemap; it is also dropped whenever public content changes. */
    'sitemap_key' => 'seo.sitemap.xml',
    'sitemap_ttl' => 86400,
];
