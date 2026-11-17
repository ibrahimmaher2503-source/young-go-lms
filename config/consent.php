<?php

declare(strict_types=1);

return [
    // Version of the cookie consent wording. The wording is a placeholder pending owner/counsel approval (YG-GAP-PRV-004);
    // bump the version whenever it changes: a stored decision for another version counts as "not decided" and the banner returns.
    'version' => env('COOKIE_CONSENT_VERSION', 'cookies-v1-draft'),

    // First-party decision cookie ("<version>|accepted" or "<version>|declined"). Strictly necessary, set only after a choice.
    'cookie' => 'yg_cookie_consent',
    'lifetime_days' => 180,

    // R-021: marketing tags never load on these surfaces, whatever the layout variant. Patterns match route names (Str::is).
    'excluded_routes' => [
        'catalog.preview', 'public.catalog.preview',
        'learn.*', 'learner.*', 'parent.*', 'family', 'link', 'profile.*', 'home', 'help.*', 'files.*', 'media.*',
        'assistant.*', 'lessons.*', 'checkout.*', 'cart.*', 'orders.*', 'payment.*', 'certificates.*',
        'login.*', 'register.*', 'password.*', 'verification.*', 'two-factor.*',
    ],

    // Cookies the tags set on the visitor's browser; expired when consent is declined or withdrawn.
    'tag_cookies' => ['_ga', '_fbp', '_fbc'],
    'tag_cookie_prefixes' => ['_ga_'],
];
